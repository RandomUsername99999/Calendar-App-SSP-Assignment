<?php
require 'db.php';

try {
    $pdo->exec("ALTER TABLE task_participants ADD COLUMN status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'");
    echo "Column 'status' added to 'task_participants' table successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
         echo "Column 'status' already exists.";
    } else {
         echo "Error updating table: " . $e->getMessage();
    }
}
?>
