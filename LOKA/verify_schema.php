<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';

$res = Database::getInstance()->fetchAll('DESCRIBE requests');
foreach ($res as $r) {
    if ($r->Field === 'requested_driver_id') {
        echo "FOUND: requested_driver_id\n";
        exit(0);
    }
}
echo "NOT FOUND: requested_driver_id\n";
exit(1);
