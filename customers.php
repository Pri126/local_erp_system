<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

$success = '';
$error = '';

// Add customer
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $credit_limit = floatval($_POST['credit_limit']);
    if(empty($name) || empty($phone)) {
        $error = "Name and phone are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (name, phone, credit_limit) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $name, $phone, $credit_limit);
        if($stmt->execute()) {
            $success = "Customer added successfully.";
        } else {
            $error = "Phone number may already exist.";
        }
        $stmt->close();
    }
}

// Add transaction (credit/payment)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transaction'])) {
    $cid = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $type = $_POST['type'];
    $note = trim($_POST['note']);
    if(addKhataTransaction($cid, $amount, $type, null, $note)) {
        $success = "Transaction recorded.";
    } else {
        $error = "Transaction failed.";
    }
}

// Send offer (marketing bot)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_offer'])) {
    $cid = intval($_POST['customer_id']);
    $customer = $conn->query("SELECT name, phone FROM customers WHERE id=$cid")->fetch_assoc();
    $offer_msg = "Special offer for you! 10% discount on your next purchase. - Alpha Vision AI";
    file_put_contents("marketing_log.txt", date('Y-m-d H:i:s')." - To {$customer['phone']}: $offer_msg\n", FILE_APPEND);
    $success = "Offer sent to {$customer['name']} (logged).";
}

// Delete customer
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM customers WHERE id = $id");
    header("Location: customers.php?deleted=1");
    exit;
}
if(isset($_GET['deleted'])) $success = "Customer deleted.";

// Statistics
$total_customers = $conn->query("SELECT COUNT(*) as total FROM customers")->fetch_assoc()['total'];
$total_khata = $conn->query("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as due FROM khata_transactions")->fetch_assoc()['due'] ?? 0;
$high_risk = 0;
$customers_list = $conn->query("SELECT * FROM customers ORDER BY id DESC");
$temp_balances = [];
foreach($customers_list as $c) {
    $balance = getCustomerBalance($c['id']);
    $temp_balances[$c['id']] = $balance;
    if($balance > 5000) $high_risk++;
}
$customers_list->data_seek(0); // reset pointer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers - Khata Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .khata-stats {
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
        .form-group input, .form-group select {
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
        .customer-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .customer-card {
            background: #111;
            border-radius: 20px;
            border: 1px solid rgba(255,204,0,0.2);
            padding: 20px;
            transition: 0.2s;
        }
        .customer-card:hover { border-color: #ffcc00; }
        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .customer-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #ffcc00;
        }
        .customer-phone {
            color: #aaa;
            font-size: 0.9rem;
        }
        .customer-balance {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ffcc00;
        }
        .risk-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .risk-high { background: #c62828; color: white; }
        .risk-medium { background: #ff8f00; color: black; }
        .risk-low { background: #2e7d32; color: white; }
        .customer-actions {
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
        }
        .btn-sm:hover { background: #ffcc00; color: #000; }
        .transaction-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .transaction-form input, .transaction-form select {
            padding: 8px;
            background: #222;
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #111;
            border-radius: 24px;
            padding: 20px;
            max-width: 500px;
            width: 90%;
            border: 1px solid #ffcc00;
            max-height: 80%;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #ffcc00;
        }
        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
        }
        .history-item {
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }
        @media (max-width: 768px) {
            .khata-stats { grid-template-columns: repeat(2,1fr); }
            .customer-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>👥 Customers & Khata</h1></div>

        <?php if($success): ?>
            <div class="message success" style="background:#2e7d32; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message error" style="background:#c62828; color:white; padding:12px; border-radius:12px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="khata-stats">
            <div class="stat-card"><div class="stat-number"><?= $total_customers ?></div><div class="stat-label">👥 Total Customers</div></div>
            <div class="stat-card"><div class="stat-number">₹ <?= number_format($total_khata, 2) ?></div><div class="stat-label">💰 Total Khata Due</div></div>
            <div class="stat-card"><div class="stat-number"><?= $high_risk ?></div><div class="stat-label">⚠️ High Risk Customers</div></div>
        </div>

        <!-- Add Customer Form -->
        <div class="form-card">
            <h3>➕ Add New Customer</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Phone *</label><input type="text" name="phone" required></div>
                    <div class="form-group"><label>Credit Limit (₹)</label><input type="number" step="0.01" name="credit_limit" value="0"></div>
                    <div class="form-group"><button type="submit" name="add_customer" class="btn-yellow">Add Customer</button></div>
                </div>
            </form>
        </div>

        <!-- Customers List -->
        <h3>📋 Customer Khata Ledger</h3>
        <div class="customer-list">
            <?php while($c = $customers_list->fetch_assoc()): 
                $balance = $temp_balances[$c['id']];
                $risk = getCreditRiskScore($c['id']);
                $risk_class = ($risk == 'High Risk') ? 'risk-high' : (($risk == 'Medium Risk') ? 'risk-medium' : 'risk-low');
            ?>
                <div class="customer-card" id="customer-<?= $c['id'] ?>">
                    <div class="customer-header">
                        <div>
                            <div class="customer-name"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="customer-phone">📞 <?= htmlspecialchars($c['phone']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div class="customer-balance">₹ <?= number_format($balance, 2) ?></div>
                            <div><span class="risk-badge <?= $risk_class ?>"><?= $risk ?></span></div>
                            <div class="stat-label">Limit: ₹ <?= number_format($c['credit_limit'], 2) ?></div>
                        </div>
                    </div>
                    <div class="customer-actions">
                        <button class="btn-sm" onclick="showTransactionForm(<?= $c['id'] ?>)">💸 Add Transaction</button>
                        <button class="btn-sm" onclick="showHistory(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">📜 History</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                            <button type="submit" name="send_offer" class="btn-sm">📱 Send Offer</button>
                        </form>
                        <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('Delete customer? All transactions will be lost.')" class="btn-sm" style="text-decoration:none;">🗑️ Delete</a>
                    </div>
                    <div id="transaction-form-<?= $c['id'] ?>" style="display:none;">
                        <form method="post" class="transaction-form">
                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                            <input type="number" name="amount" placeholder="Amount" step="0.01" required>
                            <select name="type">
                                <option value="credit">Credit (Customer owes)</option>
                                <option value="payment">Payment (Customer paid)</option>
                            </select>
                            <input type="text" name="note" placeholder="Note (optional)">
                            <button type="submit" name="add_transaction" class="btn-yellow">Submit</button>
                            <button type="button" onclick="hideTransactionForm(<?= $c['id'] ?>)" class="btn-sm">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Transaction History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Transaction History</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div id="historyList"></div>
    </div>
</div>

<script>
function showTransactionForm(id) {
    const formDiv = document.getElementById('transaction-form-' + id);
    if(formDiv.style.display === 'none') formDiv.style.display = 'block';
    else formDiv.style.display = 'none';
}
function hideTransactionForm(id) {
    document.getElementById('transaction-form-' + id).style.display = 'none';
}
function showHistory(customerId, customerName) {
    fetch(`get_transactions.php?customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('historyModal');
            document.getElementById('modalTitle').innerText = `History - ${customerName}`;
            const listDiv = document.getElementById('historyList');
            if(data.length === 0) {
                listDiv.innerHTML = '<div style="text-align:center;color:#aaa;">No transactions yet.</div>';
            } else {
                let html = '';
                data.forEach(t => {
                    const sign = (t.type === 'credit') ? '+' : '-';
                    const color = (t.type === 'credit') ? '#ffcc00' : '#4caf50';
                    html += `<div class="history-item">
                        <span>${t.transaction_date}</span>
                        <span style="color:${color};">${sign} ₹${Math.abs(t.amount).toFixed(2)}</span>
                        <span style="color:#aaa;">${t.note || ''}</span>
                    </div>`;
                });
                listDiv.innerHTML = html;
            }
            modal.style.display = 'flex';
        });
}
function closeModal() {
    document.getElementById('historyModal').style.display = 'none';
}
window.onclick = function(event) {
    if(event.target === document.getElementById('historyModal')) closeModal();
}
</script>
</body>
</html>