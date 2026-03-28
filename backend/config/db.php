<?php

$host = 'localhost';
$dbname = 'nysc_tms';
$username = 'root';
$password = '';

try {
    $connection = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username='root',
        $password = '1234'
    );
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
}

return $connection;
