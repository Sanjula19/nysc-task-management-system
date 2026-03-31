<?php

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getPDO();
    echo "DB Connected";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}