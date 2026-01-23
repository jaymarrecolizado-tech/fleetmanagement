<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $sql = file_get_contents(__DIR__ . '/sql/migrations/006_passenger_enhancements.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $db = Database::getInstance();
    $db->beginTransaction();
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $db->query($stmt);
        }
    }
    $db->commit();
    echo "Migration applied successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
