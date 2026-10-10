<?php
require_once 'includes/functions.php';
if(!isLoggedIn()) redirect('login.php');

// Handle bulk barcode generation (selected products)
$selected_ids = [];
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_barcodes'])) {
    $selected_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$products_query = "SELECT id, name, barcode, price FROM products";
if($search) {
    $search = $conn->real_escape_string($search);
    $products_query .= " WHERE name LIKE '%$search%' OR barcode LIKE '%$search%'";
}
$products_query .= " ORDER BY name ASC";
$products = $conn->query($products_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Labels - Alpha Vision AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .label-container {
            padding: 20px;
        }
        .control-bar {
            background: #111;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,204,0,0.2);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .search-group {
            flex: 2;
            min-width: 200px;
        }
        .search-group label {
            display: block;
            color: #aaa;
            font-size: 0.75rem;
            margin-bottom: 5px;
        }
        .search-group input {
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
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .product-card {
            background: #111;
            border-radius: 16px;
            border: 1px solid rgba(255,204,0,0.2);
            padding: 15px;
            transition: 0.2s;
        }
        .product-card:hover { border-color: #ffcc00; }
        .barcode-preview {
            background: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
        .barcode-preview img {
            max-width: 100%;
            height: auto;
        }
        .product-info {
            margin-bottom: 15px;
        }
        .product-name {
            font-weight: 600;
            color: #ffcc00;
        }
        .product-price {
            color: #aaa;
            font-size: 0.9rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .bulk-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        .print-area {
            display: none;
        }
        @media print {
            .sidebar, .top-bar, .control-bar, .bulk-actions, .product-card input, .product-card label {
                display: none !important;
            }
            .product-card {
                break-inside: avoid;
                border: 1px solid #ccc;
                margin: 0;
                padding: 10px;
            }
            .print-area {
                display: block;
            }
            .product-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar"><h1>🏷️ Barcode Labels</h1></div>

        <!-- Control Bar -->
        <div class="control-bar">
            <form method="get" style="flex:2; display: flex; gap: 10px;">
                <div class="search-group" style="flex:1;">
                    <label>Search Product</label>
                    <input type="text" name="search" placeholder="Name or barcode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-yellow">🔍 Search</button>
                <a href="barcode_label.php" class="btn-yellow" style="text-decoration:none;">Reset</a>
            </form>
            <div>
                <label class="checkbox-label">
                    <input type="checkbox" id="selectAll"> Select All
                </label>
            </div>
            <button id="generateSelectedBtn" class="btn-yellow">🎯 Generate Selected</button>
            <button id="printLabelsBtn" class="btn-yellow">🖨️ Print Labels</button>
        </div>

        <!-- Product Grid with Barcode Previews -->
        <form method="post" id="barcodeForm">
            <div class="product-grid" id="productGrid">
                <?php if($products->num_rows > 0): ?>
                    <?php while($p = $products->fetch_assoc()): 
                        $barcode_url = "barcode_img.php?barcode=" . urlencode($p['barcode'] ?: $p['id']);
                    ?>
                        <div class="product-card" data-product-id="<?= $p['id'] ?>">
                            <div class="barcode-preview">
                                <img src="<?= $barcode_url ?>" alt="Barcode">
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="product-price">Price: ₹ <?= number_format($p['price'], 2) ?></div>
                                <div class="product-price">Barcode: <?= htmlspecialchars($p['barcode'] ?: $p['id']) ?></div>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox" name="product_ids[]" value="<?= $p['id'] ?>" class="product-checkbox">
                                Include in bulk print
                            </label>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column:1/-1; text-align:center; color:#aaa;">No products found.</div>
                <?php endif; ?>
            </div>
            <input type="hidden" name="generate_barcodes" value="1" id="generateHidden">
        </form>

        <!-- Hidden print area for bulk generated labels -->
        <div id="printArea" class="print-area">
            <div class="product-grid" id="printGrid"></div>
        </div>

        <?php if($selected_ids): ?>
            <div style="margin-top: 20px; background:#111; border-radius:16px; padding:15px;">
                <h3>📋 Generated Labels (Selected Products)</h3>
                <div class="product-grid">
                    <?php
                    $ids_str = implode(',', array_map('intval', $selected_ids));
                    $selected_products = $conn->query("SELECT id, name, barcode, price FROM products WHERE id IN ($ids_str)");
                    while($sp = $selected_products->fetch_assoc()):
                        $barcode_url = "barcode_img.php?barcode=" . urlencode($sp['barcode'] ?: $sp['id']);
                    ?>
                        <div class="product-card">
                            <div class="barcode-preview">
                                <img src="<?= $barcode_url ?>" alt="Barcode">
                            </div>
                            <div class="product-name"><?= htmlspecialchars($sp['name']) ?></div>
                            <div class="product-price">₹ <?= number_format($sp['price'], 2) ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <button onclick="window.print()" class="btn-yellow" style="margin-top:15px;">🖨️ Print These Labels</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Select All checkbox
const selectAllCheckbox = document.getElementById('selectAll');
const productCheckboxes = document.querySelectorAll('.product-checkbox');
selectAllCheckbox.addEventListener('change', function() {
    productCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
});

// Generate Selected button
document.getElementById('generateSelectedBtn').addEventListener('click', function(e) {
    const checked = Array.from(productCheckboxes).some(cb => cb.checked);
    if(!checked) {
        alert('Please select at least one product.');
        return;
    }
    document.getElementById('barcodeForm').submit();
});

// Print Labels button: generates a printer-friendly version of selected products
document.getElementById('printLabelsBtn').addEventListener('click', function() {
    const selected = Array.from(productCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
    if(selected.length === 0) {
        alert('Select products to print.');
        return;
    }
    fetch('get_barcode_labels.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_ids=' + JSON.stringify(selected)
    })
    .then(response => response.text())
    .then(html => {
        const printArea = document.getElementById('printArea');
        printArea.innerHTML = html;
        // Wait for images to load then print
        const imgs = printArea.querySelectorAll('img');
        let loaded = 0;
        imgs.forEach(img => {
            if(img.complete) loaded++;
            else img.onload = () => { loaded++; if(loaded === imgs.length) printAndHide(); };
        });
        if(imgs.length === 0) printAndHide();
        function printAndHide() {
            window.print();
            printArea.innerHTML = ''; // clear after print
        }
    });
});

// Helper to refresh barcode images if needed (optional)
function refreshBarcodeImages() {
    document.querySelectorAll('.barcode-preview img').forEach(img => {
        img.src = img.src.split('?')[0] + '?_=' + Date.now();
    });
}
</script>
</body>
</html>