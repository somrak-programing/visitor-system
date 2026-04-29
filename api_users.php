<?php
require_once 'db.php';
header('Content-Type: application/json');

// Check authorization (Must be logged in and role must be Admin or MD)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'MD'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $stmt = $pdo->query("SELECT user_id, username, email, role, name FROM tb_users ORDER BY user_id DESC");
    $users = $stmt->fetchAll();
    echo json_encode(["status" => "success", "data" => $users]);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $name = $_POST['name'] ?? '';

    if (empty($username) || empty($password) || empty($role) || empty($name)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(["status" => "error", "message" => "Username already exists."]);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO tb_users (username, password, email, role, name) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$username, $hashed_password, $email, $role, $name])) {
        echo json_encode(["status" => "success", "message" => "User added successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add user."]);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $userId = $_POST['user_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $name = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? ''; // Optional for update

    if (empty($userId) || empty($username) || empty($role) || empty($name)) {
        echo json_encode(["status" => "error", "message" => "Required fields are missing."]);
        exit;
    }

    // Check if new username conflicts with another user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_users WHERE username = ? AND user_id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(["status" => "error", "message" => "Username is already taken by another user."]);
        exit;
    }

    if (!empty($password)) {
        // Update with password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE tb_users SET username = ?, password = ?, email = ?, role = ?, name = ? WHERE user_id = ?");
        $result = $stmt->execute([$username, $hashed_password, $email, $role, $name, $userId]);
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("UPDATE tb_users SET username = ?, email = ?, role = ?, name = ? WHERE user_id = ?");
        $result = $stmt->execute([$username, $email, $role, $name, $userId]);
    }

    if ($result) {
        echo json_encode(["status" => "success", "message" => "User updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update user."]);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $userId = $_POST['user_id'] ?? '';

    // Prevent deleting oneself
    if ($userId == $_SESSION['user_id']) {
        echo json_encode(["status" => "error", "message" => "You cannot delete your own account."]);
        exit;
    }

    if (empty($userId)) {
        echo json_encode(["status" => "error", "message" => "User ID is required."]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM tb_users WHERE user_id = ?");
    if ($stmt->execute([$userId])) {
        echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete user."]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid action."]);
}
?>
