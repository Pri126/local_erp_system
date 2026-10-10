<?php
// Set timezone to Karachi, Pakistan
date_default_timezone_set('Asia/Karachi');

require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

// Fetch dashboard statistics
$today_sales = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['total'] ?? 0;
$month_sales = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock <= min_stock")->fetch_assoc()['count'];
$total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$khata_due = $conn->query("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as due FROM khata_transactions")->fetch_assoc()['due'] ?? 0;

// Recent sales
$recent_sales = $conn->query("
    SELECT s.invoice_no, s.grand_total, s.payment_method, s.created_at, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$low_stock_products = $conn->query("SELECT name, stock, min_stock FROM products WHERE stock <= min_stock LIMIT 5");

// Prepare chart data (last 7 days)
$labels = [];
$salesData = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($date));
    $daySales = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE DATE(created_at) = '$date'")->fetch_assoc()['total'] ?? 0;
    $salesData[] = $daySales;
}
$payment_cash = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE payment_method='cash'")->fetch_assoc()['total'] ?? 0;
$payment_card = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE payment_method='card'")->fetch_assoc()['total'] ?? 0;
$payment_khata = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE payment_method='khata'")->fetch_assoc()['total'] ?? 0;

// Determine greeting based on time
$currentHour = (int)date('H');
if ($currentHour < 12) {
    $greeting = "Morning 🌅";
    $greetingIcon = "🌅";
} elseif ($currentHour < 18) {
    $greeting = "Afternoon ☀️";
    $greetingIcon = "☀️";
} else {
    $greeting = "Evening 🌙";
    $greetingIcon = "🌙";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Fill entire main area */
        .main-content {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #121212 100%);
            padding: 20px;
            width: 100%;
        }
        /* Top bar with logout */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #111;
            padding: 12px 20px;
            border-radius: 24px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,204,0,0.2);
        }
        .top-bar h1 {
            font-size: 1.6rem;
            margin: 0;
            color: #ffcc00;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .logout-btn {
            background: rgba(255,204,0,0.1);
            border: 1px solid #ffcc00;
            padding: 8px 18px;
            border-radius: 30px;
            text-decoration: none;
            color: #ffcc00;
            transition: 0.2s;
        }
        .logout-btn:hover {
            background: #ffcc00;
            color: #000;
        }
        /* Welcome Hero */
        .welcome-hero {
            background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
            border-radius: 28px;
            padding: 25px 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,204,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .welcome-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,204,0,0.05);
            border-radius: 50%;
        }
        .welcome-hero h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffcc00, #ffaa00);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }
        .greeting-icon {
            font-size: 2.5rem;
            margin-right: 12px;
            vertical-align: middle;
        }
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(17,17,17,0.8);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 20px;
            border: 1px solid rgba(255,204,0,0.2);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #ffcc00;
            background: #111;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffcc00;
            margin: 10px 0 5px;
        }
        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            background: #111;
            border-radius: 24px;
            border: 1px solid rgba(255,204,0,0.2);
            padding: 20px;
        }
        .card-header {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffcc00;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,204,0,0.2);
        }
        .recent-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .badge-cash { background: #2e7d32; color: white; }
        .badge-card { background: #1565c0; color: white; }
        .badge-khata { background: #ff8f00; color: black; }
        .alert-item {
            padding: 10px;
            background: rgba(255,204,0,0.1);
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .quick-btn {
            background: #1a1a1a;
            border: 1px solid #ffcc00;
            padding: 12px 24px;
            border-radius: 40px;
            text-decoration: none;
            color: #ffcc00;
            font-weight: 500;
            transition: 0.2s;
        }
        .quick-btn:hover {
            background: #ffcc00;
            color: #000;
        }
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .welcome-hero h2 { font-size: 1.4rem; }
            .top-bar h1 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <!-- Top Bar with Logout -->
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>

        <!-- Welcome Hero (Attractive, with correct time greeting) -->
        <div class="welcome-hero">
            <span class="greeting-icon"><?php echo $greetingIcon; ?></span>
            <h2>Good <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p>Here's what's happening with your business today. 🚀</p>
            <small style="color:#aaa;">Local time: <?php echo date('h:i A'); ?> (Karachi)</small>
        </div>

        <!-- Stats Cards (full width) -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value">₹ <?php echo number_format($today_sales, 2); ?></div><div class="stat-label">Today's Sales</div></div>
            <div class="stat-card"><div class="stat-value">₹ <?php echo number_format($month_sales, 2); ?></div><div class="stat-label">This Month</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $low_stock; ?></div><div class="stat-label">⚠️ Low Stock</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $total_customers; ?></div><div class="stat-label">👥 Customers</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $total_products; ?></div><div class="stat-label">📦 Products</div></div>
            <div class="stat-card"><div class="stat-value">₹ <?php echo number_format($khata_due, 2); ?></div><div class="stat-label">📝 Khata Due</div></div>
        </div>

        <!-- Charts & Tables Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">📈 Sales Trend (Last 7 Days)</div>
                <canvas id="salesChart" style="height:250px; width:100%;"></canvas>
            </div>
            <div class="dashboard-card">
                <div class="card-header">💳 Payment Methods</div>
                <canvas id="paymentChart" style="height:250px; width:100%;"></canvas>
            </div>
            <div class="dashboard-card">
                <div class="card-header">🕒 Recent Transactions</div>
                <?php if($recent_sales->num_rows > 0): ?>
                    <?php while($sale = $recent_sales->fetch_assoc()): ?>
                        <div class="recent-item">
                            <div><strong><?= htmlspecialchars($sale['invoice_no']) ?></strong><br><small><?= $sale['customer_name'] ?? 'Walk-in' ?> | <?= date('d M H:i', strtotime($sale['created_at'])) ?></small></div>
                            <div>₹ <?= number_format($sale['grand_total'], 2) ?><br><span class="badge badge-<?= $sale['payment_method'] ?>"><?= ucfirst($sale['payment_method']) ?></span></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px;">No sales yet today.</div>
                <?php endif; ?>
                <div style="margin-top:10px; text-align:center;"><a href="reports.php" style="color:#ffcc00;">View All →</a></div>
            </div>
            <div class="dashboard-card">
                <div class="card-header">⚠️ Low Stock Alerts</div>
                <?php if($low_stock_products->num_rows > 0): ?>
                    <?php while($item = $low_stock_products->fetch_assoc()): ?>
                        <div class="alert-item"><strong><?= htmlspecialchars($item['name']) ?></strong><br>Stock: <?= $item['stock'] ?> / Min: <?= $item['min_stock'] ?></div>
                    <?php endwhile; ?>
                    <a href="inventory.php" style="color:#ffcc00;">Manage Inventory →</a>
                <?php else: ?>
                    <div style="text-align:center; padding:20px;">✅ All products have sufficient stock</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="pos.php" class="quick-btn">🛒 New Sale</a>
            <a href="customers.php" class="quick-btn">👥 Khata Management</a>
            <a href="products.php" class="quick-btn">➕ Add Product</a>
            <a href="reports.php" class="quick-btn">📊 Full Reports</a>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: { labels: <?php echo json_encode($labels); ?>, datasets: [{ label: 'Sales (₹)', data: <?php echo json_encode($salesData); ?>, borderColor: '#ffcc00', backgroundColor: 'rgba(255,204,0,0.1)', borderWidth: 2, tension: 0.3, fill: true }] },
    options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } }, scales: { y: { ticks: { color: '#aaa' }, grid: { color: '#333' } }, x: { ticks: { color: '#aaa' }, grid: { color: '#333' } } } }
});
const ctx2 = document.getElementById('paymentChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: { labels: ['Cash', 'Card', 'Khata'], datasets: [{ data: [<?php echo $payment_cash; ?>, <?php echo $payment_card; ?>, <?php echo $payment_khata; ?>], backgroundColor: ['#2e7d32', '#1565c0', '#ff8f00'], borderColor: '#000', borderWidth: 2 }] },
    options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } } }
});
</script>
</body>
</html>