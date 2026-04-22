<?php

header('Content-Type: application/json');

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

function checkAuth(): ?array
{
    $headers = getRequestHeaders();
    $userId = getHeaderValue($headers, ['user_id', 'x_user_id']);
    $roleId = getHeaderValue($headers, ['role_id', 'x_role_id']);

    if ($userId === null || $roleId === null) {
        sendUnauthorizedResponse();
        return null;
    }

    return [
        'user_id' => (int) $userId,
        'role_id' => (int) $roleId,
    ];
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
