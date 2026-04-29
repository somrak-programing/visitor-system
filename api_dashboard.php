<?php
require_once 'db.php';
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'Sales') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

try {
    $currentYear = date('Y');

    // 1. KPI Boxes
    $stmt1 = $pdo->query("SELECT COUNT(*) as total_requests FROM tb_visitor_requests WHERE YEAR(tour_date) = $currentYear");
    $totalRequests = $stmt1->fetchColumn();

    $stmt2 = $pdo->query("SELECT COUNT(*) as total_approved FROM tb_visitor_requests WHERE status = 'Final Approved' AND YEAR(tour_date) = $currentYear");
    $totalApproved = $stmt2->fetchColumn();

    $stmt3 = $pdo->query("SELECT tour_date FROM tb_visitor_requests WHERE status = 'Final Approved' AND tour_date >= CURDATE() ORDER BY tour_date ASC LIMIT 1");
    $nextVisit = $stmt3->fetchColumn() ?: 'None';

    // 2. Monthly Stats (Bar Chart)
    $stmtMonthly = $pdo->query("
        SELECT MONTH(tour_date) as month_num, COUNT(*) as cnt 
        FROM tb_visitor_requests 
        WHERE YEAR(tour_date) = $currentYear 
        GROUP BY MONTH(tour_date)
        ORDER BY month_num
    ");
    $monthlyDataRaw = $stmtMonthly->fetchAll();
    
    // Fill 12 months with 0 by default
    $monthlyCounts = array_fill(1, 12, 0);
    foreach ($monthlyDataRaw as $row) {
        $monthlyCounts[$row['month_num']] = (int)$row['cnt'];
    }

    // 3. Category Stats (Pie Chart)
    $stmtCategory = $pdo->query("SELECT category, COUNT(*) as cnt FROM tb_visitor_requests WHERE YEAR(tour_date) = $currentYear GROUP BY category");
    $categoryDataRaw = $stmtCategory->fetchAll();
    $categories = [];
    $categoryCounts = [];
    foreach ($categoryDataRaw as $row) {
         $categories[] = $row['category'];
         $categoryCounts[] = (int)$row['cnt'];
    }

    // 4. Status Stats (Doughnut Chart)
    $stmtStatus = $pdo->query("SELECT status, COUNT(*) as cnt FROM tb_visitor_requests WHERE YEAR(tour_date) = $currentYear GROUP BY status");
    $statusDataRaw = $stmtStatus->fetchAll();
    $statuses = [];
    $statusCounts = [];
    foreach ($statusDataRaw as $row) {
         $statuses[] = $row['status'];
         $statusCounts[] = (int)$row['cnt'];
    }

    echo json_encode([
        "status" => "success",
        "params" => ["year" => $currentYear],
        "kpis" => [
            "totalRequests" => $totalRequests,
            "totalApproved" => $totalApproved,
            "nextVisit" => $nextVisit
        ],
        "charts" => [
            "monthly" => [
                "labels" => ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                "data" => array_values($monthlyCounts)
            ],
            "category" => [
                "labels" => $categories,
                "data" => $categoryCounts
            ],
            "status" => [
                "labels" => $statuses,
                "data" => $statusCounts
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
