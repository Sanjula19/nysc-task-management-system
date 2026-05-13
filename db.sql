CREATE DATABASE IF NOT EXISTS nysc_tms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nysc_tms;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS task_assignments;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
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

CREATE TABLE users (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_role_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_role_id),
    UNIQUE KEY uq_user_roles_user_role (user_id, role_id),
    KEY idx_user_roles_user_id (user_id),
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

CREATE TABLE user_profiles (
    profile_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    district VARCHAR(150) NOT NULL DEFAULT '',
    profile_photo TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (profile_id),
    UNIQUE KEY uq_user_profiles_user_id (user_id),
    CONSTRAINT fk_user_profiles_users
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    task_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH') NOT NULL DEFAULT 'MEDIUM',
    deadline DATE NOT NULL,
    status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED') NOT NULL DEFAULT 'PENDING',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id),
    KEY idx_tasks_created_by (created_by),
    KEY idx_tasks_status (status),
    KEY idx_tasks_deadline (deadline),
    CONSTRAINT fk_tasks_users
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_assignments (
    task_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_assignment_id),
    UNIQUE KEY uq_task_assignments_task_user (task_id, user_id),
    KEY idx_task_assignments_user_id (user_id),
    CONSTRAINT fk_task_assignments_tasks
        FOREIGN KEY (task_id) REFERENCES tasks (task_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_task_assignments_users
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    comment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id),
    KEY idx_comments_task_id (task_id),
    KEY idx_comments_user_id (user_id),
    CONSTRAINT fk_comments_tasks
        FOREIGN KEY (task_id) REFERENCES tasks (task_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_comments_users
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
