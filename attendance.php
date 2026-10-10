<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Mark Attendance
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $staff_id = intval($_POST['staff_id']);
    $date = $_POST['date'];
    $hours = floatval($_POST['hours_worked']);
    $status = $_POST['status'];
    
    $check = $conn->prepare("SELECT id FROM attendance WHERE staff_id = ? AND date = ?");
    $check->bind_param("is", $staff_id, $date);
    $check->execute();
    if($check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE attendance SET hours_worked = ?, status = ? WHERE staff_id = ? AND date = ?");
        $stmt->bind_param("dsis", $hours, $status, $staff_id, $date);
        $action = "updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO attendance (staff_id, date, hours_worked, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $staff_id, $date, $hours, $status);
        $action = "recorded";
    }
    if($stmt->execute()) {
        $success = "Attendance $action for " . date('d-m-Y', strtotime($date));
    } else {
        $error = "Database error.";
    }
    $stmt->close();
    $check->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM attendance WHERE id = $id");
    header("Location: attendance.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Attendance record deleted.";

// Get filter
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$start_date = $filter_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Statistics for the month
$stats = $conn->query("
    SELECT 
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
        COUNT(*) as total
    FROM attendance a
    WHERE a.date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc();
$present = $stats['present'] ?? 0;
$absent = $stats['absent'] ?? 0;
$late = $stats['late'] ?? 0;
$total = $stats['total'] ?? 0;

// Attendance records
$records = $conn->prepare("
    SELECT a.*, s.name as staff_name 
    FROM attendance a 
    JOIN staff s ON a.staff_id = s.id 
    WHERE a.date BETWEEN ? AND ? 
    ORDER BY a.date DESC, s.name
");
$records->bind_param("ss", $start_date, $end_date);
$records->execute();
$att_records = $records->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .attendance-stats {
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
        .stat-number { font-size: 2.2rem; font-weight: 700; color: #ffcc00; }
        .stat-label { color: #aaa; margin-top: 8px; font-size: 0.9rem; }
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            background: #222;
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #ffcc00;
            outline: none;
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
        .month-filter {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .month-filter input {
            background: #222;
            border: 1px solid #333;
            padding: 8px 12px;
            border-radius: 12px;
            color: #ffcc00;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid rgba(255,204,0,0.2); padding: 12px; text-align: left; }
        th { background: #1a1a1a; color: #ffcc00; }
        .status-present { color: #4caf50; font-weight: bold; }
        .status-absent { color: #f44336; font-weight: bold; }
        .status-late { color: #ff9800; font-weight: bold; }
        .delete-btn { color: #ffcc00; text-decoration: none; }
        .delete-btn:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            .form-grid { flex-direction: column; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>Staff Attendance</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="attendance-stats">
            <div class="stat-card"><div class="stat-number"><?= $present ?></div><div class="stat-label">✅ Present</div></div>
            <div class="stat-card"><div class="stat-number"><?= $absent ?></div><div class="stat-label">❌ Absent</div></div>
            <div class="stat-card"><div class="stat-number"><?= $late ?></div><div class="stat-label">⏰ Late</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total ?></div><div class="stat-label">📋 Total Records</div></div>
        </div>

        <!-- Mark Attendance Form -->
        <div class="form-card">
            <h3>📝 Mark / Update Attendance</h3>
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
                        <label>Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hours Worked</label>
                        <input type="number" step="0.5" name="hours_worked" placeholder="e.g., 8" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="mark_attendance" class="btn-yellow">Save Attendance</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Month Filter & Records -->
        <div class="month-filter">
            <h3>📅 Attendance Records</h3>
            <form method="get">
                <input type="month" name="month" value="<?= $filter_month ?>" onchange="this.form.submit()">
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Staff</th><th>Date</th><th>Hours</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if($att_records->num_rows > 0): ?>
                    <?php while($row = $att_records->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['staff_name']) ?></td>
                            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                            <td><?= $row['hours_worked'] ?></td>
                            <td class="status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
                            <td><a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this record?')" class="delete-btn">Delete</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No records for selected month.<?php if($total==0) echo " Use the form above to mark attendance."; ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>