<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$filter = $_GET['period'] ?? 'today';
if($filter == 'today') {
    $date_cond = "DATE(s.created_at) = CURDATE()";
} elseif($filter == 'week') {
    $date_cond = "s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif($filter == 'month') {
    $date_cond = "s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} else {
    $date_cond = "1=1";
}

// Total sales and count
$sales_result = $conn->query("SELECT SUM(s.grand_total) as total, COUNT(*) as count FROM sales s WHERE $date_cond");
$sales = $sales_result->fetch_assoc();

// Top selling products
$top_products = $conn->query("
    SELECT p.name, SUM(si.quantity) as qty 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN sales s ON si.sale_id = s.id 
    WHERE $date_cond 
    GROUP BY p.id 
    ORDER BY qty DESC 
    LIMIT 5
");

// Top customers
$top_customers = $conn->query("
    SELECT c.name, SUM(s.grand_total) as total_spent 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE $date_cond 
    GROUP BY s.customer_id 
    ORDER BY total_spent DESC 
    LIMIT 5
");

// Payment methods
$payment_methods = $conn->query("
    SELECT s.payment_method, SUM(s.grand_total) as total, COUNT(*) as count 
    FROM sales s 
    WHERE $date_cond 
    GROUP BY s.payment_method
");

// Recent sales
$recent = $conn->query("
    SELECT s.invoice_no, s.grand_total, s.payment_method, s.created_at, c.name as cname 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE $date_cond 
    ORDER BY s.created_at DESC 
    LIMIT 20
");

// Prepare data for charts
$last7days = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daySales = $conn->query("SELECT SUM(grand_total) as total FROM sales WHERE DATE(created_at) = '$date'")->fetch_assoc()['total'] ?? 0;
    $last7days[] = ['date' => date('d M', strtotime($date)), 'sales' => $daySales];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .report-stats {
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
        .stat-number { font-size: 1.8rem; font-weight: 700; color: #ffcc00; }
        .stat-label { color: #aaa; margin-top: 8px; font-size: 0.85rem; }
        .filter-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: #111;
            border: 1px solid #ffcc00;
            color: #ffcc00;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: 0.2s;
        }
        .filter-btn.active, .filter-btn:hover {
            background: #ffcc00;
            color: #000;
        }
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: #111;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(255,204,0,0.2);
        }
        .chart-card h3 {
            color: #ffcc00;
            margin-bottom: 15px;
            font-size: 1.1rem;
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
            .chart-row { grid-template-columns: 1fr; }
            .report-stats { grid-template-columns: repeat(2,1fr); }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>📊 Sales Reports</h1></div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <a href="?period=today" class="filter-btn <?= $filter=='today'?'active':'' ?>">Today</a>
            <a href="?period=week" class="filter-btn <?= $filter=='week'?'active':'' ?>">Last 7 Days</a>
            <a href="?period=month" class="filter-btn <?= $filter=='month'?'active':'' ?>">Last 30 Days</a>
            <a href="?period=all" class="filter-btn <?= $filter=='all'?'active':'' ?>">All Time</a>
        </div>

        <!-- Stats Cards -->
        <div class="report-stats">
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($sales['total'] ?? 0, 2) ?></div><div class="stat-label">Total Sales</div></div>
            <div class="stat-card"><div class="stat-number"><?= $sales['count'] ?? 0 ?></div><div class="stat-label">Invoices</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format(($sales['total'] ?? 0) / max(1, ($sales['count'] ?? 1)), 2) ?></div><div class="stat-label">Avg. Invoice</div></div>
        </div>

        <!-- Charts Row -->
        <div class="chart-row">
            <!-- Sales Trend Chart -->
            <div class="chart-card">
                <h3>📈 Sales Trend (Last 7 Days)</h3>
                <canvas id="salesTrendChart" style="height:250px; width:100%;"></canvas>
            </div>
            <!-- Payment Methods Doughnut -->
            <div class="chart-card">
                <h3>💳 Payment Methods</h3>
                <canvas id="paymentChart" style="height:250px; width:100%;"></canvas>
            </div>
        </div>

        <!-- Top Products & Top Customers Row -->
        <div class="chart-row">
            <div class="chart-card">
                <h3>🏆 Top Selling Products</h3>
                <table style="width:100%">
                    <thead><tr><th>Product</th><th>Quantity Sold</th></tr></thead>
                    <tbody>
                    <?php if($top_products->num_rows > 0): ?>
                        <?php while($tp = $top_products->fetch_assoc()): ?>
                            <tr><td><?= htmlspecialchars($tp['name']) ?></td><td><?= $tp['qty'] ?></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="chart-card">
                <h3>🏆 Top Customers (by spending)</h3>
                <table style="width:100%">
                    <thead><tr><th>Customer</th><th>Total Spent</th></tr></thead>
                    <tbody>
                    <?php if($top_customers->num_rows > 0): ?>
                        <?php while($tc = $top_customers->fetch_assoc()): ?>
                            <tr><td><?= htmlspecialchars($tc['name'] ?? 'Walk-in') ?></td><td>₹ <?= number_format($tc['total_spent'], 2) ?></td></tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="chart-card" style="margin-top: 10px;">
            <h3>🕒 Recent Transactions</h3>
            <div style="overflow-x: auto;">
                </table>
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Payment Method</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if($recent->num_rows > 0): ?>
                        <?php while($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($row['cname'] ?? 'Walk-in') ?></td>
                                <td>₹ <?= number_format($row['grand_total'], 2) ?></td>
                                <td><?= ucfirst($row['payment_method']) ?></td>
                                <td><?= $row['created_at'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No sales recorded for this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Sales trend chart data
const ctxTrend = document.getElementById('salesTrendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($last7days, 'date')) ?>,
        datasets: [{
            label: 'Sales (₹)',
            data: <?= json_encode(array_column($last7days, 'sales')) ?>,
            borderColor: '#ffcc00',
            backgroundColor: 'rgba(255, 204, 0, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#ffcc00',
            pointBorderColor: '#000'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { labels: { color: '#fff' } } },
        scales: { y: { ticks: { color: '#aaa' }, grid: { color: '#333' } }, x: { ticks: { color: '#aaa' }, grid: { color: '#333' } } }
    }
});

// Payment methods chart data
<?php
$pm_cash = 0; $pm_card = 0; $pm_khata = 0;
$payment_methods->data_seek(0);
while($pm = $payment_methods->fetch_assoc()) {
    if($pm['payment_method'] == 'cash') $pm_cash = $pm['total'];
    elseif($pm['payment_method'] == 'card') $pm_card = $pm['total'];
    elseif($pm['payment_method'] == 'khata') $pm_khata = $pm['total'];
}
?>
const ctxPie = document.getElementById('paymentChart').getContext('2d');
new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: ['Cash', 'Card', 'Khata'],
        datasets: [{
            data: [<?= $pm_cash ?>, <?= $pm_card ?>, <?= $pm_khata ?>],
            backgroundColor: ['#2e7d32', '#1565c0', '#ff8f00'],
            borderColor: '#000',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#fff' } } }
    }
});
</script>
</body>
</html>