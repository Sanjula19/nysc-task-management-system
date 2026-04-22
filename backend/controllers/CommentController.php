<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

function getCommentJsonInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
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

        $taskId = $data['task_id'] ?? null;
        $content = trim($data['content'] ?? '');
        $createdAt = date('Y-m-d H:i:s');

        if ($taskId === null || $taskId === '' || $content === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and content are required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO comments (content, user_id, task_id, created_at)
             VALUES (:content, :user_id, :task_id, :created_at)'
        );

        $stmt->execute([
            'content' => $content,
            'user_id' => (int) $user['user_id'],
            'task_id' => (int) $taskId,
            'created_at' => $createdAt,
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
        $pdo = getPDO();
        $taskId = $_GET['task_id'] ?? null;

        if ($taskId === null || $taskId === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID is required.',
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
                c.created_at
             FROM comments c
             JOIN users u ON c.user_id = u.user_id
             WHERE c.task_id = :task_id
             ORDER BY c.created_at ASC'
        );

        $stmt->execute([
            'task_id' => (int) $taskId,
        ]);

        echo json_encode([
            'status' => 'success',
            'comments' => $stmt->fetchAll(),
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

        $commentId = $data['comment_id'] ?? null;
        $content = trim($data['content'] ?? '');

        if ($commentId === null || $commentId === '' || $content === '') {
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
             WHERE comment_id = :comment_id'
        );

        $checkStmt->execute([
            'comment_id' => (int) $commentId,
        ]);

        $comment = $checkStmt->fetch();

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
            'comment_id' => (int) $commentId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Comment updated',
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

        $commentId = $data['comment_id'] ?? null;

        if ($commentId === null || $commentId === '') {
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
             WHERE comment_id = :comment_id'
        );

        $checkStmt->execute([
            'comment_id' => (int) $commentId,
        ]);

        $comment = $checkStmt->fetch();

        if (!$comment) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Comment deleted',
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
            'DELETE FROM comments
             WHERE comment_id = :comment_id'
        );

        $stmt->execute([
            'comment_id' => (int) $commentId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Comment deleted',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
