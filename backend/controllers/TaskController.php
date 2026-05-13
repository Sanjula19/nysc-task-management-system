<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

function getTaskJsonInput(): array
{
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    return is_array($data) ? $data : [];
}

function buildTaskCollectionSql(bool $assignedOnly, bool $filterById = false): array
{
    $sql = '
        SELECT
            t.task_id,
            t.title,
            t.description,
            t.priority,
            t.deadline,
            t.status,
            t.created_at,
            t.created_by,
            creator.name AS created_by_name,
            COALESCE(GROUP_CONCAT(DISTINCT assignee.name ORDER BY assignee.name SEPARATOR ", "), "") AS assigned_to,
            COALESCE(GROUP_CONCAT(DISTINCT assignee.user_id ORDER BY assignee.user_id SEPARATOR ","), "") AS assigned_user_ids
        FROM tasks t
        INNER JOIN users creator ON creator.user_id = t.created_by
        LEFT JOIN task_assignments task_map ON task_map.task_id = t.task_id
        LEFT JOIN users assignee ON assignee.user_id = task_map.user_id
        WHERE 1 = 1
    ';

    $params = [];

    if ($filterById) {
        $sql .= ' AND t.task_id = :task_id';
    }

    if ($assignedOnly) {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM task_assignments assigned_filter
                WHERE assigned_filter.task_id = t.task_id
                  AND assigned_filter.user_id = :user_id
            )
        ';
    }

    $sql .= '
        GROUP BY
            t.task_id,
            t.title,
            t.description,
            t.priority,
            t.deadline,
            t.status,
            t.created_at,
            t.created_by,
            creator.name
        ORDER BY t.created_at DESC
    ';

    return [
        'sql' => $sql,
        'params' => $params,
    ];
}

function executeTaskCollection(PDO $pdo, array $user, ?int $taskId = null): array
{
    $assignedOnly = ((int) $user['role_id']) === 4;
    $query = buildTaskCollectionSql($assignedOnly, $taskId !== null);
    $sql = $query['sql'];
    $params = $query['params'];

    if ($taskId !== null) {
        $params['task_id'] = $taskId;
    }

    if ($assignedOnly) {
        $params['user_id'] = (int) $user['user_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatTaskRows(array $rows): array
{
    return array_map(static function (array $row): array {
        $assignedUsers = trim((string) ($row['assigned_to'] ?? ''));
        $assignedUserIds = trim((string) ($row['assigned_user_ids'] ?? ''));

        $row['assigned_to'] = $assignedUsers;
        $row['assigned_users'] = $assignedUsers === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $assignedUsers))));
        $row['assigned_user_ids'] = $assignedUserIds === ''
            ? []
            : array_values(array_filter(array_map('intval', explode(',', $assignedUserIds))));

        return $row;
    }, $rows);
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
        $priority = strtoupper(trim($data['priority'] ?? ''));
        $deadline = trim($data['deadline'] ?? '');
        $status = 'PENDING';
        $createdBy = (int) $user['user_id'];

        if ($title === '' || $description === '' || $priority === '' || $deadline === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Title, description, priority, and deadline are required.',
            ]);
            return;
        }

        $allowedPriorities = ['LOW', 'MEDIUM', 'HIGH'];

        if (!in_array($priority, $allowedPriorities, true)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid priority value.',
            ]);
            return;
        }

        $deadlineDate = DateTime::createFromFormat('Y-m-d', $deadline);

        if (!$deadlineDate || $deadlineDate->format('Y-m-d') !== $deadline) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid deadline value.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tasks (title, description, priority, deadline, status, created_at, created_by)
             VALUES (:title, :description, :priority, :deadline, :status, NOW(), :created_by)'
        );

        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'deadline' => $deadline,
            'status' => $status,
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
    getAllTasks();
}

function updateTaskStatus(): void
{
    $user = checkRole([4]);

    if ($user === null) {
        return;
    }

    try {
        $pdo = getPDO();
        $input = getTaskJsonInput();

        $taskId = (int) ($input['task_id'] ?? 0);
        $status = strtoupper(trim($input['status'] ?? ''));

        if ($taskId <= 0 || $status === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing task_id or status.',
            ]);
            return;
        }

        $allowedStatuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];

        if (!in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid status.',
            ]);
            return;
        }

        $assignmentCheck = $pdo->prepare(
            'SELECT task_id
             FROM task_assignments
             WHERE task_id = :task_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $assignmentCheck->execute([
            'task_id' => $taskId,
            'user_id' => (int) $user['user_id'],
        ]);

        if (!$assignmentCheck->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $check = $pdo->prepare('SELECT task_id FROM tasks WHERE task_id = :task_id LIMIT 1');
        $check->execute([
            'task_id' => $taskId,
        ]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found.',
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
            'message' => 'Task status updated.',
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

        if ($user === null) {
            return;
        }

        if (!in_array((int) $user['role_id'], [1, 2], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();
        $data = getTaskJsonInput();
        $taskId = (int) ($data['task_id'] ?? 0);

        if ($taskId <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID is required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = :task_id');
        $stmt->execute([
            'task_id' => $taskId,
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
    $user = checkRole([1]);

    if ($user === null) {
        return;
    }

    try {
        $pdo = getPDO();
        $data = getTaskJsonInput();

        $taskId = (int) ($data['task_id'] ?? 0);
        $userIds = $data['user_ids'] ?? [];

        if ($taskId <= 0 || !is_array($userIds) || empty($userIds)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and user_ids are required.',
            ]);
            return;
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, static function (int $userId): bool {
            return $userId > 0;
        }));

        if (empty($userIds)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID and user_ids are required.',
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

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $userCheckStmt = $pdo->prepare(
            "SELECT u.user_id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.user_id
             WHERE ur.role_id = 4
               AND u.user_id IN ({$placeholders})"
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

        $pdo->beginTransaction();

        $deleteAssignments = $pdo->prepare('DELETE FROM task_assignments WHERE task_id = :task_id');
        $deleteAssignments->execute([
            'task_id' => $taskId,
        ]);

        $insertStmt = $pdo->prepare(
            'INSERT INTO task_assignments (task_id, user_id, assigned_at)
             VALUES (:task_id, :user_id, NOW())'
        );

        foreach ($userIds as $userId) {
            $insertStmt->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Task assigned successfully.',
            'assigned_count' => count($userIds),
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
        $taskId = (int) ($_GET['task_id'] ?? 0);

        if ($taskId <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task ID is required.',
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                u.user_id,
                u.name,
                u.email,
                COALESCE(up.district, "") AS district,
                up.profile_photo,
                r.role_id,
                r.role_name
             FROM task_assignments ta
             INNER JOIN users u ON ta.user_id = u.user_id
             INNER JOIN user_roles ur ON ur.user_id = u.user_id
             INNER JOIN roles r ON r.role_id = ur.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.user_id
             WHERE ta.task_id = :task_id
               AND ur.role_id = 4
             ORDER BY u.name ASC'
        );

        $stmt->execute([
            'task_id' => $taskId,
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

function removeAssignment(): void
{
    try {
        $user = checkRole([1]);

        if ($user === null) {
            return;
        }

        $pdo = getPDO();
        $data = getTaskJsonInput();

        $taskId = (int) ($data['task_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);

        if ($taskId <= 0 || $userId <= 0) {
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
            'task_id' => $taskId,
            'user_id' => $userId,
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Assignment removed.',
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
        $rows = executeTaskCollection($pdo, $user);

        echo json_encode([
            'status' => 'success',
            'tasks' => formatTaskRows($rows),
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

        if (!in_array((int) $user['role_id'], [1, 2, 3, 4], true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
            return;
        }

        $pdo = getPDO();
        $rows = executeTaskCollection($pdo, $user);

        echo json_encode([
            'status' => 'success',
            'tasks' => formatTaskRows($rows),
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

        $taskId = (int) $taskId;

        if ($taskId <= 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid task ID.',
            ]);
            return;
        }

        $pdo = getPDO();

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

        if ((int) $user['role_id'] === 4) {
            $assignmentCheck = $pdo->prepare(
                'SELECT task_id
                 FROM task_assignments
                 WHERE task_id = :task_id
                   AND user_id = :user_id
                 LIMIT 1'
            );
            $assignmentCheck->execute([
                'task_id' => $taskId,
                'user_id' => (int) $user['user_id'],
            ]);

            if (!$assignmentCheck->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Forbidden',
                ]);
                return;
            }
        }

        $rows = executeTaskCollection($pdo, $user, $taskId);
        $task = $rows[0] ?? null;

        if (!$task) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Task not found.',
            ]);
            return;
        }

        $formatted = formatTaskRows([$task]);

        echo json_encode([
            'status' => 'success',
            'task' => $formatted[0],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
