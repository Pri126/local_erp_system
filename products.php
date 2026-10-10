<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Add Product
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $barcode = trim($_POST['barcode']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost']);
    $stock = intval($_POST['stock']);
    $min_stock = intval($_POST['min_stock']);
    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $unit = trim($_POST['unit']);
    
    if(empty($name) || $price <= 0) {
        $error = "Product name and valid price are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, barcode, category, price, cost, stock, min_stock, expiry_date, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddiiss", $name, $barcode, $category, $price, $cost, $stock, $min_stock, $expiry, $unit);
        if($stmt->execute()) {
            $success = "Product added successfully.";
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Edit Product
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $id = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $barcode = trim($_POST['barcode']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost']);
    $stock = intval($_POST['stock']);
    $min_stock = intval($_POST['min_stock']);
    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $unit = trim($_POST['unit']);
    
    $stmt = $conn->prepare("UPDATE products SET name=?, barcode=?, category=?, price=?, cost=?, stock=?, min_stock=?, expiry_date=?, unit=? WHERE id=?");
    $stmt->bind_param("sssddiissi", $name, $barcode, $category, $price, $cost, $stock, $min_stock, $expiry, $unit, $id);
    if($stmt->execute()) {
        $success = "Product updated successfully.";
    } else {
        $error = "Update failed.";
    }
    $stmt->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Product deleted.";

// Statistics
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$low_stock_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock <= min_stock")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock = 0")->fetch_assoc()['total'];
$total_value = $conn->query("SELECT SUM(stock * cost) as value FROM products")->fetch_assoc()['value'] ?? 0;
$expired_count = $conn->query("SELECT COUNT(*) as total FROM products WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()['total'];

// Product list
$products = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .product-stats {
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
        .form-group { flex: 1; min-width: 130px; }
        .form-group label { display: block; color: #aaa; font-size: 0.75rem; margin-bottom: 5px; }
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
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-yellow:hover { background: #ffdd33; transform: translateY(-2px); }
        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            padding: 10px;
            background: #222;
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid rgba(255,204,0,0.2); padding: 10px; text-align: left; }
        th { background: #1a1a1a; color: #ffcc00; }
        .low-stock { color: #ffcc00; font-weight: bold; }
        .edit-btn, .delete-btn { font-size: 0.8rem; padding: 4px 10px; border-radius: 8px; text-decoration: none; margin-right: 5px; }
        .edit-btn { background: #ffcc00; color: #000; }
        .delete-btn { background: rgba(198,40,40,0.2); color: #ff8888; border: 1px solid #c62828; }
        .edit-form-row { background: #1a1a1a; }
        .inline-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .inline-form input, .inline-form select { padding: 6px; background: #222; border: 1px solid #ffcc00; border-radius: 8px; color: #fff; }
        .inline-form button { background: #ffcc00; color: #000; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
        @media (max-width: 768px) {
            .product-stats { grid-template-columns: repeat(2,1fr); }
            .form-grid { flex-direction: column; }
            table { font-size: 0.75rem; }
            th, td { padding: 6px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>Products Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="product-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_products ?></div><div class="stat-label">📦 Total Products</div></div>
            <div class="stat-card"><div class="stat-number"><?= $low_stock_count ?></div><div class="stat-label">⚠️ Low Stock</div></div>
            <div class="stat-card"><div class="stat-number"><?= $out_of_stock ?></div><div class="stat-label">📭 Out of Stock</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_value, 0) ?></div><div class="stat-label">💰 Inventory Value</div></div>
            <div class="stat-card"><div class="stat-number"><?= $expired_count ?></div><div class="stat-label">⏰ Expired</div></div>
        </div>

        <!-- Add Product Form -->
        <div class="form-card">
            <h3>➕ Add New Product</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group"><label>Name *</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Barcode</label><input type="text" name="barcode"></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category"></div>
                    <div class="form-group"><label>Price *</label><input type="number" step="0.01" name="price" required></div>
                    <div class="form-group"><label>Cost</label><input type="number" step="0.01" name="cost"></div>
                    <div class="form-group"><label>Stock</label><input type="number" name="stock" value="0"></div>
                    <div class="form-group"><label>Min Stock</label><input type="number" name="min_stock" value="5"></div>
                    <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date"></div>
                    <div class="form-group"><label>Unit</label><input type="text" name="unit" placeholder="kg, pcs, liter"></div>
                    <div class="form-group"><button type="submit" name="add_product" class="btn-yellow">Add Product</button></div>
                </div>
            </form>
        </div>

        <!-- Search & Product List -->
        <h3>📋 Product Inventory</h3>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search by name or barcode..." onkeyup="filterProducts()">
        </div>
        <div style="overflow-x: auto;">
            <table id="productTable">
                <thead>
                    <tr><th>ID</th><th>Barcode</th><th>Name</th><th>Category</th><th>Price</th><th>Cost</th><th>Stock</th><th>Min</th><th>Expiry</th><th>Unit</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if($products->num_rows > 0): ?>
                    <?php while($p = $products->fetch_assoc()): 
                        $low = ($p['stock'] <= $p['min_stock']);
                        $expired = ($p['expiry_date'] && $p['expiry_date'] < date('Y-m-d'));
                    ?>
                        <tr id="row-<?= $p['id'] ?>">
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['barcode']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td>₹ <?= number_format($p['price'],2) ?></td>
                            <td>₹ <?= number_format($p['cost'],2) ?></td>
                            <td class="<?= $low ? 'low-stock' : '' ?>">
                                <?= $p['stock'] ?>
                                <?= $low ? '⚠️' : '' ?>
                                <?= $expired ? ' (Expired)' : '' ?>
                            </td>
                            <td><?= $p['min_stock'] ?></td>
                            <td><?= $p['expiry_date'] ?: '-' ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td>
                                <button onclick="showEditForm(<?= $p['id'] ?>)" class="edit-btn">Edit</button>
                                <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Delete this product?')" class="delete-btn">Delete</a>
                            </td>
                        </tr>
                        <tr id="edit-<?= $p['id'] ?>" style="display:none;" class="edit-form-row">
                            <td colspan="11">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                                    <input type="text" name="barcode" value="<?= htmlspecialchars($p['barcode']) ?>">
                                    <input type="text" name="category" value="<?= htmlspecialchars($p['category']) ?>">
                                    <input type="number" step="0.01" name="price" value="<?= $p['price'] ?>" required>
                                    <input type="number" step="0.01" name="cost" value="<?= $p['cost'] ?>">
                                    <input type="number" name="stock" value="<?= $p['stock'] ?>">
                                    <input type="number" name="min_stock" value="<?= $p['min_stock'] ?>">
                                    <input type="date" name="expiry_date" value="<?= $p['expiry_date'] ?>">
                                    <input type="text" name="unit" value="<?= htmlspecialchars($p['unit']) ?>">
                                    <button type="submit" name="edit_product">Update</button>
                                    <button type="button" onclick="hideEditForm(<?= $p['id'] ?>)">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="11" style="text-align:center;">No products found. Add your first product above.</td></tr>
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
function filterProducts() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let rows = document.querySelectorAll('#productTable tbody tr:not(.edit-form-row)');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>