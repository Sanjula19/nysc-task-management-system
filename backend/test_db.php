<?php

try {
    $connection = require __DIR__ . '/config/db.php';

    if ($connection instanceof PDO) {
        echo 'DB Connected';
    }
} catch (PDOException $e) {
    echo 'DB Connection Failed: ' . $e->getMessage();
}
