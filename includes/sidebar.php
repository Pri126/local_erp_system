<div class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/logo.svg" alt="Logo" width="36" height="36">
        <div class="logo-text">
            <span class="logo-title">Alpha Vision AI</span>
            <span class="logo-subtitle">ERP & POS</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul>
            <li><a href="index.php"><span class="menu-icon">🏠</span><span class="menu-text">Dashboard</span></a></li>
            <li><a href="pos.php"><span class="menu-icon">🛒</span><span class="menu-text">POS Billing</span></a></li>
            <li><a href="products.php"><span class="menu-icon">📦</span><span class="menu-text">Products</span></a></li>
            <li><a href="inventory.php"><span class="menu-icon">📊</span><span class="menu-text">Inventory</span></a></li>
            <li><a href="customers.php"><span class="menu-icon">👥</span><span class="menu-text">Customers (Khata)</span></a></li>
            <li><a href="suppliers.php"><span class="menu-icon">🚚</span><span class="menu-text">Suppliers</span></a></li>
            <li><a href="purchase_orders.php"><span class="menu-icon">📄</span><span class="menu-text">Purchase Orders</span></a></li>
            <li><a href="cash_management.php"><span class="menu-icon">💵</span><span class="menu-text">Cash Management</span></a></li>
            <li><a href="staff.php"><span class="menu-icon">👔</span><span class="menu-text">Staff</span></a></li>
            <li><a href="attendance.php"><span class="menu-icon">📅</span><span class="menu-text">Attendance</span></a></li>
            <li><a href="payroll.php"><span class="menu-icon">💰</span><span class="menu-text">Payroll</span></a></li>
            <li><a href="returns.php"><span class="menu-icon">↩️</span><span class="menu-text">Returns</span></a></li>
            <li><a href="reports.php"><span class="menu-icon">📈</span><span class="menu-text">Reports</span></a></li>
            <li><a href="ai_insights.php"><span class="menu-icon">🤖</span><span class="menu-text">AI Insights</span></a></li>
            <li><a href="barcode_label.php"><span class="menu-icon">🏷️</span><span class="menu-text">Barcode Label</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <span class="user-icon">👤</span>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
        </div>
        <a href="logout.php" class="logout-btn">
            <span>🚪</span> <span>Logout</span>
        </a>
    </div>
</div>

<style>
/* Force reset for sidebar – no overlap */
.sidebar {
    width: 220px !important;
    background: linear-gradient(180deg, #0a0a0a 0%, #111111 100%);
    height: 100vh;
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    overflow-y: auto;
    border-right: 1px solid rgba(255, 204, 0, 0.2);
    z-index: 999;
    display: flex;
    flex-direction: column;
}

/* Scrollbar */
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: #1a1a1a; }
.sidebar::-webkit-scrollbar-thumb { background: #ffcc00; border-radius: 10px; }

/* Header */
.sidebar-header {
    padding: 20px 12px;
    border-bottom: 1px solid rgba(255, 204, 0, 0.2);
    display: flex;
    align-items: center;
    gap: 8px;
}
.logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #ffcc00, #ffaa00);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: bold;
    color: #000;
}
.logo-text { display: flex; flex-direction: column; }
.logo-title { font-size: 14px; font-weight: 700; color: #ffcc00; }
.logo-subtitle { font-size: 9px; color: #888; }

/* Menu */
.sidebar-menu { flex: 1; padding: 0 8px; }
.sidebar-menu ul { list-style: none; padding: 0; margin: 0; }
.sidebar-menu ul li { margin-bottom: 2px; }
.sidebar-menu ul li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    color: #ccc;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 13px;
}
.sidebar-menu ul li a:hover {
    background: rgba(255, 204, 0, 0.1);
    color: #ffcc00;
}
.sidebar-menu ul li a .menu-icon { font-size: 16px; min-width: 22px; }
.sidebar-menu ul li a .menu-text { flex: 1; }

/* Active link */
.sidebar-menu ul li a.active {
    background: rgba(255, 204, 0, 0.15);
    color: #ffcc00;
    border-left: 3px solid #ffcc00;
}

/* Footer */
.sidebar-footer {
    padding: 12px;
    border-top: 1px solid rgba(255, 204, 0, 0.2);
}
.user-info {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    margin-bottom: 8px;
}
.user-name { font-size: 12px; font-weight: 500; color: #ffcc00; }
.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 10px;
    background: rgba(255, 204, 0, 0.1);
    border-radius: 8px;
    text-decoration: none;
    color: #ffcc00;
    font-size: 12px;
    font-weight: 500;
}
.logout-btn:hover { background: #ffcc00; color: #000; }

/* Responsive: collapse on mobile */
@media (max-width: 768px) {
    .sidebar { width: 65px !important; }
    .sidebar .logo-text, 
    .sidebar .menu-text, 
    .sidebar .user-name,
    .sidebar .logout-btn span:last-child { display: none; }
    .sidebar-header { justify-content: center; }
    .logo-icon { margin: 0 auto; }
    .sidebar-menu ul li a { justify-content: center; }
    .menu-icon { margin: 0; }
    .user-info { justify-content: center; }
    .logout-btn { justify-content: center; }
}
</style>

<script>
// Auto-add active class based on current page
document.addEventListener('DOMContentLoaded', function() {
    const current = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-menu ul li a').forEach(link => {
        if(link.getAttribute('href') === current) {
            link.classList.add('active');
        }
    });
});
</script>