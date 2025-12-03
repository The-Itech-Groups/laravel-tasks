<?php
session_start();
$host = 'localhost';
$db   = 'premier_league_manager';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage());
}
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (empty($email) || empty($password)) {
    die("Both fields are required.");
}
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    if ($user['role'] = 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: fan/dashboard.php");
    }
    exit;
} else {
    die("Invalid credentials.");
}
?>