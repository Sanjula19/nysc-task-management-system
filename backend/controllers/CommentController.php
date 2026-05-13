<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

function getCommentJsonInput(): array
{
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    return is_array($data) ? $data : [];
}

function userCanAccessTask(PDO $pdo, array $user, int $taskId): bool
{
    if ((int) $user['role_id'] !== 4) {
        return true;
    }

    $stmt = $pdo->prepare(
        'SELECT task_id
         FROM task_assignments
         WHERE task_id = :task_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => (int) $user['user_id'],
    ]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function addComment(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getCommentJsonInput();

        $taskId = (int) ($data['task_id'] ?? 0);
        $content = trim($data['content'] ?? '');

        if ($taskId <= 0 || $content === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and content are required.',
            ]);
            return;
        }

        if (!userCanAccessTask($pdo, $user, $taskId)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $taskExists = $pdo->prepare('SELECT task_id FROM tasks WHERE task_id = :task_id LIMIT 1');
        $taskExists->execute([
            'task_id' => $taskId,
        ]);

        if (!$taskExists->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO comments (task_id, user_id, content, created_at)
             VALUES (:task_id, :user_id, :content, NOW())'
        );

        $stmt->execute([
            'task_id' => $taskId,
            'user_id' => (int) $user['user_id'],
            'content' => $content,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Comment added successfully.',
            'comment_id' => (int) $pdo->lastInsertId(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getComments(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $taskId = (int) ($_GET['task_id'] ?? 0);

        if ($taskId <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID is required.',
            ]);
            return;
        }

        if (!userCanAccessTask($pdo, $user, $taskId)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $taskExists = $pdo->prepare('SELECT task_id FROM tasks WHERE task_id = :task_id LIMIT 1');
        $taskExists->execute([
            'task_id' => $taskId,
        ]);

        if (!$taskExists->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                c.comment_id,
                c.task_id,
                c.user_id,
                u.name,
                c.content,
                c.created_at,
                COALESCE(r.role_name, "") AS role_name,
                COALESCE(up.profile_photo, "") AS profile_photo
             FROM comments c
             INNER JOIN users u ON u.user_id = c.user_id
             LEFT JOIN (
                SELECT ur.user_id, MIN(ur.role_id) AS role_id
                FROM user_roles ur
                GROUP BY ur.user_id
             ) primary_role ON primary_role.user_id = u.user_id
             LEFT JOIN roles r ON r.role_id = primary_role.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.user_id
             WHERE c.task_id = :task_id
             ORDER BY c.created_at ASC, c.comment_id ASC'
        );

        $stmt->execute([
            'task_id' => $taskId,
        ]);

        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'comments' => $comments,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function updateComment(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getCommentJsonInput();

        $commentId = (int) ($data['comment_id'] ?? 0);
        $content = trim($data['content'] ?? '');

        if ($commentId <= 0 || $content === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Comment ID and content are required.',
            ]);
            return;
        }

        $checkStmt = $pdo->prepare(
            'SELECT user_id
             FROM comments
             WHERE comment_id = :comment_id
             LIMIT 1'
        );

        $checkStmt->execute([
            'comment_id' => $commentId,
        ]);

        $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Comment not found.',
            ]);
            return;
        }

        if ((int) $comment['user_id'] !== (int) $user['user_id']) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE comments
             SET content = :content
             WHERE comment_id = :comment_id'
        );

        $stmt->execute([
            'content' => $content,
            'comment_id' => $commentId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Comment updated.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function deleteComment(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getCommentJsonInput();

        $commentId = (int) ($data['comment_id'] ?? 0);

        if ($commentId <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Comment ID is required.',
            ]);
            return;
        }

        $checkStmt = $pdo->prepare(
            'SELECT user_id
             FROM comments
             WHERE comment_id = :comment_id
             LIMIT 1'
        );

        $checkStmt->execute([
            'comment_id' => $commentId,
        ]);

        $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Comment not found.',
            ]);
            return;
        }

        if ((int) $user['role_id'] !== 1 && (int) $comment['user_id'] !== (int) $user['user_id']) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'DELETE FROM comments
             WHERE comment_id = :comment_id'
        );

        $stmt->execute([
            'comment_id' => $commentId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Comment deleted.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
