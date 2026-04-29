<?php
require_once 'db.php';
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized Access to PDF Generator.");
}
$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    die("Error: No Request ID provided.");
}

// Fetch Request Info
$stmt = $pdo->prepare("SELECT * FROM tb_visitor_requests WHERE request_id = ?");
$stmt->execute([$request_id]);
$req = $stmt->fetch();

if (!$req) {
    die("Error: Request not found.");
}

// Fetch Visitors
$stmtV = $pdo->prepare("SELECT * FROM tb_visitors WHERE request_id = ?");
$stmtV->execute([$request_id]);
$visitors = $stmtV->fetchAll();

// Fetch TCP Members
$stmtM = $pdo->prepare("SELECT * FROM tb_tcp_members WHERE request_id = ?");
$stmtM->execute([$request_id]);
$members = $stmtM->fetchAll();

// Fetch Agendas
$stmtA = $pdo->prepare("SELECT * FROM tb_schedules WHERE request_id = ? ORDER BY start_time ASC");
$stmtA->execute([$request_id]);
$agendas = $stmtA->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor Pass - <?php echo htmlspecialchars($req['company_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .a4-page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 28px;
            text-transform: uppercase;
        }
        .section-title {
            background: #f8fafc;
            color: #0f172a;
            padding: 8px 12px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 25px;
            border-left: 4px solid #2563eb;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            font-size: 14px;
        }
        .info-box span {
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-box p {
            margin: 0;
            color: #0f172a;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #475569;
        }
        .badge {
            background: #10b981;
            color: white;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .no-print {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 20px auto;
            max-width: 210mm;
        }
        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back {
            background: #e2e8f0;
            color: #0f172a;
            border: none;
            padding: 10px 28px;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        @media print {
            body { background: white; padding: 0; }
            .a4-page { box-shadow: none; padding: 0; min-height: auto; width: 100%; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="a4-page">
    <div class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="logo.png" alt="TCP Logo" style="height: 60px; object-fit: contain;">
            <div>
                <h1>Visitor Pass</h1>
                <p style="margin:5px 0 0; color:#64748b; font-size:14px;">TCP Group Plant Tour Permission</p>
            </div>
        </div>
        <div style="text-align: right;">
            <p style="margin:0; font-size: 14px;">Req ID: <strong><?php echo 'VR-' . str_pad($req['request_id'], 4, '0', STR_PAD_LEFT); ?></strong></p>
            <span class="badge">E-PASS APPROVED</span>
        </div>
    </div>

    <div class="section-title">General Information</div>
    <div class="info-grid">
        <div class="info-box">
            <span>Company Name</span>
            <p><?php echo htmlspecialchars($req['company_name']); ?></p>
        </div>
        <div class="info-box">
            <span>Category & Purpose</span>
            <p><?php echo htmlspecialchars($req['category']); ?> - <?php echo htmlspecialchars($req['purpose']); ?></p>
        </div>
        <div class="info-box">
            <span>Plant Tour Date</span>
            <p><?php echo htmlspecialchars($req['tour_date']); ?></p>
        </div>
        <div class="info-box">
            <span>Souvenir Preparation</span>
            <p><?php echo htmlspecialchars($req['souvenir']); ?></p>
        </div>
    </div>

    <div class="section-title">Visitor Attendees</div>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Full Name</th>
                <th>Job Title</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($visitors as $index => $v): ?>
            <tr>
                <td width="10%"><?php echo $index + 1; ?></td>
                <td width="45%"><?php echo htmlspecialchars($v['fullname']); ?></td>
                <td width="45%"><?php echo htmlspecialchars($v['job_title']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Agenda & Routing</div>
    <table>
        <thead>
            <tr>
                <th width="20%">Time</th>
                <th width="40%">Activity</th>
                <th width="40%">Remark</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($agendas as $a): ?>
            <tr>
                <td><?php echo date('H:i', strtotime($a['start_time'])); ?> - <?php echo date('H:i', strtotime($a['end_time'])); ?></td>
                <td><?php echo htmlspecialchars($a['activity']); ?></td>
                <td><?php echo htmlspecialchars($a['remark']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if(!empty($req['routing_course'])): ?>
    <div class="info-box" style="margin-top:20px;">
        <span>Course of Plant Tour</span>
        <p style="font-size:14px;"><?php echo nl2br(htmlspecialchars($req['routing_course'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="section-title">Requested TCP Hosts</div>
    <ul style="font-size:14px; color:#0f172a; margin-top:10px;">
        <?php foreach($members as $m): ?>
            <li><?php echo htmlspecialchars($m['employee_name']); ?></li>
        <?php endforeach; ?>
    </ul>

    <div style="margin-top: 50px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
        <p>This is a system generated document. No signature is required.</p>
        <p>Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</div>

<div class="no-print">
    <a href="javascript:history.back()" class="btn-back">← Back</a>
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>

</body>
</html>
