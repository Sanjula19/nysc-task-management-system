<?php

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$basePath = '/TMS/nysc-task-management-system/backend/routes/api.php';
$endpoint = '/';

if (strpos($requestPath, $basePath) === 0) {
    $endpoint = substr($requestPath, strlen($basePath));
}

if ($endpoint === '' || $endpoint === false) {
    $endpoint = '/';
}

if ($method === 'POST' && $endpoint === '/login') {
    require_once __DIR__ . '/../controllers/AuthController.php';
    login();
    exit;
}

if ($method === 'POST' && $endpoint === '/register') {
    require_once __DIR__ . '/../controllers/AuthController.php';
    register();
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'API working',
    'method' => $method,
    'endpoint' => $endpoint,
]);
