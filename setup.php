<?php
// IN phpmyadmin, import the "track_manager.sql" file to create the database and tables before running this setup script.
// Run this ONCE to set up the Starting Users correctly.
// FYI, xampp mysql is very unstable and may cause issues. If you encounter "SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it.", try restarting the MySQL service in XAMPP or check for port conflicts.

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // 1. Create Database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS track_manager");
    $pdo->exec("USE track_manager");

    // 2. Create Users Table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('lead', 'tester', 'admin') NOT NULL,
        last_login DATETIME DEFAULT NULL
    )";
    $pdo->exec($sql);

    // 3. Clear existing users to avoid duplicates/bad hashes
    $pdo->exec("TRUNCATE TABLE users");

    // 4. Insert Users
    $password = 'asdqwe'; // The password for all users for testing purposes.
    $hashed_pwd = password_hash($password, PASSWORD_DEFAULT);

    $stm = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
    
    // Insert Tester
    $stm->execute(['Chan Jian Feng', 'jf', $hashed_pwd, 'tester']);
    // Insert Lead
    $stm->execute(['Kali', 'kali', $hashed_pwd, 'lead']);
    // Insert Admin
    $stm->execute(['Admin User', 'admin', $hashed_pwd, 'admin']);
    echo "<h1>System Installed Successfully</h1>";
    echo "<p>Database created. Users 'jf', 'kali', and 'admin' added with password: <strong>asdqwe</strong></p>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>