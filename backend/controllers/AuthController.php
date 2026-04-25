<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

function getJsonInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
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
                'message' => 'Email and password are required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare('SELECT user_id, name, role_id, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful.',
            'user' => [
                'id' => (int) $user['user_id'],
                'name' => $user['name'],
                'role_id' => (int) $user['role_id'],
            ],
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
        $roleId = $data['role_id'] ?? null;

        if ($name === '' || $email === '' || $password === '' || $roleId === null || $roleId === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Name, email, password, and role_id are required.',
            ]);
            return;
        }

        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute(['email' => $email]);

        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Email already exists.',
            ]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, role_id) VALUES (:name, :email, :password, :role_id)'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'role_id' => (int) $roleId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully.',
            'user_id' => (int) $pdo->lastInsertId(),
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
        $data = json_decode(file_get_contents('php://input'), true);

        $userId = $_SERVER['HTTP_USER_ID'] ?? null;
        $name = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';

        if (!$userId) {
            throw new Exception("Unauthorized");
        }

        if ($name === '') {
            throw new Exception("Name required");
        }

        // If password provided → hash it
        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name, password = :password 
                WHERE user_id = :id
            ");

            $stmt->execute([
                'name' => $name,
                'password' => $hashed,
                'id' => $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name 
                WHERE user_id = :id
            ");

            $stmt->execute([
                'name' => $name,
                'id' => $userId
            ]);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Profile updated"
        ]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}