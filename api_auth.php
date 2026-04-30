<?php
require_once 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM tb_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        echo json_encode(["status" => "success", "role" => $user['role'], "name" => $user['name']]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials. Please try again."]);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "success", "session" => [
            "name" => $_SESSION['name'],
            "role" => $_SESSION['role']
        ]]);
    } else {
        echo json_encode(["status" => "error", "message" => "Not logged in"]);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    session_destroy();
    echo json_encode(["status" => "success"]);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Not logged in"]);
        exit;
    }
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 6) {
        echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters."]);
        exit;
    }
    if ($new !== $confirm) {
        echo json_encode(["status" => "error", "message" => "New passwords do not match."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM tb_users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password'])) {
        echo json_encode(["status" => "error", "message" => "Current password is incorrect."]);
        exit;
    }

    $hashed = password_hash($new, PASSWORD_BCRYPT);
    $upd = $pdo->prepare("UPDATE tb_users SET password = ? WHERE user_id = ?");
    $upd->execute([$hashed, $_SESSION['user_id']]);
    echo json_encode(["status" => "success", "message" => "Password changed successfully."]);
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
?>
