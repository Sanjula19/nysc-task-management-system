-- NYSC TMS RBAC migration
-- Creates role catalog and many-to-many user role assignments.
-- Run on the `nysc_tms` database.

CREATE TABLE IF NOT EXISTS roles (
    role_id TINYINT UNSIGNED NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_id, role_name) VALUES
    (1, 'Chairman'),
    (2, 'Director'),
    (3, 'Deputy Director'),
    (4, 'Assistant Director')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name);

CREATE TABLE IF NOT EXISTS user_roles (
    user_role_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_role_id),
    UNIQUE KEY uq_user_roles_user_role (user_id, role_id),
    KEY idx_user_roles_role_id (role_id),
    CONSTRAINT fk_user_roles_users
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_user_roles_roles
        FOREIGN KEY (role_id) REFERENCES roles (role_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.user_id, u.role_id
FROM users u
INNER JOIN roles r ON r.role_id = u.role_id;

SET @fk_name := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @sql := IF(
    @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE users DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_name := (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role_id'
      AND INDEX_NAME <> 'PRIMARY'
    LIMIT 1
);

SET @sql := IF(
    @idx_name IS NOT NULL,
    CONCAT('ALTER TABLE users DROP INDEX `', @idx_name, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_role_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role_id'
);

SET @sql := IF(
    @has_role_column > 0,
    'ALTER TABLE users DROP COLUMN role_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
