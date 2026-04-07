<?php
include "../db_connect.php";

try {
    $sql = "CREATE TABLE IF NOT EXISTS stock_transaction (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        transaction_type ENUM('in', 'out', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        reference_no VARCHAR(100),
        notes TEXT,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE RESTRICT ON UPDATE CASCADE
    )";

    $pdo->exec($sql);
    echo "StockTransaction table created successfully.<br>";

} catch (PDOException $e) {
    die("Error creating stock_transaction table: " . $e->getMessage());
}
?>