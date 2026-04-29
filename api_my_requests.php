<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $stmt = $pdo->prepare("SELECT request_id, company_name, category, tour_date, status, reject_reason, created_at FROM tb_visitor_requests WHERE created_by = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll();
        echo json_encode(["status" => "success", "data" => $requests]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Missing ID"]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_visitor_requests WHERE request_id = ? AND created_by = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $req = $stmt->fetch();
        if (!$req) {
            echo json_encode(["status" => "error", "message" => "Request not found or unauthorized"]);
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
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Action"]);
}
?>
