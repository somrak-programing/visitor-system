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
    $stmt1 = $pdo->prepare("SELECT COUNT(*) as total_requests FROM tb_visitor_requests WHERE YEAR(tour_date) = ?");
    $stmt1->execute([$currentYear]);
    $totalRequests = $stmt1->fetchColumn();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total_approved FROM tb_visitor_requests WHERE status = 'Final Approved' AND YEAR(tour_date) = ?");
    $stmt2->execute([$currentYear]);
    $totalApproved = $stmt2->fetchColumn();

    $stmt3 = $pdo->query("SELECT tour_date FROM tb_visitor_requests WHERE status = 'Final Approved' AND tour_date >= CURDATE() ORDER BY tour_date ASC LIMIT 1");
    $nextVisit = $stmt3->fetchColumn() ?: 'None';

    // 2. Monthly Stats (Bar Chart)
    $stmtMonthly = $pdo->prepare("
        SELECT MONTH(tour_date) as month_num, COUNT(*) as cnt 
        FROM tb_visitor_requests 
        WHERE YEAR(tour_date) = ? 
        GROUP BY MONTH(tour_date)
        ORDER BY month_num
    ");
    $stmtMonthly->execute([$currentYear]);
    $monthlyDataRaw = $stmtMonthly->fetchAll();
    
    // Fill 12 months with 0 by default
    $monthlyCounts = array_fill(1, 12, 0);
    foreach ($monthlyDataRaw as $row) {
        $monthlyCounts[$row['month_num']] = (int)$row['cnt'];
    }

    // 3. Category Stats (Pie Chart)
    $stmtCategory = $pdo->prepare("SELECT category, COUNT(*) as cnt FROM tb_visitor_requests WHERE YEAR(tour_date) = ? GROUP BY category");
    $stmtCategory->execute([$currentYear]);
    $categoryDataRaw = $stmtCategory->fetchAll();
    $categories = [];
    $categoryCounts = [];
    foreach ($categoryDataRaw as $row) {
         $categories[] = $row['category'];
         $categoryCounts[] = (int)$row['cnt'];
    }

    // 4. Status Stats (Doughnut Chart)
    $stmtStatus = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM tb_visitor_requests WHERE YEAR(tour_date) = ? GROUP BY status");
    $stmtStatus->execute([$currentYear]);
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
