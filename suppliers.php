<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Handle Add Supplier
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    
    if(empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $contact, $phone, $email, $address);
        if($stmt->execute()) {
            $success = "Supplier added successfully.";
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Edit Supplier
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_supplier'])) {
    $id = intval($_POST['supplier_id']);
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    
    $stmt = $conn->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $contact, $phone, $email, $address, $id);
    if($stmt->execute()) {
        $success = "Supplier updated successfully.";
    } else {
        $error = "Update failed.";
    }
    $stmt->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM suppliers WHERE id = $id");
    header("Location: suppliers.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Supplier deleted.";

// Statistics
$total_suppliers = $conn->query("SELECT COUNT(*) as total FROM suppliers")->fetch_assoc()['total'];
$total_pos = $conn->query("SELECT COUNT(*) as total FROM purchase_orders")->fetch_assoc()['total'];
$pending_pos = $conn->query("SELECT COUNT(*) as total FROM purchase_orders WHERE status='pending'")->fetch_assoc()['total'];
$total_po_value = $conn->query("SELECT SUM(total_amount) as total FROM purchase_orders")->fetch_assoc()['total'] ?? 0;

$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Suppliers - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .supplier-stats {
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
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; color: #aaa; font-size: 0.75rem; margin-bottom: 5px; }
        .form-group input, .form-group textarea {
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
        .supplier-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .supplier-card {
            background: #111;
            border-radius: 20px;
            border: 1px solid rgba(255,204,0,0.2);
            padding: 20px;
            transition: 0.2s;
        }
        .supplier-card:hover { border-color: #ffcc00; }
        .supplier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .supplier-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #ffcc00;
        }
        .supplier-contact {
            color: #aaa;
            font-size: 0.9rem;
        }
        .supplier-actions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .btn-sm {
            background: #222;
            border: 1px solid #ffcc00;
            color: #ffcc00;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
        }
        .btn-sm:hover { background: #ffcc00; color: #000; }
        .edit-form {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .inline-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        .inline-form input, .inline-form textarea {
            padding: 8px;
            background: #222;
            border: 1px solid #ffcc00;
            border-radius: 12px;
            color: #fff;
            font-size: 0.85rem;
        }
        @media (max-width: 768px) {
            .supplier-stats { grid-template-columns: repeat(2,1fr); }
            .supplier-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .inline-form { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>🚚 Suppliers Management</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="supplier-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_suppliers ?></div><div class="stat-label">📦 Total Suppliers</div></div>
            <div class="stat-card"><div class="stat-number"><?= $total_pos ?></div><div class="stat-label">📄 Purchase Orders</div></div>
            <div class="stat-card"><div class="stat-number"><?= $pending_pos ?></div><div class="stat-label">⏳ Pending POs</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_po_value, 0) ?></div><div class="stat-label">💰 Total PO Value</div></div>
        </div>

        <!-- Add Supplier Form -->
        <div class="form-card">
            <h3>➕ Add New Supplier</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group"><label>Supplier Name *</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person"></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                    <div class="form-group"><label>Address</label><textarea name="address" rows="1"></textarea></div>
                    <div class="form-group"><button type="submit" name="add_supplier" class="btn-yellow">Add Supplier</button></div>
                </div>
            </form>
        </div>

        <!-- Suppliers List -->
        <h3>📋 Supplier Directory</h3>
        <div class="supplier-list">
            <?php if($suppliers->num_rows > 0): ?>
                <?php while($s = $suppliers->fetch_assoc()): ?>
                    <div class="supplier-card" id="supplier-<?= $s['id'] ?>">
                        <div class="supplier-header">
                            <div>
                                <div class="supplier-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="supplier-contact"><?= htmlspecialchars($s['contact_person']) ?: 'No contact person' ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div>📞 <?= htmlspecialchars($s['phone'] ?: '-') ?></div>
                                <div>✉️ <?= htmlspecialchars($s['email'] ?: '-') ?></div>
                            </div>
                        </div>
                        <div class="supplier-actions">
                            <button class="btn-sm" onclick="toggleEditForm(<?= $s['id'] ?>)">✏️ Edit</button>
                            <a href="purchase_orders.php?supplier=<?= $s['id'] ?>" class="btn-sm">📄 View POs</a>
                            <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this supplier?')" class="btn-sm">🗑️ Delete</a>
                        </div>
                        <div id="edit-form-<?= $s['id'] ?>" class="edit-form">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                                <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
                                <input type="text" name="contact_person" value="<?= htmlspecialchars($s['contact_person']) ?>" placeholder="Contact Person">
                                <input type="text" name="phone" value="<?= htmlspecialchars($s['phone']) ?>" placeholder="Phone">
                                <input type="email" name="email" value="<?= htmlspecialchars($s['email']) ?>" placeholder="Email">
                                <textarea name="address" placeholder="Address" rows="1"><?= htmlspecialchars($s['address']) ?></textarea>
                                <button type="submit" name="edit_supplier" class="btn-yellow">Update</button>
                                <button type="button" onclick="toggleEditForm(<?= $s['id'] ?>)" class="btn-sm">Cancel</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding:30px; background:#111; border-radius:20px;">No suppliers found. Add your first supplier above.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleEditForm(id) {
    const formDiv = document.getElementById('edit-form-' + id);
    if(formDiv.style.display === 'none' || formDiv.style.display === '') {
        formDiv.style.display = 'block';
    } else {
        formDiv.style.display = 'none';
    }
}
</script>
</body>
</html>