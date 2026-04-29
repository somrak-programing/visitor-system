<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

// GET: List notifications for current user
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tb_notifications WHERE user_id = ? AND is_read = 0");
        $stmtCount->execute([$userId]);
        $unreadCount = $stmtCount->fetchColumn();

        echo json_encode([
            "status" => "success",
            "data" => $notifications,
            "unread_count" => (int)$unreadCount
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
// POST: Mark one notification as read
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'read') {
    $notifId = $_POST['notif_id'] ?? '';
    if (!$notifId) {
        echo json_encode(["status" => "error", "message" => "Missing notification ID"]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE tb_notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
// POST: Mark all as read
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'read_all') {
    try {
        $stmt = $pdo->prepare("UPDATE tb_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
?>
