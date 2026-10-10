<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$uid = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Open Shift
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['open_shift'])) {
    $opening_balance = floatval($_POST['opening_balance']);
    $check = $conn->query("SELECT id FROM cash_sessions WHERE user_id = $uid AND status = 'open'");
    if($check->num_rows > 0) {
        $error = "You already have an open shift. Close it first.";
    } else {
        $stmt = $conn->prepare("INSERT INTO cash_sessions (user_id, opening_balance, status) VALUES (?, ?, 'open')");
        $stmt->bind_param("id", $uid, $opening_balance);
        if($stmt->execute()) {
            $success = "Shift opened with opening balance ₹ " . number_format($opening_balance, 2);
        } else {
            $error = "Failed to open shift.";
        }
        $stmt->close();
    }
}

// Handle Close Shift
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['close_shift'])) {
    $closing_balance = floatval($_POST['closing_balance']);
    $stmt = $conn->prepare("UPDATE cash_sessions SET closed_at = NOW(), closing_balance = ?, status = 'closed' WHERE user_id = ? AND status = 'open'");
    $stmt->bind_param("di", $closing_balance, $uid);
    if($stmt->execute()) {
        // Calculate expected (opening + cash sales)
        $get_session = $conn->query("SELECT opening_balance, closing_balance FROM cash_sessions WHERE user_id = $uid AND status = 'closed' ORDER BY id DESC LIMIT 1");
        $row = $get_session->fetch_assoc();
        $success = "Shift closed. Closing balance: ₹ " . number_format($closing_balance, 2);
    } else {
        $error = "Failed to close shift.";
    }
    $stmt->close();
}

// Handle Discount Approval PIN
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_discount'])) {
    $pin = $_POST['pin'];
    $correct_pin = "1234";
    if($pin == $correct_pin) {
        $success = "Discount approved! You can now apply discounts.";
    } else {
        $error = "Wrong PIN. Discount not approved.";
    }
}

// Get current open shift
$open_shift = $conn->query("SELECT * FROM cash_sessions WHERE user_id = $uid AND status = 'open'")->fetch_assoc();

// Statistics
$total_cash_sales = $conn->query("SELECT SUM(amount_paid) as total FROM sales WHERE payment_method='cash' AND cashier_id=$uid")->fetch_assoc()['total'] ?? 0;
$total_shifts = $conn->query("SELECT COUNT(*) as total FROM cash_sessions WHERE user_id=$uid")->fetch_assoc()['total'];
$total_shortage = 0; // can be calculated if needed

$history = $conn->query("SELECT * FROM cash_sessions WHERE user_id = $uid ORDER BY id DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cash Management - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .cash-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        .card {
            background: #111;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,204,0,0.2);
        }
        .card h3 {
            color: #ffcc00;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #ffcc00;
            padding-left: 15px;
        }
        .shift-status {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .badge-open {
            background: #2e7d32;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: bold;
        }
        .badge-closed {
            background: #c62828;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            color: #aaa;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: #222;
            border: 1px solid #333;
            border-radius: 14px;
            color: #fff;
        }
        .btn-yellow {
            background: #ffcc00;
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-yellow:hover { background: #ffdd33; transform: translateY(-2px); }
        .btn-danger {
            background: #c62828;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid rgba(255,204,0,0.2);
            padding: 10px;
            text-align: left;
        }
        th {
            background: #1a1a1a;
            color: #ffcc00;
        }
        @media (max-width: 768px) {
            .cash-stats { grid-template-columns: repeat(2,1fr); }
            .shift-status { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>💰 Cash Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="cash-stats">
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_cash_sales, 0) ?></div><div class="stat-label">💵 Cash Sales (All time)</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_shifts ?></div><div class="stat-label">🔄 Total Shifts</div></div>
            <?php if($open_shift): ?>
                <div class="stat-card"><div class="stat-number">₹ <?= number_format($open_shift['opening_balance'], 2) ?></div><div class="stat-label">💰 Opening Balance (Current)</div></div>
            <?php else: ?>
                <div class="stat-card"><div class="stat-number">—</div><div class="stat-label">No open shift</div></div>
            <?php endif; ?>
        </div>

        <!-- Shift Management -->
        <div class="card">
            <h3>🔄 Cash Drawer Shift</h3>
            <div class="shift-status">
                <div>
                    <strong>Current Status:</strong> 
                    <?php if($open_shift): ?>
                        <span class="badge-open">🟢 OPEN</span>
                        <span style="margin-left: 15px;">Opened: <?= $open_shift['opened_at'] ?></span>
                    <?php else: ?>
                        <span class="badge-closed">🔴 CLOSED</span>
                        <span style="margin-left: 15px;">No active shift</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if($open_shift): ?>
                        <span>Total cash sales collected: ₹ <?= number_format($open_shift['closing_balance'] ?? 0, 2) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($open_shift): ?>
                <form method="post">
                    <div class="form-group">
                        <label>Closing Balance (Count cash in drawer)</label>
                        <input type="number" step="0.01" name="closing_balance" required>
                    </div>
                    <button type="submit" name="close_shift" class="btn-yellow">🔒 Close Shift</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label>Opening Balance (Starting cash in drawer)</label>
                        <input type="number" step="0.01" name="opening_balance" required>
                    </div>
                    <button type="submit" name="open_shift" class="btn-yellow">🔓 Open Shift</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Discount Approval PIN -->
        <div class="card">
            <h3>🔐 Discount Approval</h3>
            <p>Managers only: Enter PIN to approve discounts for sales.</p>
            <form method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="flex:1;">
                    <label>Approval PIN</label>
                    <input type="password" name="pin" placeholder="Enter 4-digit PIN" required>
                </div>
                <button type="submit" name="approve_discount" class="btn-yellow">Approve Discount</button>
            </form>
        </div>

        <!-- Shift History -->
        <div class="card">
            <h3>📋 Shift History</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Opened At</th><th>Closed At</th><th>Opening Balance</th><th>Closing Balance</th><th>Difference</th></tr>
                    </thead>
                    <tbody>
                    <?php if($history->num_rows > 0): ?>
                        <?php while($h = $history->fetch_assoc()): 
                            $diff = ($h['closing_balance'] ?? 0) - $h['opening_balance'];
                        ?>
                            <tr>
                                <td><?= $h['opened_at'] ?></td>
                                <td><?= $h['closed_at'] ?? '-' ?></td>
                                <td>₹ <?= number_format($h['opening_balance'], 2) ?></td>
                                <td>₹ <?= number_format($h['closing_balance'] ?? 0, 2) ?></td>
                                    <td style="color: <?= $diff >= 0 ? '#4caf50' : '#f44336' ?>">₹ <?= number_format($diff, 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No shifts recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>