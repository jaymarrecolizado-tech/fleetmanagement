<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();
$res = $db->fetchAll("DESCRIBE request_passengers");
print_r($res);
