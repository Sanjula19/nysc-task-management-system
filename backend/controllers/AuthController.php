<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

const NYSC_ROLE_CATALOG = [
    1 => 'Chairman',
    2 => 'Director',
    3 => 'Deputy Director',
    4 => 'Assistant Director',
];

function getRequestData(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

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
         ORDER BY r.role_id ASC'
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

function fetchProfileSnapshot(PDO $pdo, int $userId, ?int $roleId = null): array
{
    $stmt = $pdo->prepare(
        'SELECT
            u.user_id,
            u.name,
            u.email,
            COALESCE(up.district, "") AS district,
            up.profile_photo,
            up.created_at AS profile_created_at
         FROM users u
         LEFT JOIN user_profiles up ON up.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        'user_id' => $userId,
    ]);

    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        return [];
    }

    $roles = fetchRolesForUser($pdo, $userId);
    $selectedRole = null;

    if ($roleId !== null && $roleId > 0) {
        foreach ($roles as $role) {
            if ((int) $role['role_id'] === $roleId) {
                $selectedRole = $role;
                break;
            }
        }
    }

    if ($selectedRole === null && !empty($roles)) {
        $selectedRole = $roles[0];
    }

    return [
        'user' => [
            'user_id' => (int) $userRow['user_id'],
            'name' => $userRow['name'],
            'email' => $userRow['email'],
            'role_id' => $selectedRole['role_id'] ?? 0,
            'role_name' => $selectedRole['role_name'] ?? '',
        ],
        'profile' => [
            'district' => $userRow['district'] ?? '',
            'profile_photo' => $userRow['profile_photo'] ?? null,
            'created_at' => $userRow['profile_created_at'] ?? null,
        ],
        'roles' => $roles,
        'selectedRole' => $selectedRole,
    ];
}

function login(): void
{
    try {
        $pdo = getPDO();
        $data = getRequestData();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $requestedRoleId = (int) ($data['role_id'] ?? $data['selected_role_id'] ?? 0);

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
                'message' => 'No authorized role assigned.',
            ]);
            return;
        }

        $selectedRole = $roles[0];

        foreach ($roles as $role) {
            if ($requestedRoleId > 0 && (int) $role['role_id'] === $requestedRoleId) {
                $selectedRole = $role;
                break;
            }
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['role_id'] = (int) $selectedRole['role_id'];

        $snapshot = fetchProfileSnapshot($pdo, (int) $user['user_id'], (int) $selectedRole['role_id']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Authentication successful.',
            'user' => $snapshot['user'],
            'roles' => $snapshot['roles'],
            'selectedRole' => $snapshot['selectedRole'],
            'profile' => $snapshot['profile'],
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
        $data = getRequestData();

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
                'message' => 'Passwords do not match.',
            ]);
            return;
        }

        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute([
            'email' => $email,
        ]);

        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Official email already registered.',
            ]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, created_at)
             VALUES (:name, :email, :password, NOW())'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
        ]);

        $userId = (int) $pdo->lastInsertId();

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

        $profileStmt = $pdo->prepare(
            'INSERT INTO user_profiles (user_id, district, profile_photo, created_at)
             VALUES (:user_id, "", NULL, NOW())'
        );

        $profileStmt->execute([
            'user_id' => $userId,
        ]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Official account created successfully.',
            'user_id' => $userId,
        ]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function storeProfilePhotoUpload(array $file, int $userId): string
{
    $uploadDirectory = __DIR__ . '/../uploads/profile';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create profile upload directory.');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        $extension = 'jpg';
    }

    $fileName = sprintf(
        'user_%d_%s_%s.%s',
        $userId,
        date('YmdHis'),
        bin2hex(random_bytes(4)),
        $extension
    );

    $targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to store uploaded profile photo.');
    }

    return 'backend/uploads/profile/' . $fileName;
}

function updateProfile(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getRequestData();
        $userId = (int) $user['user_id'];

        $name = trim($data['name'] ?? '');
        $district = trim($data['district'] ?? '');

        if ($name === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Name is required.',
            ]);
            return;
        }

        $existingProfileStmt = $pdo->prepare(
            'SELECT profile_id, profile_photo
             FROM user_profiles
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $existingProfileStmt->execute([
            'user_id' => $userId,
        ]);
        $existingProfile = $existingProfileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $profilePhotoPath = $existingProfile['profile_photo'] ?? null;

        if (isset($_FILES['profile_photo']) && is_array($_FILES['profile_photo']) && (int) ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $profilePhotoPath = storeProfilePhotoUpload($_FILES['profile_photo'], $userId);
        }

        $pdo->beginTransaction();

        $updateUserStmt = $pdo->prepare(
            'UPDATE users
             SET name = :name
             WHERE user_id = :user_id'
        );

        $updateUserStmt->execute([
            'name' => $name,
            'user_id' => $userId,
        ]);

        $profileUpsertStmt = $pdo->prepare(
            'INSERT INTO user_profiles (user_id, district, profile_photo, created_at)
             VALUES (:user_id, :district, :profile_photo, NOW())
             ON DUPLICATE KEY UPDATE
                district = VALUES(district),
                profile_photo = VALUES(profile_photo)'
        );

        $profileUpsertStmt->execute([
            'user_id' => $userId,
            'district' => $district,
            'profile_photo' => $profilePhotoPath,
        ]);

        $pdo->commit();

        $snapshot = fetchProfileSnapshot($pdo, $userId, (int) $user['role_id']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'user' => $snapshot['user'],
            'profile' => $snapshot['profile'],
            'roles' => $snapshot['roles'],
            'selectedRole' => $snapshot['selectedRole'],
        ]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getProfile(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $snapshot = fetchProfileSnapshot($pdo, (int) $user['user_id'], (int) $user['role_id']);

        if (empty($snapshot)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Profile not found.',
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'user' => $snapshot['user'],
            'profile' => $snapshot['profile'],
            'roles' => $snapshot['roles'],
            'selectedRole' => $snapshot['selectedRole'],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function logout(): void
{
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();

        echo json_encode([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function listUsersByRole(int $roleId): void
{
    try {
        $user = checkRole([1, 2, 3]);

        if ($user === null) {
            return;
        }

        if ($roleId < 1 || $roleId > 4) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid role requested.',
            ]);
            return;
        }

        $pdo = getPDO();

        $stmt = $pdo->prepare(
            'SELECT
                u.user_id,
                u.name,
                u.email,
                COALESCE(up.district, "") AS district,
                up.profile_photo,
                r.role_id,
                r.role_name
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.user_id
             INNER JOIN roles r ON r.role_id = ur.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.user_id
             WHERE ur.role_id = :role_id
             ORDER BY u.name ASC'
        );

        $stmt->execute([
            'role_id' => $roleId,
        ]);

        echo json_encode([
            'status' => 'success',
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
