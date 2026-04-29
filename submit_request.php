<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'mailer_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please login first."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
    exit;
}

try {
    // Collect General Info
    $category = $_POST['category'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $tourDate = $_POST['tourDate'] ?? '';
    $companyName = $_POST['companyName'] ?? '';
    
    // Arrays from dynamically generated fields
    $visitorNames = $_POST['visitorName'] ?? [];
    $visitorTitles = $_POST['visitorTitle'] ?? [];
    
    $tcpMembers = $_POST['tcpMembers'] ?? ''; // From Multiple Select Box (comma separated string passed from JS)
    
    $agendaStarts = $_POST['agendaStart'] ?? [];
    $agendaEnds = $_POST['agendaEnd'] ?? [];
    $agendaActivities = $_POST['agendaActivity'] ?? [];
    $agendaRemarks = $_POST['agendaRemark'] ?? [];
    
    $routingCourse = $_POST['routingCourse'] ?? '';
    $souvenirsArray = $_POST['souvenir'] ?? [];
    $souvenirStr = is_array($souvenirsArray) ? implode(', ', $souvenirsArray) : (string)$souvenirsArray;

    // Check for TIME OVERLAP on the same date (allows multiple bookings if times don't clash)
    $stmtExisting = $pdo->prepare("
        SELECT r.request_id, r.company_name, s.start_time, s.end_time, s.activity
        FROM tb_visitor_requests r
        JOIN tb_schedules s ON r.request_id = s.request_id
        WHERE r.tour_date = ? AND r.status IN ('Pending PM', 'Pending MD', 'Final Approved')
    ");
    $stmtExisting->execute([$tourDate]);
    $existingSlots = $stmtExisting->fetchAll();

    if (!empty($existingSlots) && !empty($agendaStarts)) {
        $conflicts = [];
        for ($i = 0; $i < count($agendaStarts); $i++) {
            $newStart = $agendaStarts[$i];
            $newEnd = $agendaEnds[$i];
            if (empty($newStart) || empty($newEnd)) continue;

            foreach ($existingSlots as $slot) {
                // Overlap: newStart < existingEnd AND newEnd > existingStart
                if ($newStart < $slot['end_time'] && $newEnd > $slot['start_time']) {
                    $conflicts[] = "{$slot['company_name']} (#{$slot['request_id']}) {$slot['start_time']}-{$slot['end_time']} [{$slot['activity']}]";
                }
            }
        }

        if (!empty($conflicts)) {
            $uniqueConflicts = array_unique($conflicts);
            echo json_encode([
                "status" => "error",
                "message" => "Time conflict on {$tourDate}! Your schedule overlaps with: " . implode('; ', $uniqueConflicts) . ". Please adjust your time slots."
            ]);
            exit;
        }
    }

    // Begin Transaction
    $pdo->beginTransaction();

    $createdBy = $_SESSION['user_id'];

    // 1. Insert into tb_visitor_requests
    $stmt1 = $pdo->prepare("INSERT INTO tb_visitor_requests (company_name, category, purpose, tour_date, routing_course, souvenir, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Pending PM', ?)");
    $stmt1->execute([$companyName, $category, $purpose, $tourDate, $routingCourse, $souvenirStr, $createdBy]);
    
    $requestId = $pdo->lastInsertId();

    // 2. Insert into tb_visitors
    if (!empty($visitorNames)) {
        $stmt2 = $pdo->prepare("INSERT INTO tb_visitors (request_id, fullname, job_title) VALUES (?, ?, ?)");
        for ($i = 0; $i < count($visitorNames); $i++) {
            $fname = $visitorNames[$i];
            $title = $visitorTitles[$i] ?? '';
            if (!empty($fname)) {
                $stmt2->execute([$requestId, $fname, $title]);
            }
        }
    }

    // 3. Insert into tb_schedules
    if (!empty($agendaStarts)) {
        $stmt3 = $pdo->prepare("INSERT INTO tb_schedules (request_id, start_time, end_time, activity, remark) VALUES (?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($agendaStarts); $i++) {
            $st = $agendaStarts[$i];
            $en = $agendaEnds[$i];
            $act = $agendaActivities[$i];
            $rmk = $agendaRemarks[$i] ?? '';
            if (!empty($st) && !empty($en) && !empty($act)) {
                $stmt3->execute([$requestId, $st, $en, $act, $rmk]);
            }
        }
    }

    // 4. Insert into tb_tcp_members
    if (!empty($tcpMembers)) {
        $membersArr = is_array($tcpMembers) ? $tcpMembers : explode(',', $tcpMembers);
        $stmt4 = $pdo->prepare("INSERT INTO tb_tcp_members (request_id, employee_name) VALUES (?, ?)");
        foreach ($membersArr as $mem) {
            $mem = trim($mem);
            if (!empty($mem)) {
                $stmt4->execute([$requestId, $mem]);
            }
        }
    }

    // Commit Transaction
    $pdo->commit();
    // Helper function for ID formatting
    $fmtId = 'VR-' . str_pad($requestId, 4, '0', STR_PAD_LEFT);

    // Create in-app notification for PM users
    $stmtPMUsers = $pdo->query("SELECT user_id FROM tb_users WHERE role = 'PM'");
    $notifStmt = $pdo->prepare("INSERT INTO tb_notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'pending', ?, ?)");
    while ($pmUser = $stmtPMUsers->fetch()) {
        $notifStmt->execute([$pmUser['user_id'], $requestId, '📋 New Request Pending Approval', "New visitor request {$fmtId} from {$companyName} requires your approval."]);
    }

    // Fetch PM email
    $stmtPM = $pdo->query("SELECT email FROM tb_users WHERE role = 'PM' AND email IS NOT NULL AND email != '' LIMIT 1");
    $pmRow = $stmtPM->fetch();
    $pmEmail = $pmRow ? $pmRow['email'] : null;

    $emailMsg = "";
    if ($pmEmail) {
        $emailStatus = sendNotificationEmail(
            $pmEmail,
            "Action Required: New Visitor Request {$fmtId}",
            "<h3>New Visitor Request</h3><p>A new visitor request from <strong>{$companyName}</strong> has been submitted and is waiting for your approval as Plant Manager.</p><p>Please log in to the portal to review.</p>"
        );
        $emailMsg = $emailStatus['status'] === 'success' ? ' (Email notification sent to PM)' : ' (Failed to send email to PM)';
    }

    echo json_encode(["status" => "success", "message" => "Request submitted successfully. Pending PM Approval." . $emailMsg, "request_id" => $requestId]);

} catch (Exception $e) {
    // Rollback if any error occurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Transaction Failed: " . $e->getMessage()]);
}
?>
