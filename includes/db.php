<?php

$config = require __DIR__ . '/../config.php';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

$db = new PDO($dsn, $config['user'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);