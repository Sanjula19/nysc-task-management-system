<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

function getTaskJsonInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
}

function createTask(): void
{
    try {
        $user = checkRole([1]);

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getTaskJsonInput();

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $priority = trim($data['priority'] ?? '');
        $deadline = trim($data['deadline'] ?? '');
        $status = 'PENDING';
        $createdAt = date('Y-m-d H:i:s');
        $createdBy = (int) $user['user_id'];

        if ($title === '' || $description === '' || $priority === '' || $deadline === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Title, description, priority, and deadline are required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tasks (title, description, priority, deadline, status, created_at, created_by)
             VALUES (:title, :description, :priority, :deadline, :status, :created_at, :created_by)'
        );

        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'deadline' => $deadline,
            'status' => $status,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Task created successfully.',
            'task_id' => (int) $pdo->lastInsertId(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getTasks(): void
{
    try {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            'SELECT task_id, title, description, status, priority, deadline, created_at
             FROM tasks
             ORDER BY created_at DESC'
        );
        $stmt->execute();

        echo json_encode([
            'status' => 'success',
            'tasks' => $stmt->fetchAll(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function updateTaskStatus(): void
{
    $user = checkAuth();

    if (!$user) {
        return;
    }

    $roleId = (int) $user['role_id'];
    $userId = (int) $user['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);

    $taskId = $input['task_id'] ?? null;
    $status = $input['status'] ?? null;

    if (!$taskId || !$status) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing task_id or status',
        ]);
        return;
    }

    $allowedStatuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid status',
        ]);
        return;
    }

    try {
        $pdo = getPDO();

        if ($roleId === 4) {
            $assignmentCheck = $pdo->prepare(
                'SELECT task_id FROM task_assignments WHERE task_id = :task_id AND user_id = :user_id'
            );
            $assignmentCheck->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
            ]);

            if (!$assignmentCheck->fetch()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Forbidden',
                ]);
                return;
            }
        }

        $check = $pdo->prepare('SELECT task_id FROM tasks WHERE task_id = ?');
        $check->execute([$taskId]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE tasks
             SET status = :status
             WHERE task_id = :task_id'
        );

        $stmt->execute([
            'status' => $status,
            'task_id' => $taskId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Task status updated',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function deleteTask(): void
{
    try {
        $user = checkAuth();

        if (!$user) {
            return;
        }

        $roleId = (int) $user['role_id'];

        if (!in_array($roleId, [1, 2], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();
        $data = getTaskJsonInput();

        $taskId = $data['task_id'] ?? null;

        if ($taskId === null || $taskId === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID is required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'DELETE FROM tasks
             WHERE task_id = :task_id'
        );

        $stmt->execute([
            'task_id' => (int) $taskId,
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found.',
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Task deleted successfully.',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function assignTask(): void
{
    try {
        $user = checkAuth();

        if (!$user) {
            return;
        }

        $roleId = (int) $user['role_id'];

        if (!in_array($roleId, [1, 2], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();
        $data = getTaskJsonInput();

        $taskId = $data['task_id'] ?? null;
        $userIds = $data['user_ids'] ?? null;

        if ($taskId === null || $taskId === '' || !is_array($userIds) || empty($userIds)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and user_ids are required.',
            ]);
            return;
        }

        $taskId = (int) $taskId;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, function ($userId) {
            return $userId > 0;
        }));

        if ($taskId <= 0 || empty($userIds)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid task ID or user_ids.',
            ]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $userCheckStmt = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE role_id = 4
             AND user_id IN ({$placeholders})"
        );
        $userCheckStmt->execute($userIds);

        $assistantDirectorIds = array_map('intval', $userCheckStmt->fetchAll(PDO::FETCH_COLUMN));
        sort($assistantDirectorIds);

        $requestedUserIds = $userIds;
        sort($requestedUserIds);

        if ($assistantDirectorIds !== $requestedUserIds) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'All assigned users must be Assistant Directors.',
            ]);
            return;
        }

        $existingAssignmentStmt = $pdo->prepare(
            "SELECT user_id
             FROM task_assignments
             WHERE task_id = ?
             AND user_id IN ({$placeholders})"
        );
        $existingAssignmentStmt->execute(array_merge([$taskId], $userIds));

        $existingUserIds = array_map('intval', $existingAssignmentStmt->fetchAll(PDO::FETCH_COLUMN));
        $newUserIds = array_values(array_diff($userIds, $existingUserIds));

        if (!empty($newUserIds)) {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare(
                'INSERT INTO task_assignments (task_id, user_id)
                 VALUES (:task_id, :user_id)'
            );

            foreach ($newUserIds as $userId) {
                $insertStmt->execute([
                    'task_id' => $taskId,
                    'user_id' => $userId,
                ]);
            }

            $pdo->commit();
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Task assigned successfully.',
            'assigned_count' => count($newUserIds),
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

function getAssignedUsers(): void
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
            'SELECT u.user_id, u.name, u.email
             FROM task_assignments ta
             INNER JOIN users u ON ta.user_id = u.user_id
             WHERE ta.task_id = :task_id'
        );

        $stmt->execute([
            'task_id' => (int) $taskId,
        ]);

        echo json_encode([
            'status' => 'success',
            'users' => $stmt->fetchAll(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function removeAssignment(): void
{
    try {
        $pdo = getPDO();
        $data = getTaskJsonInput();

        $taskId = $data['task_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if ($taskId === null || $taskId === '' || $userId === null || $userId === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and user ID are required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'DELETE FROM task_assignments
             WHERE task_id = :task_id
             AND user_id = :user_id'
        );

        $stmt->execute([
            'task_id' => (int) $taskId,
            'user_id' => (int) $userId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Assignment removed',
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getMyTasks(): void
{
    try {
        $user = checkRole([4]);

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $userId = (int) $user['user_id'];

        $stmt = $pdo->prepare(
            'SELECT t.task_id, t.title, t.description, t.status, t.priority, t.deadline
             FROM tasks t
             INNER JOIN task_assignments ta ON t.task_id = ta.task_id
             WHERE ta.user_id = :user_id
             ORDER BY t.created_at DESC'
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        echo json_encode([
            'status' => 'success',
            'tasks' => $stmt->fetchAll(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getAllTasks(): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $roleId = (int) $user['role_id'];
        $userId = (int) $user['user_id'];

        if (!in_array($roleId, [1, 2, 3, 4], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();

        if ($roleId === 4) {
            $stmt = $pdo->prepare(
                'SELECT
                    t.task_id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    u.name AS created_by_name,
                    GROUP_CONCAT(au.name) AS assigned_users
                 FROM tasks t
                 JOIN users u ON t.created_by = u.user_id
                 JOIN task_assignments ta ON t.task_id = ta.task_id
                 LEFT JOIN users au ON ta.user_id = au.user_id
                 WHERE ta.user_id = :user_id
                 GROUP BY t.task_id
                 ORDER BY t.created_at DESC'
            );
            $stmt->execute([
                'user_id' => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT
                    t.task_id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    u.name AS created_by_name,
                    GROUP_CONCAT(au.name) AS assigned_users
                 FROM tasks t
                 JOIN users u ON t.created_by = u.user_id
                 LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
                 LEFT JOIN users au ON ta.user_id = au.user_id
                 GROUP BY t.task_id
                 ORDER BY t.created_at DESC'
            );
            $stmt->execute();
        }

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tasks = array_map(function (array $row): array {
            $assignedUsers = $row['assigned_users'] ?? '';

            $row['assigned_users'] = $assignedUsers === ''
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $assignedUsers))));

            return $row;
        }, $tasks);

        echo json_encode([
            'status' => 'success',
            'tasks' => $tasks,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

function getTaskById($taskId): void
{
    try {
        $user = checkAuth();

        if ($user === null) {
            return;
        }

        $roleId = (int) $user['role_id'];
        $userId = (int) $user['user_id'];

        if (!in_array($roleId, [1, 2, 3, 4], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();

        if ($roleId === 4) {
            $assignmentCheck = $pdo->prepare(
                'SELECT task_id FROM task_assignments WHERE task_id = :task_id AND user_id = :user_id'
            );
            $assignmentCheck->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
            ]);

            if (!$assignmentCheck->fetch()) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Forbidden',
                ]);
                return;
            }
        }

        $stmt = $pdo->prepare(
            'SELECT
                t.task_id,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.deadline,
                u.name AS created_by_name,
                GROUP_CONCAT(au.name) AS assigned_users
             FROM tasks t
             JOIN users u ON t.created_by = u.user_id
             LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
             LEFT JOIN users au ON ta.user_id = au.user_id
             WHERE t.task_id = :task_id
             GROUP BY t.task_id
             LIMIT 1'
        );

        $stmt->execute(['task_id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found',
            ]);
            return;
        }

        $task['assigned_users'] = $task['assigned_users']
            ? array_map('trim', explode(',', $task['assigned_users']))
            : [];

        echo json_encode([
            'status' => 'success',
            'task' => $task,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
