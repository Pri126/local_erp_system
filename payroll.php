<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Generate Payroll
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_payroll'])) {
    $staff_id = intval($_POST['staff_id']);
    $month = $_POST['month'];
    
    $staff_query = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $staff_query->bind_param("i", $staff_id);
    $staff_query->execute();
    $staff = $staff_query->get_result()->fetch_assoc();
    
    if(!$staff) {
        $error = "Staff member not found.";
    } else {
        $start_date = $month . "-01";
        $end_date = date("Y-m-t", strtotime($start_date));
        $month_date = $start_date;
        
        $total_days = 0;
        $total_hours = 0;
        
        if($staff['wage_type'] == 'daily') {
            $present_days = $conn->prepare("SELECT COUNT(*) as days FROM attendance WHERE staff_id = ? AND status = 'present' AND date BETWEEN ? AND ?");
            $present_days->bind_param("iss", $staff_id, $start_date, $end_date);
            $present_days->execute();
            $total_days = $present_days->get_result()->fetch_assoc()['days'];
            $gross = $total_days * $staff['wage_rate'];
            $present_days->close();
        } elseif($staff['wage_type'] == 'hourly') {
            $total_hours_query = $conn->prepare("SELECT SUM(hours_worked) as hrs FROM attendance WHERE staff_id = ? AND date BETWEEN ? AND ?");
            $total_hours_query->bind_param("iss", $staff_id, $start_date, $end_date);
            $total_hours_query->execute();
            $total_hours = $total_hours_query->get_result()->fetch_assoc()['hrs'] ?? 0;
            $gross = $total_hours * $staff['wage_rate'];
            $total_hours_query->close();
        } else {
            $gross = $staff['wage_rate'];
        }
        
        $deductions = 0;
        $net = $gross - $deductions;
        
        $check = $conn->prepare("SELECT id FROM payroll WHERE staff_id = ? AND month_year = ?");
        $check->bind_param("is", $staff_id, $month_date);
        $check->execute();
        if($check->get_result()->num_rows > 0) {
            $error = "Payroll already generated for this staff in selected month.";
        } else {
            $stmt = $conn->prepare("INSERT INTO payroll (staff_id, month_year, total_days, total_hours, gross_salary, deductions, net_salary) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isidddd", $staff_id, $month_date, $total_days, $total_hours, $gross, $deductions, $net);
            if($stmt->execute()) {
                $success = "Payroll generated for {$staff['name']} - Net: Rs. " . number_format($net, 2);
            } else {
                $error = "Database error.";
            }
            $stmt->close();
        }
        $check->close();
    }
    $staff_query->close();
}

// Handle Mark as Paid
if(isset($_GET['pay'])) {
    $payroll_id = intval($_GET['pay']);
    $conn->query("UPDATE payroll SET paid_at = NOW() WHERE id = $payroll_id");
    header("Location: payroll.php?paid=1");
    exit;
}
if(isset($_GET['paid'])) $success = "Payroll marked as paid.";

// Handle Delete
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM payroll WHERE id = $id");
    header("Location: payroll.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Payroll record deleted.";

// Statistics
$total_paid = $conn->query("SELECT SUM(net_salary) as total FROM payroll WHERE paid_at IS NOT NULL")->fetch_assoc()['total'] ?? 0;
$total_unpaid = $conn->query("SELECT SUM(net_salary) as total FROM payroll WHERE paid_at IS NULL")->fetch_assoc()['total'] ?? 0;
$payroll_count = $conn->query("SELECT COUNT(*) as count FROM payroll")->fetch_assoc()['count'];
$staff_count = $conn->query("SELECT COUNT(DISTINCT staff_id) as count FROM payroll")->fetch_assoc()['count'];

$payrolls = $conn->query("
    SELECT p.*, s.name as staff_name 
    FROM payroll p 
    JOIN staff s ON p.staff_id = s.id 
    ORDER BY p.month_year DESC, p.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payroll-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #111 0%, #1a1a1a 100%);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,204,0,0.2);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: #ffcc00; }
        .stat-number { font-size: 1.8rem; font-weight: 700; color: #ffcc00; }
        .stat-label { color: #aaa; margin-top: 8px; font-size: 0.85rem; }
        .form-card {
            background: #111;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,204,0,0.2);
        }
        .form-card h3 {
            color: #ffcc00;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; color: #aaa; font-size: 0.8rem; margin-bottom: 5px; }
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            background: #222;
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
        }
        .btn-yellow {
            background: #ffcc00;
            color: #000;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-yellow:hover { background: #ffdd33; transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid rgba(255,204,0,0.2); padding: 12px; text-align: left; }
        th { background: #1a1a1a; color: #ffcc00; }
        .paid { color: #4caf50; font-weight: bold; }
        .unpaid { color: #ff9800; font-weight: bold; }
        .pay-btn, .delete-btn { color: #ffcc00; text-decoration: none; margin-right: 10px; }
        @media (max-width: 768px) {
            .form-grid { flex-direction: column; }
            .payroll-stats { grid-template-columns: repeat(2,1fr); }
            th, td { padding: 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>Payroll Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="payroll-stats">
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_paid, 0) ?></div><div class="stat-label">✅ Total Paid</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_unpaid, 0) ?></div><div class="stat-label">⏳ Pending</div></div>
            <div class="stat-card"><div class="stat-number"><?= $payroll_count ?></div><div class="stat-label">📄 Payroll Records</div></div>
            <div class="stat-card"><div class="stat-number"><?= $staff_count ?></div><div class="stat-label">👥 Staff with Payroll</div></div>
        </div>

        <!-- Generate Payroll Form -->
        <div class="form-card">
            <h3>💰 Generate New Payroll</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Staff Member</label>
                        <select name="staff_id" required>
                            <option value="">Select Staff</option>
                            <?php $staffs = $conn->query("SELECT id, name FROM staff ORDER BY name");
                            while($s = $staffs->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <input type="month" name="month" value="<?= date('Y-m') ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="generate_payroll" class="btn-yellow">Generate Payroll</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Payroll Records -->
        <h3>📋 Payroll History</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Staff</th><th>Month</th><th>Days/Hours</th><th>Gross</th><th>Deductions</th><th>Net Salary</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if($payrolls->num_rows > 0): ?>
                    <?php while($row = $payrolls->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['staff_name']) ?></td>
                            <td><?= date('F Y', strtotime($row['month_year'])) ?></td>
                            <td>
                                <?php if($row['total_days'] > 0): ?>
                                    <?= $row['total_days'] ?> days
                                <?php elseif($row['total_hours'] > 0): ?>
                                    <?= $row['total_hours'] ?> hours
                                <?php else: ?> - <?php endif; ?>
                            </td>
                            <td>₹ <?= number_format($row['gross_salary'], 2) ?></td>
                            <td>₹ <?= number_format($row['deductions'], 2) ?></td>
                            <td>₹ <?= number_format($row['net_salary'], 2) ?></td>
                            <td class="<?= $row['paid_at'] ? 'paid' : 'unpaid' ?>"><?= $row['paid_at'] ? 'Paid' : 'Pending' ?></td>
                            <td>
                                <?php if(!$row['paid_at']): ?>
                                    <a href="?pay=<?= $row['id'] ?>" onclick="return confirm('Mark this payroll as paid?')" class="pay-btn">Mark Paid</a>
                                <?php endif; ?>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this record?')" class="delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No payroll records found. Generate payroll for staff above.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>