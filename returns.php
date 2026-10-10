<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Process Return
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_return'])) {
    $sale_id = intval($_POST['sale_id']);
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $reason = trim($_POST['reason']);
    
    $sale_check = $conn->prepare("SELECT id FROM sales WHERE id = ?");
    $sale_check->bind_param("i", $sale_id);
    $sale_check->execute();
    if($sale_check->get_result()->num_rows == 0) {
        $error = "Sale ID not found.";
    } elseif($quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        $item_check = $conn->prepare("SELECT quantity FROM sale_items WHERE sale_id = ? AND product_id = ?");
        $item_check->bind_param("ii", $sale_id, $product_id);
        $item_check->execute();
        $sold_qty = $item_check->get_result()->fetch_assoc();
        
        if(!$sold_qty) {
            $error = "Product not found in this sale.";
        } elseif($sold_qty['quantity'] < $quantity) {
            $error = "Return quantity exceeds sold quantity.";
        } else {
            $stmt = $conn->prepare("INSERT INTO returns (sale_id, product_id, quantity, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $sale_id, $product_id, $quantity, $reason);
            if($stmt->execute()) {
                $update_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $update_stock->bind_param("ii", $quantity, $product_id);
                $update_stock->execute();
                $success = "Return processed. Stock restored for product.";
            } else {
                $error = "Database error.";
            }
            $stmt->close();
        }
        $item_check->close();
    }
    $sale_check->close();
}

// Handle Delete Return Record
if(isset($_GET['delete'])) {
    $return_id = intval($_GET['delete']);
    $conn->query("DELETE FROM returns WHERE id = $return_id");
    header("Location: returns.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Return record deleted.";

// Statistics
$total_returns = $conn->query("SELECT COUNT(*) as total FROM returns")->fetch_assoc()['total'];
$total_return_qty = $conn->query("SELECT SUM(quantity) as total FROM returns")->fetch_assoc()['total'] ?? 0;
$most_returned_product = $conn->query("
    SELECT p.name, SUM(r.quantity) as qty 
    FROM returns r 
    JOIN products p ON r.product_id = p.id 
    GROUP BY r.product_id 
    ORDER BY qty DESC LIMIT 1
")->fetch_assoc();

$returns = $conn->query("
    SELECT r.*, p.name as product_name, s.invoice_no
    FROM returns r 
    JOIN products p ON r.product_id = p.id 
    LEFT JOIN sales s ON r.sale_id = s.id
    ORDER BY r.returned_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Returns Management - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .returns-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 0.75rem;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
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
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-yellow:hover { background: #ffdd33; transform: translateY(-2px); }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid rgba(255,204,0,0.2);
            padding: 12px;
            text-align: left;
        }
        th {
            background: #1a1a1a;
            color: #ffcc00;
        }
        .delete-btn {
            color: #ffcc00;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            .returns-stats { grid-template-columns: repeat(2,1fr); }
            .form-grid { flex-direction: column; }
            th, td { padding: 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>↩️ Returns Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="returns-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_returns ?></div><div class="stat-label">📋 Total Returns</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_return_qty ?></div><div class="stat-label">📦 Units Returned</div></div>
            <div class="stat-card">
                <div class="stat-number"><?= htmlspecialchars($most_returned_product['name'] ?? '-') ?></div>
                <div class="stat-label">🏆 Most Returned</div>
                <?php if($most_returned_product): ?><div class="stat-label" style="font-size:0.7rem;"><?= $most_returned_product['qty'] ?> units</div><?php endif; ?>
            </div>
        </div>

        <!-- Process Return Form -->
        <div class="form-card">
            <h3>🔄 Process Customer Return</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Sale / Invoice ID</label>
                        <input type="number" name="sale_id" placeholder="Enter Sale ID" required>
                    </div>
                    <div class="form-group">
                        <label>Product</label>
                        <select name="product_id" required>
                            <option value="">Select Product</option>
                            <?php $products = $conn->query("SELECT id, name FROM products ORDER BY name");
                            while($p = $products->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" placeholder="Quantity" required>
                    </div>
                    <div class="form-group">
                        <label>Reason (optional)</label>
                        <textarea name="reason" rows="1" placeholder="Damaged, wrong item, etc."></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="process_return" class="btn-yellow">Process Return</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Return History -->
        <h3>📋 Return History</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Sale ID</th><th>Invoice</th><th>Product</th><th>Quantity</th><th>Reason</th><th>Returned At</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if($returns->num_rows > 0): ?>
                    <?php while($row = $returns->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['sale_id'] ?></td>
                            <td><?= htmlspecialchars($row['invoice_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><?= $row['returned_at'] ?></td>
                            <td><a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this return record?')" class="delete-btn">Delete</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No returns recorded yet. Process a return above.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>