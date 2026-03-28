<?php

header('Content-Type: application/json');

try {
    $connection = include __DIR__ . '/../config/db.php';

    if (!$connection instanceof PDO) {
        throw new Exception('Database connection not available.');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email and password are required.',
        ]);
        exit;
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
        exit;
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
