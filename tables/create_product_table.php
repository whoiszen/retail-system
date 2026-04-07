<?php
include "../db_connect.php";

try {
    $sql = "CREATE TABLE IF NOT EXISTS product (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        supplier_id INT NOT NULL,
        sku VARCHAR(80) UNIQUE NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE RESTRICT ON UPDATE CASCADE
    )";

    $pdo->exec($sql);
    echo "Product table created successfully.<br>";

} catch (PDOException $e) {
    die("Error creating product table: " . $e->getMessage());
}
?>