<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Add Staff
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $name = trim($_POST['name']);
    $position = trim($_POST['position']);
    $wage_type = $_POST['wage_type'];
    $wage_rate = floatval($_POST['wage_rate']);
    $phone = trim($_POST['phone']);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    
    if(empty($name) || $wage_rate <= 0) {
        $error = "Name and valid wage rate are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO staff (name, position, wage_type, wage_rate, phone, hire_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdss", $name, $position, $wage_type, $wage_rate, $phone, $hire_date);
        if($stmt->execute()) {
            $success = "Staff added successfully.";
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Edit Staff
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_staff'])) {
    $id = intval($_POST['staff_id']);
    $name = trim($_POST['name']);
    $position = trim($_POST['position']);
    $wage_type = $_POST['wage_type'];
    $wage_rate = floatval($_POST['wage_rate']);
    $phone = trim($_POST['phone']);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    
    $stmt = $conn->prepare("UPDATE staff SET name=?, position=?, wage_type=?, wage_rate=?, phone=?, hire_date=? WHERE id=?");
    $stmt->bind_param("sssdssi", $name, $position, $wage_type, $wage_rate, $phone, $hire_date, $id);
    if($stmt->execute()) {
        $success = "Staff updated successfully.";
    } else {
        $error = "Update failed.";
    }
    $stmt->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM staff WHERE id = $id");
    header("Location: staff.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Staff deleted.";

// Fetch statistics
$total_staff = $conn->query("SELECT COUNT(*) as total FROM staff")->fetch_assoc()['total'];
$total_daily = $conn->query("SELECT COUNT(*) as total FROM staff WHERE wage_type='daily'")->fetch_assoc()['total'];
$total_hourly = $conn->query("SELECT COUNT(*) as total FROM staff WHERE wage_type='hourly'")->fetch_assoc()['total'];
$total_monthly = $conn->query("SELECT COUNT(*) as total FROM staff WHERE wage_type='monthly'")->fetch_assoc()['total'];

$staff_list = $conn->query("SELECT * FROM staff ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .staff-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        .stat-number { font-size: 2rem; font-weight: 700; color: #ffcc00; }
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
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid rgba(255,204,0,0.2); padding: 12px; text-align: left; }
        th { background: #1a1a1a; color: #ffcc00; }
        .edit-btn {
            background: #ffcc00;
            color: #000;
            padding: 5px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            margin-right: 8px;
        }
        .edit-btn:hover { background: #ffdd33; }
        .delete-btn { color: #ffcc00; text-decoration: none; }
        .delete-btn:hover { text-decoration: underline; }
        .edit-form-row {
            background: #1a1a1a;
        }
        .edit-form-row td {
            padding: 15px;
        }
        .inline-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .inline-form input, .inline-form select {
            padding: 8px;
            background: #222;
            border: 1px solid #ffcc00;
            border-radius: 8px;
            color: #fff;
        }
        .inline-form button {
            background: #ffcc00;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .form-grid { flex-direction: column; }
            .staff-stats { grid-template-columns: repeat(2,1fr); }
            table { font-size: 0.8rem; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>Staff Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="staff-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_staff ?></div><div class="stat-label">👥 Total Staff</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_daily ?></div><div class="stat-label">📆 Daily Wage</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_hourly ?></div><div class="stat-label">⏱️ Hourly Wage</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_monthly ?></div><div class="stat-label">📅 Monthly Salary</div></div>
        </div>

        <!-- Add Staff Form -->
        <div class="form-card">
            <h3>➕ Add New Staff Member</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" placeholder="e.g., Cashier, Manager">
                    </div>
                    <div class="form-group">
                        <label>Wage Type</label>
                        <select name="wage_type">
                            <option value="daily">Daily</option>
                            <option value="hourly">Hourly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Wage Rate *</label>
                        <input type="number" step="0.01" name="wage_rate" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_staff" class="btn-yellow">Add Staff</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Staff List -->
        <h3>📋 All Staff Members</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Position</th><th>Wage Type</th><th>Rate</th><th>Phone</th><th>Hire Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if($staff_list->num_rows > 0): ?>
                    <?php while($s = $staff_list->fetch_assoc()): ?>
                        <tr id="row-<?= $s['id'] ?>">
                            <td><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['position']) ?></td>
                            <td><?= ucfirst($s['wage_type']) ?></td>
                            <td>Rs. <?= number_format($s['wage_rate'], 2) ?> / <?= $s['wage_type'] == 'hourly' ? 'hr' : ($s['wage_type'] == 'daily' ? 'day' : 'month') ?></td>
                            <td><?= htmlspecialchars($s['phone']) ?></td>
                            <td><?= $s['hire_date'] ?: '-' ?></td>
                            <td>
                                <button onclick="showEditForm(<?= $s['id'] ?>)" class="edit-btn">Edit</button>
                                <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this staff member?')" class="delete-btn">Delete</a>
                            </td>
                        </tr>
                        <tr id="edit-<?= $s['id'] ?>" style="display:none;" class="edit-form-row">
                            <td colspan="8">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                    <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
                                    <input type="text" name="position" value="<?= htmlspecialchars($s['position']) ?>">
                                    <select name="wage_type">
                                        <option value="daily" <?= $s['wage_type']=='daily'?'selected':'' ?>>Daily</option>
                                        <option value="hourly" <?= $s['wage_type']=='hourly'?'selected':'' ?>>Hourly</option>
                                        <option value="monthly" <?= $s['wage_type']=='monthly'?'selected':'' ?>>Monthly</option>
                                    </select>
                                    <input type="number" step="0.01" name="wage_rate" value="<?= $s['wage_rate'] ?>" required>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($s['phone']) ?>">
                                    <input type="date" name="hire_date" value="<?= $s['hire_date'] ?>">
                                    <button type="submit" name="edit_staff">Update</button>
                                    <button type="button" onclick="hideEditForm(<?= $s['id'] ?>)">Cancel</button>
                                </form>
                             </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No staff found. Add your first staff member above.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showEditForm(id) {
    document.getElementById('edit-' + id).style.display = 'table-row';
}
function hideEditForm(id) {
    document.getElementById('edit-' + id).style.display = 'none';
}
</script>
</body>
</html>