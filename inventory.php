<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Edge-AI stock counting (demo)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edge_ai'])) {
    $product_id = intval($_POST['product_id']);
    $detected_qty = rand(1, 10);
    $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    $stmt->bind_param("ii", $detected_qty, $product_id);
    if($stmt->execute()) {
        $success = "🧠 AI detected $detected_qty units. Stock updated for product ID $product_id.";
    } else {
        $error = "Failed to update stock.";
    }
    $stmt->close();
}

// Handle manual stock adjustment
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_stock, $product_id);
    if($stmt->execute()) {
        $success = "Stock updated successfully.";
    } else {
        $error = "Update failed.";
    }
    $stmt->close();
}

// Statistics
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$total_stock = $conn->query("SELECT SUM(stock) as total FROM products")->fetch_assoc()['total'] ?? 0;
$low_stock_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock <= min_stock")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock = 0")->fetch_assoc()['total'];
$expired_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()['total'];

$products = $conn->query("SELECT * FROM products ORDER BY stock ASC, id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .inventory-stats {
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
            align-items: center;
        }
        .filter-bar input {
            flex: 1;
            padding: 12px;
            background: #222;
            border: 1px solid #333;
            border-radius: 30px;
            color: #fff;
        }
        .filter-bar select {
            padding: 12px;
            background: #222;
            border: 1px solid #333;
            border-radius: 30px;
            color: #ffcc00;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid rgba(255,204,0,0.2);
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background: #1a1a1a;
            color: #ffcc00;
        }
        .stock-bar-container {
            background: #333;
            border-radius: 10px;
            height: 8px;
            width: 100px;
            overflow: hidden;
        }
        .stock-bar {
            background: #ffcc00;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }
        .low-stock-text {
            color: #ffcc00;
            font-weight: bold;
        }
        .expired-text {
            color: #ff6666;
        }
        .btn-small {
            background: #ffcc00;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.8rem;
            margin: 2px;
        }
        .btn-small-danger {
            background: #c62828;
            color: #fff;
        }
        .inline-adjust {
            display: inline-flex;
            gap: 5px;
            align-items: center;
        }
        .inline-adjust input {
            width: 70px;
            padding: 5px;
            background: #222;
            border: 1px solid #ffcc00;
            border-radius: 8px;
            color: #fff;
        }
        @media (max-width: 768px) {
            .inventory-stats { grid-template-columns: repeat(2,1fr); }
            th, td { padding: 8px; font-size: 0.8rem; }
            .stock-bar-container { width: 60px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>📊 Inventory Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="inventory-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_products ?></div><div class="stat-label">📦 Total Products</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_stock ?></div><div class="stat-label">📦 Total Units</div></div>
            <div class="stat-card"><div class="stat-number"><?= $low_stock_count ?></div><div class="stat-label">⚠️ Low Stock</div></div>
            <div class="stat-card"><div class="stat-number"><?= $out_of_stock ?></div><div class="stat-label">📭 Out of Stock</div></div>
            <div class="stat-card"><div class="stat-number"><?= $expired_count ?></div><div class="stat-label">⏰ Expired</div></div>
        </div>

        <!-- Search & Filter -->
        <div class="filter-bar">
            <input type="text" id="searchInventory" placeholder="🔍 Search by name or barcode..." onkeyup="filterTable()">
            <select id="stockFilter" onchange="filterTable()">
                <option value="all">All Products</option>
                <option value="low">Low Stock Only</option>
                <option value="out">Out of Stock</option>
                <option value="expired">Expired</option>
            </select>
        </div>

        <!-- Inventory Table -->
        <div style="overflow-x: auto;">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Barcode</th><th>Stock</th><th>Min Stock</th><th>Status</th><th>Expiry</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($products->num_rows > 0): ?>
                    <?php while($p = $products->fetch_assoc()): 
                        $stock_percent = ($p['min_stock'] > 0) ? min(100, ($p['stock'] / $p['min_stock']) * 100) : 100;
                        $is_low = ($p['stock'] <= $p['min_stock']);
                        $is_out = ($p['stock'] == 0);
                        $is_expired = ($p['expiry_date'] && $p['expiry_date'] < date('Y-m-d'));
                        $row_class = $is_low ? 'low-stock-row' : '';
                    ?>
                        <tr data-stock-type="<?= $is_out ? 'out' : ($is_low ? 'low' : ($is_expired ? 'expired' : 'normal')) ?>">
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['barcode']) ?></td>
                            <td>
                                <?= $p['stock'] ?>
                                <?php if($p['min_stock'] > 0): ?>
                                    <div class="stock-bar-container">
                                        <div class="stock-bar" style="width: <?= $stock_percent ?>%;"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $p['min_stock'] ?></td>
                            <td class="<?= $is_out ? 'low-stock-text' : ($is_low ? 'low-stock-text' : '') ?>">
                                <?php if($is_out): ?>❌ Out of Stock<?php elseif($is_low): ?>⚠️ Low Stock<?php else: ?>✅ In Stock<?php endif; ?>
                                <?php if($is_expired): ?><br><span class="expired-text">🗓️ Expired</span><?php endif; ?>
                            </td>
                            <td><?= $p['expiry_date'] ? date('d-m-Y', strtotime($p['expiry_date'])) : '-' ?></td>
                            <td>
                                <!-- Manual stock adjustment -->
                                <form method="post" class="inline-adjust">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="number" name="new_stock" value="<?= $p['stock'] ?>" step="1">
                                    <button type="submit" name="adjust_stock" class="btn-small">Update</button>
                                </form>
                                <!-- Edge-AI demo -->
                                <form method="post" class="inline-adjust">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="edge_ai" class="btn-small" style="background:#ffcc00; color:#000;">📸 AI Count</button>
                                </form>
                             </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No products found. Add products from Products page.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #111; border-radius: 16px; border-left: 4px solid #ffcc00;">
            <h4>🤖 Edge‑AI Stock Counting</h4>
            <p>Click <strong>"📸 AI Count"</strong> on any product to simulate computer vision detection. In production, this connects to TensorFlow Lite for real object recognition – fully offline.</p>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const search = document.getElementById('searchInventory').value.toLowerCase();
    const filterType = document.getElementById('stockFilter').value;
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const matchesSearch = text.includes(search);
        let matchesFilter = true;
        const stockType = row.getAttribute('data-stock-type');
        if(filterType === 'low') matchesFilter = (stockType === 'low');
        else if(filterType === 'out') matchesFilter = (stockType === 'out');
        else if(filterType === 'expired') matchesFilter = (stockType === 'expired');
        
        row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
    });
}
</script>
</body>
</html>