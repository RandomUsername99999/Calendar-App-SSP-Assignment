<?php
require 'db.php';

try {
    // Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        description TEXT,
        avatar_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'users' created successfully.<br>";

    // Tasks Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        due_date DATETIME NOT NULL,
        is_special TINYINT(1) DEFAULT 0,
        color VARCHAR(20) DEFAULT 'blue',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'tasks' created successfully.<br>";

    // Friendships Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS friendships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id_1 INT NOT NULL,
        user_id_2 INT NOT NULL,
        status ENUM('pending', 'accepted') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(user_id_1, user_id_2)
    )");
    echo "Table 'friendships' created successfully.<br>";

    // Task Participants Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'task_participants' created successfully.<br>";

    echo "All tables set up successfully.";

} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>
