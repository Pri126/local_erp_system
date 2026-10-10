<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Create PO
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_po'])) {
    $supplier_id = intval($_POST['supplier_id']);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];
    
    if($supplier_id <= 0 || empty($product_ids)) {
        $error = "Please select a supplier and at least one product.";
    } else {
        $total = 0;
        $items = [];
        foreach($product_ids as $idx => $pid) {
            $qty = intval($quantities[$idx]);
            $price = floatval($prices[$idx]);
            $total += $qty * $price;
            $items[] = ['pid'=>$pid, 'qty'=>$qty, 'price'=>$price];
        }
        
        $po_no = generatePONo();
        $stmt = $conn->prepare("INSERT INTO purchase_orders (po_no, supplier_id, total_amount) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $po_no, $supplier_id, $total);
        if($stmt->execute()) {
            $po_id = $conn->insert_id;
            foreach($items as $item) {
                $st = $conn->prepare("INSERT INTO po_items (po_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");
                $item_total = $item['qty'] * $item['price'];
                $st->bind_param("iiidd", $po_id, $item['pid'], $item['qty'], $item['price'], $item_total);
                $st->execute();
            }
            $success = "Purchase Order created: $po_no";
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Receive PO
if(isset($_GET['receive'])) {
    $po_id = intval($_GET['receive']);
    $conn->query("UPDATE purchase_orders SET status='received' WHERE id=$po_id");
    $items = $conn->query("SELECT product_id, quantity FROM po_items WHERE po_id=$po_id");
    while($item = $items->fetch_assoc()) {
        $conn->query("UPDATE products SET stock = stock + {$item['quantity']} WHERE id = {$item['product_id']}");
    }
    header("Location: purchase_orders.php?received=1");
    exit;
}
if(isset($_GET['received'])) $success = "PO received. Stock updated.";

// Statistics
$total_pos = $conn->query("SELECT COUNT(*) as total FROM purchase_orders")->fetch_assoc()['total'];
$pending_pos = $conn->query("SELECT COUNT(*) as total FROM purchase_orders WHERE status='pending'")->fetch_assoc()['total'];
$received_pos = $conn->query("SELECT COUNT(*) as total FROM purchase_orders WHERE status='received'")->fetch_assoc()['total'];
$total_value = $conn->query("SELECT SUM(total_amount) as total FROM purchase_orders")->fetch_assoc()['total'] ?? 0;

$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$products = $conn->query("SELECT id, name, price FROM products ORDER BY name");
$pos = $conn->query("SELECT po.*, s.name as supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Orders - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .po-stats {
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
        .po-item {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .po-item select, .po-item input {
            flex: 1;
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
        .btn-outline {
            background: transparent;
            border: 1px solid #ffcc00;
            color: #ffcc00;
            padding: 8px 16px;
            border-radius: 12px;
            cursor: pointer;
        }
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
        .status-pending {
            background: rgba(255,152,0,0.2);
            color: #ff9800;
            padding: 4px 12px;
            border-radius: 30px;
            display: inline-block;
            font-size: 0.8rem;
        }
        .status-received {
            background: rgba(76,175,80,0.2);
            color: #4caf50;
            padding: 4px 12px;
            border-radius: 30px;
            display: inline-block;
            font-size: 0.8rem;
        }
        .receive-btn {
            background: #ffcc00;
            color: #000;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
        }
        @media (max-width: 768px) {
            .po-stats { grid-template-columns: repeat(2,1fr); }
            .po-item { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>📄 Purchase Orders</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="po-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_pos ?></div><div class="stat-label">📋 Total POs</div></div>
            <div class="stat-card"><div class="stat-number"><?= $pending_pos ?></div><div class="stat-label">⏳ Pending</div></div>
            <div class="stat-card"><div class="stat-number"><?= $received_pos ?></div><div class="stat-label">✅ Received</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_value, 0) ?></div><div class="stat-label">💰 Total Value</div></div>
        </div>

        <!-- Create PO Form -->
        <div class="form-card">
            <h3>➕ Create New Purchase Order</h3>
            <form method="post" id="poForm">
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px;">Select Supplier</label>
                    <select name="supplier_id" required style="width:100%; padding:12px; background:#222; border:1px solid #333; border-radius:12px;">
                        <option value="">-- Choose Supplier --</option>
                        <?php while($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div id="poItemsContainer">
                    <div class="po-item" data-index="0">
                        <select name="product_id[]" required>
                            <option value="">Select Product</option>
                            <?php $products->data_seek(0); while($p = $products->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (₹<?= $p['price'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" name="qty[]" placeholder="Quantity" required>
                        <input type="number" step="0.01" name="price[]" placeholder="Unit Price" required>
                        <button type="button" class="btn-outline" onclick="removePoItem(this)" style="background:#c62828; border-color:#c62828; color:white;">Remove</button>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="button" class="btn-outline" onclick="addPoItem()">+ Add Another Product</button>
                    <button type="submit" name="create_po" class="btn-yellow">Create Purchase Order</button>
                </div>
            </form>
        </div>

        <!-- List of POs -->
        <h3>📋 Purchase Order History</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>PO #</th><th>Supplier</th><th>Total Amount</th><th>Status</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if($pos->num_rows > 0): ?>
                    <?php while($po = $pos->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($po['po_no']) ?></strong></td>
                            <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                            <td>₹ <?= number_format($po['total_amount'], 2) ?></td>
                            <td><span class="status-<?= $po['status'] ?>"><?= ucfirst($po['status']) ?></span></td>
                            <td><?= date('d-m-Y H:i', strtotime($po['created_at'])) ?></td>
                            <td>
                                <?php if($po['status'] == 'pending'): ?>
                                    <a href="?receive=<?= $po['id'] ?>" onclick="return confirm('Receive this PO and update stock?')" class="receive-btn">📦 Receive Stock</a>
                                <?php else: ?>
                                    <span style="color:#4caf50;">✓ Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No purchase orders found. Create one above.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function addPoItem() {
    const container = document.getElementById('poItemsContainer');
    const original = container.querySelector('.po-item');
    const clone = original.cloneNode(true);
    // Clear values
    clone.querySelector('select').value = '';
    clone.querySelector('input[name="qty[]"]').value = '';
    clone.querySelector('input[name="price[]"]').value = '';
    // Remove remove button event? It will be attached via onclick attr.
    container.appendChild(clone);
}
function removePoItem(btn) {
    const container = document.getElementById('poItemsContainer');
    if(container.querySelectorAll('.po-item').length > 1) {
        btn.closest('.po-item').remove();
    } else {
        alert("At least one product is required.");
    }
}
</script>
</body>
</html>