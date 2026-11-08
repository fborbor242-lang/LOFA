<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die('<b>Connection failed:</b> ' . $conn->connect_error);

$queries = [
    "CREATE DATABASE IF NOT EXISTS alexander_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    "USE alexander_shop",

    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','cashier') DEFAULT 'cashier',
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(30),
        email VARCHAR(150),
        address TEXT,
        category VARCHAR(100),
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        sku VARCHAR(100) UNIQUE,
        barcode VARCHAR(100) UNIQUE,
        category_id INT,
        unit VARCHAR(50) DEFAULT 'piece',
        buy_price DECIMAL(10,2) DEFAULT 0.00,
        sell_price DECIMAL(10,2) NOT NULL,
        stock_quantity INT DEFAULT 0,
        low_stock_alert INT DEFAULT 5,
        status ENUM('active','inactive','deleted') DEFAULT 'active',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        email VARCHAR(150),
        address TEXT,
        credit_limit DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL UNIQUE,
        customer_id INT,
        user_id INT,
        subtotal DECIMAL(10,2) NOT NULL,
        discount DECIMAL(10,2) DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL,
        payment_method ENUM('cash','card','mobile_money','credit') NOT NULL,
        amount_paid DECIMAL(10,2) DEFAULT 0.00,
        change_amount DECIMAL(10,2) DEFAULT 0.00,
        credit_amount DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('completed','voided') DEFAULT 'completed',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(200) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('cash','card','mobile_money') NOT NULL,
        reference VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS credits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        customer_id INT NOT NULL,
        original_amount DECIMAL(10,2) NOT NULL,
        amount_paid DECIMAL(10,2) DEFAULT 0.00,
        balance DECIMAL(10,2) NOT NULL,
        due_date DATE,
        status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS credit_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        credit_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('cash','bank_transfer','mobile_money') NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (credit_id) REFERENCES credits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        type ENUM('sale','purchase','adjustment','return') NOT NULL,
        quantity INT NOT NULL,
        reference_id INT,
        reference_type VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference_number VARCHAR(50) UNIQUE,
        supplier VARCHAR(200),
        total DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('received','pending','cancelled') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS purchase_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",

    // ── Seed data ──────────────────────────────────────────
    "INSERT IGNORE INTO users (name, username, password, role) VALUES
        ('Alexander O.', 'admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
        ('Juan Dela Cruz', 'juan', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier'),
        ('Maria Santos', 'maria', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier')",

    "INSERT IGNORE INTO categories (id, name) VALUES
        (1,'Food'),(2,'Drinks'),(3,'Household'),(4,'Personal Care'),(5,'Other')",

    "INSERT IGNORE INTO suppliers (name, contact_person, phone, category) VALUES
        ('ABC Distributors',  'John Abc',   '+1 555-1001', 'General goods'),
        ('Global Supplies Co.','Mary Global','+1 555-1002', 'Beverages'),
        ('Metro Wholesale',   'Pete Metro', '+1 555-1003', 'Household'),
        ('Fresh Goods Ltd.',  'Sara Fresh', '+1 555-1004', 'Fresh produce')",

    "INSERT IGNORE INTO products (name,sku,barcode,category_id,unit,buy_price,sell_price,stock_quantity,low_stock_alert,status) VALUES
        ('Rice 1kg',           'SKU-0001','BAR-0001',1,'kg',    0.85, 1.20, 30, 5,'active'),
        ('Sugar 1kg',          'SKU-0005','BAR-0005',1,'kg',    0.70, 0.95, 20, 5,'active'),
        ('Bread Loaf 400g',    'SKU-0023','BAR-0023',1,'piece', 1.20, 1.80,  4, 5,'active'),
        ('Instant Noodles',    'SKU-0010','BAR-0010',1,'piece', 0.40, 0.60, 50, 5,'active'),
        ('Cooking Oil 1L',     'SKU-0006','BAR-0006',1,'liter', 2.20, 2.80, 12, 5,'active'),
        ('Canned Sardines',    'SKU-0130','BAR-0130',1,'piece', 0.90, 1.35,  0, 5,'active'),
        ('Coca-Cola 1.5L',     'SKU-0042','BAR-0042',2,'piece', 1.80, 2.50,  2, 5,'active'),
        ('Pepsi 1.5L',         'SKU-0043','BAR-0043',2,'piece', 1.80, 2.50, 15, 5,'active'),
        ('Mineral Water 500ml','SKU-0044','BAR-0044',2,'piece', 0.50, 0.80, 40, 5,'active'),
        ('Orange Juice 1L',    'SKU-0045','BAR-0045',2,'liter', 2.50, 3.20,  8, 5,'active'),
        ('Detergent Bar 120g', 'SKU-0115','BAR-0115',3,'piece', 0.30, 0.45,  5, 5,'active'),
        ('Dishwashing Liquid', 'SKU-0116','BAR-0116',3,'piece', 1.30, 1.90, 18, 5,'active'),
        ('Floor Cleaner 500ml','SKU-0117','BAR-0117',3,'piece', 1.60, 2.20, 10, 5,'active'),
        ('Tissue Roll 6s',     'SKU-0118','BAR-0118',3,'pack',  1.80, 2.50, 22, 5,'active'),
        ('Shampoo Sachet 15ml','SKU-0088','BAR-0088',4,'piece', 0.20, 0.30,  1, 5,'active'),
        ('Soap Bar 90g',       'SKU-0089','BAR-0089',4,'piece', 0.50, 0.75, 25, 5,'active'),
        ('Toothpaste 75ml',    'SKU-0090','BAR-0090',4,'piece', 1.10, 1.60, 14, 5,'active'),
        ('Deodorant Spray',    'SKU-0092','BAR-0092',4,'piece', 2.80, 3.50,  7, 5,'active'),
        ('Lays Classic 150g',  'SKU-0091','BAR-0091',1,'piece', 1.00, 1.50,  3, 5,'active'),
        ('Salt 500g',          'SKU-0007','BAR-0007',1,'piece', 0.30, 0.50, 35, 5,'active')",

    "INSERT IGNORE INTO customers (first_name,last_name,phone,email,credit_limit,status) VALUES
        ('Maria',  'Santos',    '+1 555-0101','maria@email.com',  500.00,'active'),
        ('Juan',   'Dela Cruz', '+1 555-0102','juan@email.com',   300.00,'active'),
        ('Ana',    'Reyes',     '+1 555-0103','ana@email.com',    400.00,'active'),
        ('Carlos', 'Mendez',    '+1 555-0104','carlos@email.com', 200.00,'active'),
        ('Rosa',   'Lim',       '+1 555-0105','rosa@email.com',   600.00,'active'),
        ('Ben',    'Torres',    '+1 555-0106','ben@email.com',    100.00,'inactive')",
];

echo '<style>body{font-family:sans-serif;padding:20px;max-width:800px;} .ok{color:green;} .err{color:red;} h2{color:#2563EB;}</style>';
echo '<h2>Alexander Shop — Database Setup</h2>';

$errors = 0;
foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo '<p class="ok">✓ ' . htmlspecialchars(substr(trim($sql), 0, 70)) . '...</p>';
    } else {
        echo '<p class="err">✗ Error: ' . $conn->error . '<br><small>' . htmlspecialchars(substr(trim($sql),0,100)) . '</small></p>';
        $errors++;
    }
}

$conn->close();

if ($errors === 0) {
    echo '<hr><h3 class="ok">✅ Setup complete! All tables created and seed data inserted.</h3>';
    echo '<p><strong>Default login:</strong> username: <code>admin</code> | password: <code>password</code></p>';
    echo '<p><a href="/ALEXANDER/index.html">→ Go to Dashboard</a></p>';
} else {
    echo '<hr><h3 class="err">⚠ Setup completed with ' . $errors . ' error(s).</h3>';
}
