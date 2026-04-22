<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/TaskController.php';
require_once __DIR__ . '/../controllers/CommentController.php';

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

if ($method === 'POST' && $endpoint === '/tasks') {
    $user = checkRole([1]);

    if ($user === null) {
        exit;
    }

    createTask();
    exit;
}

if ($method === 'DELETE' && $endpoint === '/tasks') {
    $user = checkRole([1]);

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
    $user = checkRole([1, 2, 3, 4]);

    if ($user === null) {
        exit;
    }

    getTasks();
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/all') {
    $user = checkRole([1, 2, 3]);

    if ($user === null) {
        exit;
    }

    getAllTasks();
    exit;
}

if ($method === 'GET' && preg_match('#^/tasks/(\d+)$#', $endpoint, $matches)) {
    require_once __DIR__ . '/../controllers/TaskController.php';
    getTaskById((int) $matches[1]);
    exit;
}

if ($method === 'GET' && $endpoint === '/tasks/assigned') {
    $user = checkRole([1, 2, 3, 4]);

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

if ($method === 'PUT' && $endpoint === '/tasks/status') {
    require_once __DIR__ . '/../controllers/TaskController.php';
    updateTaskStatus();
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'API working',
    'method' => $method,
    'endpoint' => $endpoint,
]);
