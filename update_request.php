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
    $requestId = $_POST['request_id'] ?? '';
    if (!$requestId) {
        throw new Exception("Missing request ID.");
    }

    // Verify ownership and status
    $stmtCheck = $pdo->prepare("SELECT created_by, status FROM tb_visitor_requests WHERE request_id = ?");
    $stmtCheck->execute([$requestId]);
    $req = $stmtCheck->fetch();

    if (!$req) {
        throw new Exception("Request not found.");
    }

    if ($req['created_by'] != $_SESSION['user_id']) {
        throw new Exception("Unauthorized to edit this request.");
    }

    if ($req['status'] === 'Final Approved') {
        throw new Exception("Cannot edit a Final Approved request.");
    }

    // Collect General Info
    $category = $_POST['category'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $tourDate = $_POST['tourDate'] ?? '';
    $companyName = $_POST['companyName'] ?? '';
    
    // Arrays from dynamically generated fields
    $visitorNames = $_POST['visitorName'] ?? [];
    $visitorTitles = $_POST['visitorTitle'] ?? [];
    
    $tcpMembers = $_POST['tcpMembers'] ?? ''; 
    
    $agendaStarts = $_POST['agendaStart'] ?? [];
    $agendaEnds = $_POST['agendaEnd'] ?? [];
    $agendaActivities = $_POST['agendaActivity'] ?? [];
    $agendaRemarks = $_POST['agendaRemark'] ?? [];
    
    $routingCourse = $_POST['routingCourse'] ?? '';
    $souvenirsArray = $_POST['souvenir'] ?? [];
    $souvenirStr = is_array($souvenirsArray) ? implode(', ', $souvenirsArray) : (string)$souvenirsArray;

    // Check for TIME OVERLAP on the same date (exclude current request)
    $stmtExisting = $pdo->prepare("
        SELECT r.request_id, r.company_name, s.start_time, s.end_time, s.activity
        FROM tb_visitor_requests r
        JOIN tb_schedules s ON r.request_id = s.request_id
        WHERE r.tour_date = ? AND r.status IN ('Pending PM', 'Pending MD', 'Final Approved') AND r.request_id != ?
    ");
    $stmtExisting->execute([$tourDate, $requestId]);
    $existingSlots = $stmtExisting->fetchAll();

    if (!empty($existingSlots) && !empty($agendaStarts)) {
        $conflicts = [];
        for ($i = 0; $i < count($agendaStarts); $i++) {
            $newStart = $agendaStarts[$i];
            $newEnd = $agendaEnds[$i];
            if (empty($newStart) || empty($newEnd)) continue;

            foreach ($existingSlots as $slot) {
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

    // 1. Update tb_visitor_requests and Reset Status to Pending PM
    $stmt1 = $pdo->prepare("UPDATE tb_visitor_requests SET company_name = ?, category = ?, purpose = ?, tour_date = ?, routing_course = ?, souvenir = ?, status = 'Pending PM' WHERE request_id = ?");
    $stmt1->execute([$companyName, $category, $purpose, $tourDate, $routingCourse, $souvenirStr, $requestId]);
    
    // 2. Clear old children records
    $pdo->prepare("DELETE FROM tb_visitors WHERE request_id = ?")->execute([$requestId]);
    $pdo->prepare("DELETE FROM tb_schedules WHERE request_id = ?")->execute([$requestId]);
    $pdo->prepare("DELETE FROM tb_tcp_members WHERE request_id = ?")->execute([$requestId]);

    // 3. Insert into tb_visitors
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

    // 4. Insert into tb_schedules
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

    // 5. Insert into tb_tcp_members
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

    // Fetch PM email for notification of update
    $stmtPM = $pdo->query("SELECT email FROM tb_users WHERE role = 'PM' AND email IS NOT NULL AND email != '' LIMIT 1");
    $pmRow = $stmtPM->fetch();
    $pmEmail = $pmRow ? $pmRow['email'] : null;

    $emailMsg = "";
    if ($pmEmail) {
        $emailStatus = sendNotificationEmail(
            $pmEmail,
            "Action Required: Updated Visitor Request #{$requestId}",
            "<h3>Visitor Request Updated</h3><p>The visitor request #{$requestId} from <strong>{$companyName}</strong> has been UPDATED by the requester.</p><p>Please log in to the portal to review the new changes.</p>"
        );
        $emailMsg = $emailStatus['status'] === 'success' ? ' (Email notification sent to PM)' : ' (Failed to send email to PM)';
    }

    echo json_encode(["status" => "success", "message" => "Request updated successfully. Status reset to Pending PM." . $emailMsg, "request_id" => $requestId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Update Failed: " . $e->getMessage()]);
}
?>
