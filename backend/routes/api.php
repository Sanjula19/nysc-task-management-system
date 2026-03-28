<?php

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function login(): void
{
    include __DIR__ . '/../controllers/AuthController.php';
}

$response = [
    'status' => 'success',
    'method' => $method,
    'uri' => $uri,
    'message' => 'API working',
];

switch ($uri) {
    case '/api':
    case '/api.php':
        break;

    case '/login':
        if ($method === 'POST') {
            login();
            exit;
        }

        http_response_code(405);
        $response = [
            'status' => 'error',
            'message' => 'Method not allowed.',
        ];
        break;

    default:
        break;
}

echo json_encode($response);
