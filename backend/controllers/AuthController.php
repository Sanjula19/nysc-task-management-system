<?php

header('Content-Type: application/json');

function getConnection(): PDO
{
    $connection = include __DIR__ . '/../config/db.php';

    if (!$connection instanceof PDO) {
        throw new Exception('Database connection not available.');
    }

    return $connection;
}

function getJsonInput(): array
{
    $input = json_decode(file_get_contents('php://input'), true);

    return is_array($input) ? $input : [];
}

function login(): void
{
    try {
        $connection = getConnection();
        $input = getJsonInput();

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Email and password are required.',
            ]);
            return;
        }

        $statement = $connection->prepare(
            'SELECT id, name, role_id, password FROM users WHERE email = :email LIMIT 1'
        );
        $statement->bindValue(':email', $email, PDO::PARAM_STR);
        $statement->execute();

        $user = $statement->fetch(PDO::FETCH_ASSOC);

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
            'user_id' => (int) $user['id'],
            'name' => $user['name'],
            'role_id' => $user['role_id'],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error.',
        ]);
    }
}

function register(): void
{
    try {
        $connection = getConnection();
        $input = getJsonInput();

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $roleId = $input['role_id'] ?? null;

        if ($name === '' || $email === '' || $password === '' || $roleId === null || $roleId === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Name, email, password, and role_id are required.',
            ]);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $statement = $connection->prepare(
            'INSERT INTO users (name, email, password, role_id) VALUES (:name, :email, :password, :role_id)'
        );
        $statement->bindValue(':name', $name, PDO::PARAM_STR);
        $statement->bindValue(':email', $email, PDO::PARAM_STR);
        $statement->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        $statement->bindValue(':role_id', (int) $roleId, PDO::PARAM_INT);
        $statement->execute();

        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully.',
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Email already exists.',
            ]);
            return;
        }

        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error.',
        ]);
    }
}
