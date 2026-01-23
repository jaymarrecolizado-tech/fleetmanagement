<?php
// Update admin email to match documentation
require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);

    // Check if admin@fleet.local already exists
    $exists = $pdo->query("SELECT id FROM users WHERE email = 'admin@fleet.local'")->fetch();

    if ($exists) {
        echo "admin@fleet.local already exists. Skipping update.\n";
    } else {
        // Update admin email from jelite.demo@gmail.com to admin@fleet.local
        $stmt = $pdo->prepare("UPDATE users SET email = 'admin@fleet.local' WHERE email = 'jelite.demo@gmail.com'");
        $result = $stmt->execute();

        if ($result) {
            echo "✅ Admin email updated successfully!\n";
            echo "   Old: jelite.demo@gmail.com\n";
            echo "   New: admin@fleet.local\n";
        } else {
            echo "❌ Failed to update admin email\n";
        }
    }

    // Verify admin user
    $admin = $pdo->query("SELECT email, name, role, status FROM users WHERE email = 'admin@fleet.local'")->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        echo "\n=== ADMIN USER VERIFIED ===\n";
        echo "Email: {$admin['email']}\n";
        echo "Name: {$admin['name']}\n";
        echo "Role: {$admin['role']}\n";
        echo "Status: {$admin['status']}\n";
    } else {
        echo "\n❌ Admin user not found!\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
