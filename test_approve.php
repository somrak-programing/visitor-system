<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['name'] = 'System Administrator';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'approve';
$_POST['request_id'] = 1;
$_POST['current_status'] = 'Pending MD';

// Mock DB if needed, but since it requires db.php, we just include api_admin.php
ob_start();
require 'api_admin.php';
$output = ob_get_clean();

echo "Output:\n" . $output;
?>
