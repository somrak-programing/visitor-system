<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'mailer_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'Sales') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$action = $_GET['action'] ?? '';

// Helper: format request ID as VR-0001
function fmtReqId($id) { return 'VR-' . str_pad($id, 4, '0', STR_PAD_LEFT); }

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    // List all visitor requests
    try {
        $stmt = $pdo->query("SELECT r.request_id, r.company_name, r.category, r.tour_date, r.status, r.created_at, u.name as requester_name 
                             FROM tb_visitor_requests r 
                             LEFT JOIN tb_users u ON r.created_by = u.user_id 
                             ORDER BY r.created_at DESC");
        $requests = $stmt->fetchAll();
        echo json_encode(["status" => "success", "data" => $requests]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'detail') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Missing request ID"]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT r.*, u.name as requester_name 
                               FROM tb_visitor_requests r 
                               LEFT JOIN tb_users u ON r.created_by = u.user_id 
                               WHERE r.request_id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) {
            echo json_encode(["status" => "error", "message" => "Request not found"]);
            exit;
        }

        $stmtVis = $pdo->prepare("SELECT fullname, job_title FROM tb_visitors WHERE request_id = ?");
        $stmtVis->execute([$id]);
        $req['visitors'] = $stmtVis->fetchAll();

        $stmtSch = $pdo->prepare("SELECT start_time, end_time, activity, remark FROM tb_schedules WHERE request_id = ?");
        $stmtSch->execute([$id]);
        $req['schedules'] = $stmtSch->fetchAll();

        $stmtTcp = $pdo->prepare("SELECT employee_name FROM tb_tcp_members WHERE request_id = ?");
        $stmtTcp->execute([$id]);
        $req['tcp_members'] = $stmtTcp->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(["status" => "success", "data" => $req]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve') {
    $request_id = $_POST['request_id'] ?? '';
    $current_status = $_POST['current_status'] ?? '';

    if (!$request_id || !$current_status) {
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

    try {
        // Workflow Logic
        $new_status = "";
        $message = "";

        if ($current_status === 'Pending PM') {
            if ($_SESSION['role'] !== 'PM') {
                echo json_encode(["status" => "error", "message" => "Only Plant Manager can approve this state."]);
                exit;
            }
            $new_status = 'Pending MD';
            
            // Fetch MD email
            $stmtMD = $pdo->query("SELECT email FROM tb_users WHERE role = 'MD' AND email IS NOT NULL AND email != '' LIMIT 1");
            $mdRow = $stmtMD->fetch();
            $targetEmail = $mdRow ? $mdRow['email'] : null;
            
            if ($targetEmail) {
                $emailStatus = sendNotificationEmail(
                    $targetEmail,
                    "Action Required: New Visitor Request " . fmtReqId($request_id),
                    "<h3>Visitor Request Pending Approval</h3><p>A new visitor request requires your approval as Managing Director.</p><p>Please log in to the portal to review.</p>"
                );
                $emailMsg = $emailStatus['status'] === 'success' ? "Email sent to MD ({$targetEmail})." : 'Email failed: ' . $emailStatus['message'];
            } else {
                $emailMsg = "No email address configured for MD.";
            }
            
            $message = "Request Approved by PM. Now Pending MD Approval. ($emailMsg)";
        } elseif ($current_status === 'Pending MD') {
            if ($_SESSION['role'] !== 'MD') {
                echo json_encode(["status" => "error", "message" => "Only Managing Director can approve this state."]);
                exit;
            }
            $new_status = 'Final Approved';
            
            // Fetch Requester email
            $stmtReq = $pdo->prepare("
                SELECT u.email 
                FROM tb_visitor_requests r 
                JOIN tb_users u ON r.created_by = u.user_id 
                WHERE r.request_id = ? AND u.email IS NOT NULL AND u.email != '' LIMIT 1
            ");
            $stmtReq->execute([$request_id]);
            $reqRow = $stmtReq->fetch();
            $targetEmail = $reqRow ? $reqRow['email'] : null;
            
            if ($targetEmail) {
                $emailStatus = sendNotificationEmail(
                    $targetEmail,
                    "Visitor Request " . fmtReqId($request_id) . " Approved",
                    "<h3>Visitor Request Approved</h3><p>The Managing Director has approved your visitor request " . fmtReqId($request_id) . ".</p><p>You can now log in to generate the digital pass for your visitors.</p>"
                );
                $emailMsg = $emailStatus['status'] === 'success' ? "Email sent to Requester ({$targetEmail})." : 'Email failed: ' . $emailStatus['message'];
            } else {
                $emailMsg = "No email address configured for the Requester.";
            }

            $message = "Request Final Approved by MD! ($emailMsg)";
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid state transition"]);
            exit;
        }

        // Update Database
        $stmt = $pdo->prepare("UPDATE tb_visitor_requests SET status = ? WHERE request_id = ?");
        $stmt->execute([$new_status, $request_id]);

        // Create in-app notifications
        $stmtCreator = $pdo->prepare("SELECT created_by, company_name FROM tb_visitor_requests WHERE request_id = ?");
        $stmtCreator->execute([$request_id]);
        $reqInfo = $stmtCreator->fetch();
        $creatorId = $reqInfo['created_by'] ?? null;
        $companyName = $reqInfo['company_name'] ?? '';

        if ($current_status === 'Pending PM' && $creatorId) {
            // Notify requester: PM approved
            $notifStmt = $pdo->prepare("INSERT INTO tb_notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'approved', ?, ?)");
            $notifStmt->execute([$creatorId, $request_id, 'PM Approved Your Request', "Request " . fmtReqId($request_id) . " ({$companyName}) has been approved by Plant Manager. Now pending MD approval."]);

            // Notify MD: new request needs attention
            $stmtMDUsers = $pdo->query("SELECT user_id FROM tb_users WHERE role = 'MD'");
            while ($mdUser = $stmtMDUsers->fetch()) {
                $notifStmt->execute([$mdUser['user_id'], $request_id, 'New Request Pending Your Approval', "Request " . fmtReqId($request_id) . " ({$companyName}) has been approved by PM and requires your approval."]);
            }
        } elseif ($current_status === 'Pending MD' && $creatorId) {
            // Notify requester: Final approved
            $notifStmt = $pdo->prepare("INSERT INTO tb_notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'approved', ?, ?)");
            $notifStmt->execute([$creatorId, $request_id, '🎉 Request Final Approved!', "Request " . fmtReqId($request_id) . " ({$companyName}) has been fully approved by Managing Director. You can now generate the visitor pass."]);
        }
        
        echo json_encode(["status" => "success", "new_status" => $new_status, "message" => $message]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject') {
    $request_id = $_POST['request_id'] ?? '';
    $reject_reason = $_POST['reject_reason'] ?? '';

    if (!$request_id || !$reject_reason) {
        echo json_encode(["status" => "error", "message" => "Missing request ID or rejection reason."]);
        exit;
    }

    try {
        // Fetch current status to check role
        $stmtCheck = $pdo->prepare("SELECT status FROM tb_visitor_requests WHERE request_id = ?");
        $stmtCheck->execute([$request_id]);
        $reqStatus = $stmtCheck->fetchColumn();

        if ($reqStatus === 'Pending PM' && $_SESSION['role'] !== 'PM') {
            echo json_encode(["status" => "error", "message" => "Only Plant Manager can reject this request."]);
            exit;
        }
        if ($reqStatus === 'Pending MD' && $_SESSION['role'] !== 'MD') {
            echo json_encode(["status" => "error", "message" => "Only Managing Director can reject this request."]);
            exit;
        }

        // Update Database
        $stmt = $pdo->prepare("UPDATE tb_visitor_requests SET status = 'Rejected', reject_reason = ? WHERE request_id = ?");
        $stmt->execute([$reject_reason, $request_id]);

        // Create in-app notification for requester
        $stmtCreator = $pdo->prepare("SELECT created_by, company_name FROM tb_visitor_requests WHERE request_id = ?");
        $stmtCreator->execute([$request_id]);
        $reqInfo = $stmtCreator->fetch();
        $creatorId = $reqInfo['created_by'] ?? null;
        $companyName = $reqInfo['company_name'] ?? '';

        if ($creatorId) {
            $notifStmt = $pdo->prepare("INSERT INTO tb_notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'rejected', ?, ?)");
            $notifStmt->execute([$creatorId, $request_id, '❌ Request Rejected', "Request " . fmtReqId($request_id) . " ({$companyName}) has been rejected. Reason: {$reject_reason}"]);
        }
        
        // Fetch Requester email
        $stmtReq = $pdo->prepare("
            SELECT u.email 
            FROM tb_visitor_requests r 
            JOIN tb_users u ON r.created_by = u.user_id 
            WHERE r.request_id = ? AND u.email IS NOT NULL AND u.email != '' LIMIT 1
        ");
        $stmtReq->execute([$request_id]);
        $reqRow = $stmtReq->fetch();
        $targetEmail = $reqRow ? $reqRow['email'] : null;
        
        $emailMsg = "";
        if ($targetEmail) {
            $emailStatus = sendNotificationEmail(
                $targetEmail,
                "Visitor Request " . fmtReqId($request_id) . " Rejected",
                "<h3>Visitor Request Rejected</h3><p>Your visitor request " . fmtReqId($request_id) . " has been rejected by the approver.</p><p><strong>Reason:</strong> " . htmlspecialchars($reject_reason) . "</p><p>Please log in to the portal to review your history.</p>"
            );
            $emailMsg = $emailStatus['status'] === 'success' ? " Email sent to Requester." : " Email failed.";
        } else {
            $emailMsg = " No email address configured for the Requester.";
        }

        echo json_encode(["status" => "success", "message" => "Request Rejected successfully." . $emailMsg]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid Action"]);
}
?>
