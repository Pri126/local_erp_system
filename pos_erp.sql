-- ============================================
-- ALPHA VISION AI - Local ERP & POS System
-- Complete Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS pos_erp;
USE pos_erp;

-- 1. Users (Authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin','cashier') DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin, password=admin123 (hashed using password_hash)
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- 2. Products (Inventory)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(100) UNIQUE,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2),
    stock INT DEFAULT 0,
    min_stock INT DEFAULT 5,
    expiry_date DATE NULL,
    unit VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Products
INSERT INTO products (barcode, name, category, price, cost, stock, min_stock) VALUES
('5012345678900', 'Sugar 1kg', 'Grocery', 80.00, 70.00, 50, 10),
('5012345678901', 'Milk 1L', 'Dairy', 60.00, 50.00, 30, 5),
('5012345678902', 'Rice 5kg', 'Grocery', 350.00, 320.00, 20, 5),
('5012345678903', 'Cooking Oil 1L', 'Grocery', 220.00, 200.00, 15, 3);

-- 3. Customers (Khata)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE,
    credit_limit DECIMAL(10,2) DEFAULT 0,
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO customers (name, phone, credit_limit) VALUES
('Ahmed Traders', '03001234567', 5000.00),
('Fatima Store', '03007654321', 3000.00);

-- 4. Khata Ledger
CREATE TABLE khata_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','payment') NOT NULL,
    reference_id INT,
    note TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Sample transactions
INSERT INTO khata_transactions (customer_id, amount, type, note) VALUES
(1, 1500.00, 'credit', 'Initial purchase'),
(2, 800.00, 'credit', 'Goods sold on credit');

-- 5. Sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE,
    customer_id INT NULL,
    total DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(10,2),
    payment_method ENUM('cash','card','khata'),
    amount_paid DECIMAL(10,2),
    cashier_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- 6. Sale Items
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity INT,
    price DECIMAL(10,2),
    total DECIMAL(10,2),
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 7. Suppliers
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO suppliers (name, contact_person, phone) VALUES
('Best Wholesalers', 'Ali Raza', '0421234567'),
('Fresh Foods Ltd', 'Sara Khan', '0427654321');

-- 8. Purchase Orders
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_no VARCHAR(50) UNIQUE,
    supplier_id INT,
    order_date DATE DEFAULT CURDATE(),
    total_amount DECIMAL(10,2),
    status ENUM('pending','received','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- 9. Purchase Order Items
CREATE TABLE po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT,
    product_id INT,
    quantity INT,
    unit_price DECIMAL(10,2),
    total DECIMAL(10,2),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 10. Cash Sessions (Drawer Tracking)
CREATE TABLE cash_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    opening_balance DECIMAL(10,2) DEFAULT 0,
    closing_balance DECIMAL(10,2),
    expected_amount DECIMAL(10,2),
    status ENUM('open','closed') DEFAULT 'open',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 11. Staff
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    wage_type ENUM('hourly','daily','monthly') DEFAULT 'daily',
    wage_rate DECIMAL(10,2),
    phone VARCHAR(20),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO staff (name, position, wage_type, wage_rate, phone) VALUES
('Usman Ahmad', 'Cashier', 'daily', 800.00, '03111222333'),
('Nadia Khan', 'Sales Assistant', 'hourly', 150.00, '03114445566');

-- 12. Attendance
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    date DATE,
    hours_worked DECIMAL(5,2),
    status ENUM('present','absent','late') DEFAULT 'present',
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- 13. Payroll
CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    month_year DATE,  -- first day of month
    total_hours DECIMAL(5,2),
    total_days INT,
    gross_salary DECIMAL(10,2),
    deductions DECIMAL(10,2),
    net_salary DECIMAL(10,2),
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);

-- 14. Returns
CREATE TABLE returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity INT,
    reason TEXT,
    returned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 15. Settings (for configuration)
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    value TEXT
);

-- Default settings
INSERT INTO settings (setting_key, value) VALUES
('shop_name', 'Alpha Vision AI Store'),
('gst_rate', '5'),
('discount_approval_pin', '1234');

-- 16. Marketing Log (optional, for WhatsApp/SMS tracking)
CREATE TABLE marketing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- ============================================
-- End of Schema
-- ============================================