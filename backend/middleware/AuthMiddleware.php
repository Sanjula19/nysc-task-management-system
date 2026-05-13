<?php

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function getRequestHeaders(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = $value;
            }
        }
    }

    $normalizedHeaders = [];

    foreach ($headers as $key => $value) {
        $normalizedKey = strtolower(str_replace('-', '_', $key));
        $normalizedHeaders[$normalizedKey] = trim((string) $value);
    }

    return $normalizedHeaders;
}

function getHeaderValue(array $headers, array $names): ?string
{
    foreach ($names as $name) {
        $normalizedName = strtolower(str_replace('-', '_', $name));

        if (isset($headers[$normalizedName]) && $headers[$normalizedName] !== '') {
            return $headers[$normalizedName];
        }
    }

    return null;
}

function sendUnauthorizedResponse(): void
{
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized',
    ]);
}

function sendForbiddenResponse(): void
{
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Forbidden',
    ]);
}

function sendServerErrorResponse(): void
{
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication service unavailable',
    ]);
}

function getSessionInt(string $key): int
{
    return isset($_SESSION[$key]) ? (int) $_SESSION[$key] : 0;
}

function syncAuthSession(array $user): void
{
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role_id'] = (int) $user['role_id'];
}

function fetchAuthenticatedUser(PDO $pdo, int $userId, ?int $roleId = null): ?array
{
    $sql = '
        SELECT
            u.user_id,
            u.name,
            u.email,
            r.role_id,
            r.role_name
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.user_id
        INNER JOIN roles r ON r.role_id = ur.role_id
        WHERE u.user_id = :user_id
    ';

    $params = [
        'user_id' => $userId,
    ];

    if ($roleId !== null && $roleId > 0) {
        $sql .= ' AND r.role_id = :role_id';
        $params['role_id'] = $roleId;
    }

    $sql .= ' ORDER BY r.role_id ASC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function checkAuth(): ?array
{
    $headers = getRequestHeaders();

    $sessionUserId = getSessionInt('user_id');
    $sessionRoleId = getSessionInt('role_id');
    $headerUserId = (int) (getHeaderValue($headers, ['user_id', 'x_user_id']) ?? 0);
    $headerRoleId = (int) (getHeaderValue($headers, ['role_id', 'x_role_id', 'active_role_id', 'x_active_role_id']) ?? 0);

    $userId = $sessionUserId > 0 ? $sessionUserId : $headerUserId;
    $roleId = $headerRoleId > 0 ? $headerRoleId : $sessionRoleId;

    if ($userId <= 0) {
        sendUnauthorizedResponse();
        return null;
    }

    try {
        $pdo = getPDO();
        $user = fetchAuthenticatedUser($pdo, $userId, $roleId > 0 ? $roleId : null);

        if ($user === null && $roleId > 0) {
            $existingUser = fetchAuthenticatedUser($pdo, $userId, null);

            if ($existingUser !== null) {
                sendForbiddenResponse();
                return null;
            }
        }

        if ($user === null) {
            sendUnauthorizedResponse();
            return null;
        }

        syncAuthSession($user);

        return [
            'user_id' => (int) $user['user_id'],
            'role_id' => (int) $user['role_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_name' => $user['role_name'],
        ];
    } catch (Throwable $e) {
        sendServerErrorResponse();
        return null;
    }
}

function checkRole(array $allowedRoles): ?array
{
    $user = checkAuth();

    if ($user === null) {
        return null;
    }

    $allowedRoleIds = array_map('intval', $allowedRoles);

    if (!in_array((int) $user['role_id'], $allowedRoleIds, true)) {
        sendForbiddenResponse();
        return null;
    }

    return $user;
}
