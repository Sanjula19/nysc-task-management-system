<?php

header('Content-Type: application/json');

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

function checkAuth(): ?array
{
    $headers = getRequestHeaders();
    $userId = (int) (getHeaderValue($headers, ['user_id', 'x_user_id']) ?? 0);
    $roleId = (int) (getHeaderValue($headers, ['role_id', 'x_role_id', 'active_role_id', 'x_active_role_id']) ?? 0);

    if ($userId <= 0 || $roleId <= 0) {
        sendUnauthorizedResponse();
        return null;
    }

    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            'SELECT u.user_id, r.role_id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.user_id
             INNER JOIN roles r ON r.role_id = ur.role_id
             WHERE u.user_id = :user_id
               AND r.role_id = :role_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            sendForbiddenResponse();
            return null;
        }

        return [
            'user_id' => (int) $user['user_id'],
            'role_id' => (int) $user['role_id'],
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
