<?php
include "../db_connect.php";

try {
    $sql = "CREATE TABLE IF NOT EXISTS stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL UNIQUE,
        quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
        reorder_level INT NOT NULL DEFAULT 10,
        location VARCHAR(100),
        last_restocked TIMESTAMP NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE
    )";

    $pdo->exec($sql);
    echo "Stock table created successfully.<br>";

} catch (PDOException $e) {
    die("Error creating stock table: " . $e->getMessage());
}
?>