# Local ERP System

A web-based Enterprise Resource Planning (ERP) system developed using Core PHP and MySQL to manage business operations through a centralized application.

## Overview

The Local ERP System is designed to simplify business management by providing modules for inventory, products, customers, suppliers, sales, purchases, staff, attendance, payroll, and reporting.

## Features

- **Dashboard** — Access the main system interface.
- **Point of Sale (POS)** — Manage sales transactions.
- **Product Management** — Maintain product records.
- **Inventory Management** — Manage stock and inventory information.
- **Customer Management** — Maintain customer records.
- **Supplier Management** — Manage supplier information.
- **Purchase Orders** — Manage purchase orders.
- **Returns Management** — Handle product returns.
- **Cash Management** — Manage cash-related operations.
- **Staff Management** — Maintain staff records.
- **Attendance Management** — Manage staff attendance.
- **Payroll Management** — Manage payroll information.
- **Reports** — View business reports.
- **AI Insights** — Access the project's AI insights module.
- **Barcode Labels** — Generate product barcode labels.

## Technologies Used

- PHP (Core PHP)
- MySQL
- HTML5
- CSS3
- JavaScript
- Bootstrap / CSS framework (if used in the project)
- XAMPP
- Git and GitHub

## Requirements

- XAMPP or another PHP development environment
- PHP
- MySQL or MariaDB
- A web browser

## Installation and Setup

### 1. Clone the Repository

```bash
git clone https://github.com/Pri126/local_erp_system.git
```

### 2. Move the Project

Place the project folder inside your XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\erp_system
```

### 3. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the Database

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `pos_erp`.
3. Import the `pos_erp.sql` file from the project folder.

### 5. Configure the Database

Configure your local database connection in `config/database.php` with your own local MySQL credentials.

Do not publish real passwords or production database credentials.

### 6. Run the Project

Open the following URL in your browser:

```text
http://localhost/erp_system/
```

## Project Structure

```text
erp_system/
├── assets/
│   ├── css/
│   └── images/
├── config/
│   └── database.php
├── includes/
├── sql/
│   └── pos_erp.sql
├── index.php
├── login.php
├── logout.php
├── products.php
├── inventory.php
├── customers.php
├── suppliers.php
├── pos.php
├── payroll.php
├── attendance.php
├── reports.php
├── purchase_orders.php
├── returns.php
├── staff.php
├── cash_management.php
├── barcode_label.php
└── README.md
```

## Database

The project uses MySQL for storing application data. The database setup script is provided in `pos_erp.sql`.

## Security Notes

- Keep database credentials private.
- Do not commit real customer, staff, or business data.
- Use secure passwords and validate user input.
- Configure production credentials separately from local development settings.

## Author

**Priya Devi**

GitHub: [Pri126](https://github.com/Pri126)

## License

This project is available for learning and portfolio purposes. Add a license file if you intend to specify reuse and distribution permissions.
