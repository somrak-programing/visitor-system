<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? 'month';

// Get bookings for a specific month
if ($action === 'month') {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    
    $startDate = "{$year}-{$month}-01";
    $endDate = date('Y-m-t', strtotime($startDate));

    try {
        $stmt = $pdo->prepare("
            SELECT r.request_id, r.company_name, r.category, r.tour_date, r.status,
                   GROUP_CONCAT(CONCAT(s.start_time, '-', s.end_time) SEPARATOR ', ') as time_slots
            FROM tb_visitor_requests r
            LEFT JOIN tb_schedules s ON r.request_id = s.request_id
            WHERE r.tour_date BETWEEN ? AND ? 
            AND r.status IN ('Pending PM', 'Pending MD', 'Final Approved')
            GROUP BY r.request_id
            ORDER BY r.tour_date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $bookings = $stmt->fetchAll();

        echo json_encode(["status" => "success", "data" => $bookings]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

// Check if a specific date has conflicts
elseif ($action === 'check') {
    $date = $_GET['date'] ?? '';
    $excludeId = $_GET['exclude_id'] ?? ''; // For edit mode

    if (!$date) {
        echo json_encode(["status" => "error", "message" => "Date is required"]);
        exit;
    }

    try {
        $sql = "SELECT request_id, company_name, category, status 
                FROM tb_visitor_requests 
                WHERE tour_date = ? 
                AND status IN ('Pending PM', 'Pending MD', 'Final Approved')";
        $params = [$date];

        if ($excludeId) {
            $sql .= " AND request_id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetchAll();

        echo json_encode([
            "status" => "success",
            "date" => $date,
            "count" => count($existing),
            "bookings" => $existing,
            "available" => count($existing) === 0
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
?>
