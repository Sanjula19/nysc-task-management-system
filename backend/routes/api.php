<?php

header('Content-Type: application/json');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TaskController.php';
require_once __DIR__ . '/../controllers/CommentController.php';

function resolveRoutePath(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';

    if ($pathInfo !== '') {
        return '/' . ltrim($pathInfo, '/');
    }

    if ($scriptName !== '' && strpos($requestPath, $scriptName) === 0) {
        $route = substr($requestPath, strlen($scriptName));

        return '/' . ltrim($route, '/');
    }

    return '/' . ltrim($requestPath, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$endpoint = resolveRoutePath();

if ($endpoint === '') {
    $endpoint = '/';
}

if ($endpoint !== '/' && substr($endpoint, -1) === '/') {
    $endpoint = rtrim($endpoint, '/');
}

if ($method === 'POST' && $endpoint === '/login') {
    login();
    exit;
}

if ($method === 'POST' && $endpoint === '/register') {
    register();
    exit;
}

if ($method === 'POST' && $endpoint === '/logout') {
    logout();
    exit;
}

if ($method === 'GET' && $endpoint === '/profile/me') {
    getProfile();
    exit;
}

if (preg_match('#^/users/role/(\d+)$#', $endpoint, $matches) && $method === 'GET') {
    listUsersByRole((int) $matches[1]);
    exit;
}

if ($method === 'POST' && $endpoint === '/tasks') {
    $user = checkRole([1]);

    if ($user === null) {
        exit;
    }

    createTask();
    exit;
}

if ($method === 'DELETE' && $endpoint === '/tasks') {
    $user = checkRole([1, 2]);

    if ($user === null) {
        exit;
    }

    deleteTask();
    exit;
}

if ($method === 'POST' && $endpoint === '/tasks/assign') {
    $user = checkRole([1]);

    if ($user === null) {
        exit;
    }

    assignTask();
    exit;
}

if ($method === 'DELETE' && $endpoint === '/tasks/assign') {
    $user = checkRole([1]);

    if ($user === null) {
        exit;
    }

    removeAssignment();
    exit;
}

if ($method === 'POST' && $endpoint === '/tasks/comments') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    addComment();
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/comments') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    getComments();
    exit;
}

if ($method === 'PUT' && $endpoint === '/tasks/comments') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    updateComment();
    exit;
}

if ($method === 'DELETE' && $endpoint === '/tasks/comments') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    deleteComment();
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/view') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    getTasks();
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/all') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    getAllTasks();
    exit;
}

if ($method === 'GET' && preg_match('#^/tasks/(\d+)$#', $endpoint, $matches)) {
    getTaskById((int) $matches[1]);
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/assigned') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    getAssignedUsers();
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/my') {
    $user = checkRole([4]);

    if ($user === null) {
        exit;
    }

    getMyTasks();
    exit;
}

if ($method === 'PATCH' && $endpoint === '/tasks/status') {
    $user = checkRole([4]);

    if ($user === null) {
        exit;
    }

    updateTaskStatus();
    exit;
}

if (($method === 'POST' || $method === 'PUT' || $method === 'PATCH') && $endpoint === '/profile/update') {
    $user = checkAuth();

    if ($user === null) {
        exit;
    }

    updateProfile();
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'API working',
    'method' => $method,
    'endpoint' => $endpoint,
]);
