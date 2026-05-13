<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

const NYSC_ROLE_CATALOG = [
    1 => 'Chairman',
    2 => 'Director',
    3 => 'Deputy Director',
    4 => 'Assistant Director',
];

function getJsonInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
}

function normalizeRoleIds($value): array
{
    $candidateValues = [];

    if (is_array($value)) {
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $candidateValues[] = $entry['role_id'] ?? $entry['id'] ?? null;
            } else {
                $candidateValues[] = $entry;
            }
        }
    } elseif (is_string($value) && strpos($value, ',') !== false) {
        $candidateValues = array_map('trim', explode(',', $value));
    } elseif ($value !== null && $value !== '') {
        $candidateValues[] = $value;
    }

    $roleIds = [];

    foreach ($candidateValues as $candidate) {
        $roleId = (int) $candidate;

        if ($roleId > 0 && isset(NYSC_ROLE_CATALOG[$roleId])) {
            $roleIds[] = $roleId;
        }
    }

    return array_values(array_unique($roleIds));
}

function fetchRolesForUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.role_id, r.role_name
         FROM user_roles ur
         INNER JOIN roles r ON r.role_id = ur.role_id
         WHERE ur.user_id = :user_id
         ORDER BY r.role_id'
    );
    $stmt->execute([
        'user_id' => $userId,
    ]);

    return array_map(static function (array $role): array {
        return [
            'role_id' => (int) $role['role_id'],
            'role_name' => $role['role_name'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function login(): void
{
    try {
        $pdo = getPDO();
        $data = getJsonInput();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Official email and password are required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT user_id, name, email, password
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute([
            'email' => $email,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid official credentials.',
            ]);
            return;
        }

        $roles = fetchRolesForUser($pdo, (int) $user['user_id']);

        if (empty($roles)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'No authorized role assigned',
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Authentication successful',
            'user' => [
                'user_id' => (int) $user['user_id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'roles' => $roles,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function register(): void
{
    try {
        $pdo = getPDO();
        $data = getJsonInput();

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? null;
        $roleIds = normalizeRoleIds($data['role_id'] ?? ($data['role_ids'] ?? null));

        if ($name === '' || $email === '' || $password === '' || empty($roleIds)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Name, official email, password, and role are required.',
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Please provide a valid official email address.',
            ]);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Password must be at least 6 characters.',
            ]);
            return;
        }

        if ($confirmPassword !== null && $confirmPassword !== '' && $confirmPassword !== $password) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Passwords do not match',
            ]);
            return;
        }

        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute([
            'email' => $email,
        ]);

        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Official email already registered',
            ]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password)
             VALUES (:name, :email, :password)'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
        ]);

        $userId = (int) $pdo->lastInsertId();

        if (!empty($roleIds)) {
            $insertRoleStmt = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id)
                 VALUES (:user_id, :role_id)'
            );

            foreach ($roleIds as $roleId) {
                $insertRoleStmt->execute([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Official account created successfully',
            'user_id' => $userId,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function updateProfile(): void
{
    try {
        $pdo = getPDO();
        $data = getJsonInput();

        $userId = $_SERVER['HTTP_USER_ID'] ?? null;
        $name = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';

        if (!$userId) {
            throw new Exception('Unauthorized');
        }

        if ($name === '') {
            throw new Exception('Name required');
        }

        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET name = :name, password = :password
                 WHERE user_id = :id'
            );

            $stmt->execute([
                'name' => $name,
                'password' => $hashedPassword,
                'id' => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET name = :name
                 WHERE user_id = :id'
            );

            $stmt->execute([
                'name' => $name,
                'id' => $userId,
            ]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
