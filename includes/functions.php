<?php
// includes/functions.php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function getSetting($key) {
    // For simplicity, return default. Can be extended to DB settings table.
    global $conn;
    $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()) return $row['value'];
    return null;
}

function generateInvoiceNo() {
    return 'INV-' . date('Ymd') . '-' . rand(1000,9999);
}

function generatePONo() {
    return 'PO-' . date('Ymd') . '-' . rand(1000,9999);
}

function addKhataTransaction($customer_id, $amount, $type, $reference_id = null, $note = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO khata_transactions (customer_id, amount, type, reference_id, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsis", $customer_id, $amount, $type, $reference_id, $note);
    return $stmt->execute();
}

function getCustomerBalance($customer_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance FROM khata_transactions WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row['balance'] ?? 0;
}

// Predictive Khata Scoring function
function getCreditRiskScore($customer_id) {
    $balance = getCustomerBalance($customer_id);
    // Get overdue days from last payment/credit
    global $conn;
    $stmt = $conn->prepare("SELECT MAX(transaction_date) as last_date FROM khata_transactions WHERE customer_id = ? AND type='credit'");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $lastCredit = $row['last_date'];
    $overdue = 0;
    if($lastCredit) {
        $overdue = max(0, (time() - strtotime($lastCredit)) / 86400);
    }
    if($balance > 5000 && $overdue > 30) return "High Risk";
    elseif($balance > 2000 && $overdue > 15) return "Medium Risk";
    else return "Low Risk";
}

// Conversational BI: natural language to SQL (improved)
function chatWithData($question) {
    global $conn;
    $question = strtolower(trim($question));
    
    // Sales queries
    if(strpos($question, 'total sales') !== false) {
        if(strpos($question, 'today') !== false) {
            $sql = "SELECT SUM(grand_total) as total FROM sales WHERE DATE(created_at) = CURDATE()";
        } elseif(strpos($question, 'yesterday') !== false) {
            $sql = "SELECT SUM(grand_total) as total FROM sales WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
        } elseif(strpos($question, 'last week') !== false) {
            $sql = "SELECT SUM(grand_total) as total FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif(strpos($question, 'this month') !== false) {
            $sql = "SELECT SUM(grand_total) as total FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        } else {
            $sql = "SELECT SUM(grand_total) as total FROM sales";
        }
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return "Total sales: Rs. " . number_format($row['total'] ?? 0, 2);
    }
    
    // Invoice count
    if(strpos($question, 'how many invoices') !== false || strpos($question, 'number of sales') !== false) {
        if(strpos($question, 'today') !== false) {
            $sql = "SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()";
        } elseif(strpos($question, 'this month') !== false) {
            $sql = "SELECT COUNT(*) as count FROM sales WHERE MONTH(created_at) = MONTH(CURDATE())";
        } else {
            $sql = "SELECT COUNT(*) as count FROM sales";
        }
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return "Number of invoices: " . $row['count'];
    }
    
    // Low stock
    if(strpos($question, 'low stock') !== false || strpos($question, 'stock alert') !== false) {
        $sql = "SELECT name, stock, min_stock FROM products WHERE stock <= min_stock LIMIT 10";
        $result = $conn->query($sql);
        if($result->num_rows > 0) {
            $list = "";
            while($row = $result->fetch_assoc()) {
                $list .= "• " . $row['name'] . " (Stock: " . $row['stock'] . ", Min: " . $row['min_stock'] . ")\n";
            }
            return "Low stock items:\n" . $list;
        } else {
            return "No products are low on stock.";
        }
    }
    
    // Top selling products
    if(strpos($question, 'top selling') !== false || strpos($question, 'best selling') !== false) {
        $sql = "SELECT p.name, SUM(si.quantity) as qty FROM sale_items si JOIN products p ON si.product_id = p.id GROUP BY p.id ORDER BY qty DESC LIMIT 5";
        $result = $conn->query($sql);
        if($result->num_rows > 0) {
            $list = "";
            while($row = $result->fetch_assoc()) {
                $list .= "• " . $row['name'] . " - " . $row['qty'] . " units\n";
            }
            return "Top selling products:\n" . $list;
        } else {
            return "No sales data available.";
        }
    }
    
    // Khata due
    if(strpos($question, 'khata due') !== false || strpos($question, 'total credit') !== false) {
        $sql = "SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as due FROM khata_transactions";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        return "Total Khata (credit) due: Rs. " . number_format($row['due'] ?? 0, 2);
    }
    
    // Fallback
    return "";
}
?>