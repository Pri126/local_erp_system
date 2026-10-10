<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');
$cashier_id = $_SESSION['user_id'];

// Check if cash session is open (for non-admin)
if($_SESSION['role'] != 'admin') {
    $sessionCheck = $conn->prepare("SELECT id FROM cash_sessions WHERE user_id = ? AND status = 'open'");
    $sessionCheck->bind_param("i", $cashier_id);
    $sessionCheck->execute();
    if($sessionCheck->get_result()->num_rows == 0) {
        echo "<script>alert('Please open cash drawer first from Cash Management'); window.location.href='cash_management.php';</script>";
        exit;
    }
}

// Handle sale submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_sale'])) {
    $items = json_decode($_POST['cart'], true);
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : NULL;
    $payment_method = $_POST['payment_method'];
    $amount_paid = floatval($_POST['amount_paid']);
    $total = floatval($_POST['total']);
    $discount = floatval($_POST['discount']);
    $tax = floatval($_POST['tax']);
    $grand_total = floatval($_POST['grand_total']);
    
    $invoice_no = generateInvoiceNo();
    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, total, discount, tax, grand_total, payment_method, amount_paid, cashier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sidddssdi", $invoice_no, $customer_id, $total, $discount, $tax, $grand_total, $payment_method, $amount_paid, $cashier_id);
    
    if($stmt->execute()) {
        $sale_id = $conn->insert_id;
        foreach($items as $item) {
            $st = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
            $st->bind_param("iiidd", $sale_id, $item['id'], $item['qty'], $item['price'], $item['total']);
            $st->execute();
            $updateStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $updateStock->bind_param("ii", $item['qty'], $item['id']);
            $updateStock->execute();
        }
        if($payment_method == 'khata' && $customer_id) {
            addKhataTransaction($customer_id, $grand_total, 'credit', $sale_id, "Sale invoice $invoice_no");
        }
        if($payment_method == 'cash') {
            $updateCash = $conn->prepare("UPDATE cash_sessions SET closing_balance = COALESCE(closing_balance, 0) + ? WHERE user_id = ? AND status = 'open'");
            $updateCash->bind_param("di", $amount_paid, $cashier_id);
            $updateCash->execute();
        }
        echo "<script>alert('Sale completed! Invoice: $invoice_no'); window.location.href='pos.php';</script>";
        exit;
    } else {
        echo "<script>alert('Sale failed: " . $conn->error . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .pos-container {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .cart-section {
            flex: 1.2;
            min-width: 280px;
            background: #111;
            border-radius: 24px;
            border: 1px solid rgba(255,204,0,0.2);
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: fit-content;
        }
        .products-section {
            flex: 2;
            min-width: 300px;
        }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,204,0,0.3);
        }
        .cart-header h2 {
            color: #ffcc00;
            font-size: 1.5rem;
        }
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .cart-item-info {
            flex: 2;
        }
        .cart-item-name {
            font-weight: 500;
        }
        .cart-item-price {
            font-size: 0.8rem;
            color: #aaa;
        }
        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cart-item-qty button {
            background: #222;
            border: 1px solid #ffcc00;
            color: #ffcc00;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            cursor: pointer;
        }
        .cart-item-total {
            font-weight: 600;
            color: #ffcc00;
            min-width: 80px;
            text-align: right;
        }
        .cart-total {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 16px;
            margin: 15px 0;
            text-align: right;
        }
        .cart-total span {
            font-size: 1.2rem;
            color: #ffcc00;
            font-weight: bold;
        }
        .checkout-form {
            margin-top: 10px;
        }
        .checkout-form select, .checkout-form input {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            background: #222;
            border: 1px solid #333;
            border-radius: 14px;
            color: #fff;
        }
        .btn-complete {
            width: 100%;
            background: linear-gradient(135deg, #ffcc00, #ffaa00);
            color: #000;
            font-weight: bold;
            padding: 14px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,204,0,0.3);
        }
        .product-search {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .product-search input {
            flex: 1;
            padding: 14px;
            background: #222;
            border: 1px solid #333;
            border-radius: 30px;
            color: #fff;
            font-size: 1rem;
        }
        .voice-btn {
            background: #ffcc00;
            color: #000;
            border: none;
            border-radius: 30px;
            padding: 0 20px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            max-height: 550px;
            overflow-y: auto;
            padding: 5px;
        }
        .product-card {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(255,204,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-4px);
            border-color: #ffcc00;
            background: #222;
        }
        .product-name {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .product-price {
            color: #ffcc00;
            font-size: 1.1rem;
        }
        .badge {
            background: rgba(255,204,0,0.2);
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
            margin-top: 5px;
        }
        .clear-cart {
            background: #333;
            border: none;
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .pos-container { flex-direction: column; }
            .product-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <h1>Point of Sale</h1>
            <div>🏪 Quick & Smart Billing</div>
        </div>

        <div class="pos-container">
            <!-- Left: Cart -->
            <div class="cart-section">
                <div class="cart-header">
                    <h2>🛒 Current Cart</h2>
                    <button class="clear-cart" id="clearCartBtn">Clear All</button>
                </div>
                <div id="cart-items" class="cart-items">
                    <!-- dynamic cart items will appear here -->
                    <div style="text-align:center;color:#666;">Cart is empty. Add products from the right panel.</div>
                </div>
                <div class="cart-total">
                    Total: <span id="cart-total">₹0.00</span>
                </div>
                <form method="post" id="saleForm" class="checkout-form">
                    <input type="hidden" name="cart" id="cartJson">
                    <input type="hidden" name="submit_sale" value="1">
                    <select name="customer_id" id="customerSelect">
                        <option value="">Walk-in Customer</option>
                        <?php $cust = $conn->query("SELECT id, name FROM customers");
                        while($c = $cust->fetch_assoc()) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
                    </select>
                    <select name="payment_method" required>
                        <option value="cash">💵 Cash</option>
                        <option value="card">💳 Card</option>
                        <option value="khata">📝 Khata (Credit)</option>
                    </select>
                    <input type="number" name="amount_paid" id="amountPaid" placeholder="Amount Received" step="0.01">
                    <input type="hidden" name="total" id="totalInput">
                    <input type="hidden" name="discount" id="discountInput" value="0">
                    <input type="hidden" name="tax" id="taxInput" value="0">
                    <input type="hidden" name="grand_total" id="grandTotalInput">
                    <button type="submit" class="btn-complete">✅ Complete Sale</button>
                </form>
            </div>

            <!-- Right: Products -->
            <div class="products-section">
                <div class="product-search">
                    <input type="text" id="barcodeSearch" placeholder="🔍 Scan barcode or search product..." autofocus>
                    <button id="voiceBtn" class="voice-btn">🎤 Voice</button>
                </div>
                <div id="productGrid" class="product-grid">
                    <!-- dynamic product cards -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let products = <?php 
    $res = $conn->query("SELECT id, name, barcode, price, stock FROM products WHERE stock > 0 ORDER BY name");
    $prods = [];
    while($r = $res->fetch_assoc()) $prods[] = $r;
    echo json_encode($prods);
?>;

function renderCart() {
    const container = document.getElementById('cart-items');
    let total = 0;
    if(cart.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:#666;">Cart is empty. Add products from the right panel.</div>';
        document.getElementById('cart-total').innerText = '₹0.00';
        document.getElementById('totalInput').value = 0;
        document.getElementById('grandTotalInput').value = 0;
        document.getElementById('cartJson').value = JSON.stringify(cart);
        return;
    }
    let html = '';
    cart.forEach((item, idx) => {
        const itemTotal = item.qty * item.price;
        total += itemTotal;
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${escapeHtml(item.name)}</div>
                    <div class="cart-item-price">₹${item.price.toFixed(2)}</div>
                </div>
                <div class="cart-item-qty">
                    <button onclick="changeQty(${idx}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button onclick="changeQty(${idx}, 1)">+</button>
                </div>
                <div class="cart-item-total">₹${itemTotal.toFixed(2)}</div>
                <button onclick="removeItem(${idx})" style="background:none; border:none; color:#ff8888; font-size:1.2rem; cursor:pointer;">✕</button>
            </div>
        `;
    });
    container.innerHTML = html;
    document.getElementById('cart-total').innerText = `₹${total.toFixed(2)}`;
    document.getElementById('totalInput').value = total;
    document.getElementById('grandTotalInput').value = total;
    document.getElementById('cartJson').value = JSON.stringify(cart);
}

function changeQty(idx, delta) {
    let newQty = cart[idx].qty + delta;
    if(newQty < 1) return;
    cart[idx].qty = newQty;
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx,1);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function addProduct(product, qty=1) {
    let existing = cart.find(p => p.id == product.id);
    if(existing) existing.qty += qty;
    else cart.push({id: product.id, name: product.name, price: product.price, qty: qty, total: product.price * qty});
    renderCart();
}

function renderProducts(filterText = '') {
    const grid = document.getElementById('productGrid');
    const filtered = products.filter(p => p.name.toLowerCase().includes(filterText) || p.barcode.includes(filterText));
    if(filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center;color:#666;">No products found</div>';
        return;
    }
    let html = '';
    filtered.forEach(p => {
        html += `
            <div class="product-card" onclick="addProduct({id:${p.id}, name:'${escapeHtml(p.name)}', price:${p.price}})">
                <div class="product-name">${escapeHtml(p.name)}</div>
                <div class="product-price">₹${p.price.toFixed(2)}</div>
                <div class="badge">Stock: ${p.stock}</div>
            </div>
        `;
    });
    grid.innerHTML = html;
}

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

// Search & barcode
const searchInput = document.getElementById('barcodeSearch');
searchInput.addEventListener('keyup', function(e) {
    renderProducts(this.value.toLowerCase());
    if(e.key === 'Enter') {
        let found = products.find(p => p.barcode === this.value);
        if(found) addProduct(found);
        this.value = '';
        renderProducts('');
    }
});

// Voice AI
const voiceBtn = document.getElementById('voiceBtn');
const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
recognition.lang = 'ur-PK';
recognition.onresult = function(event) {
    let command = event.results[0][0].transcript.toLowerCase();
    let match = command.match(/add (\d+(\.\d+)?)\s*(kg|liter|pcs)?\s*(.+)/i);
    if(match) {
        let qty = parseFloat(match[1]);
        let productName = match[4];
        let found = products.find(p => p.name.toLowerCase().includes(productName));
        if(found) addProduct(found, qty);
        else alert("Product not found");
    } else alert("Command not recognized. Say 'Add 2kg Sugar'");
};
voiceBtn.onclick = () => recognition.start();

// Clear cart button
document.getElementById('clearCartBtn').addEventListener('click', clearCart);

// Initial render
renderProducts('');
</script>
</body>
</html>