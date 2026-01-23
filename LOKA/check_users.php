<?php
// Quick check of users in database
require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);

    $users = $pdo->query("SELECT id, email, name, role, status FROM users WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    echo "=== USERS IN DATABASE ===\n\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']}\n";
        echo "Email: {$user['email']}\n";
        echo "Name: {$user['name']}\n";
        echo "Role: {$user['role']}\n";
        echo "Status: {$user['status']}\n";
        echo "--------------------------------\n";
    }

    echo "\nTotal users: " . count($users) . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
