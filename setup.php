<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // 1. Create Database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS track_manager");
    $pdo->exec("USE track_manager");

    // 2. Create Users Table (With Staff ID & Join Date)
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id VARCHAR(50) UNIQUE,
        full_name VARCHAR(255) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('lead', 'tester', 'admin') NOT NULL,
        joined_date DATE,
        last_login DATETIME DEFAULT NULL,
        pfp_path VARCHAR(255) DEFAULT NULL,
        status ENUM('active','blocked') DEFAULT 'active',
        remember_token VARCHAR(255) DEFAULT NULL
    )";
    $pdo->exec($sql);

    // 3. Clear existing users
    $pdo->exec("TRUNCATE TABLE users");

    // 4. Insert Users with Staff IDs and Join Dates
    $password = 'asdqwe';
    $hashed_pwd = password_hash($password, PASSWORD_DEFAULT);

    $stm = $pdo->prepare("INSERT INTO users (staff_id, full_name, username, password, role, joined_date) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Tester: Chan Jian Feng
    $stm->execute(['STAFF-001', 'Chan Jian Feng', 'jf', $hashed_pwd, 'tester', '2025-06-15']);
    
    // Lead: Kali
    $stm->execute(['STAFF-002', 'Kali', 'kali', $hashed_pwd, 'lead', '2024-03-10']);
    
    // Admin: Admin User
    $stm->execute(['STAFF-003', 'Admin User', 'admin', $hashed_pwd, 'admin', '2023-01-01']);

    echo "<h1>System Installed Successfully</h1>";
    echo "<p>Database updated. Join Dates assigned.</p>";
    echo "<ul>
            <li><strong>Admin:</strong> staff_id: STAFF-003, joined: 2023-01-01</li>
            <li><strong>Lead:</strong> staff_id: STAFF-002, joined: 2024-03-10</li>
            <li><strong>Tester:</strong> staff_id: STAFF-001, joined: 2025-06-15</li>
          </ul>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>