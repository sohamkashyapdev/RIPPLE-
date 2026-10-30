-- =============================================
--  RIPPLE – Campus Communication Platform
--  Database Schema: ripple.sql
--  Compatible with: MySQL 5.7+ / MariaDB 10+
-- =============================================
--
--  HOW TO IMPORT:
--  1. Open phpMyAdmin → http://localhost/phpmyadmin
--  2. Click "New" to create a new database named: ripple
--  3. Select the ripple database
--  4. Click "Import" tab → Choose this file → Go
--
--  OR via command line:
--  mysql -u root -p < ripple.sql
-- =============================================

-- Create & select database
CREATE DATABASE IF NOT EXISTS `ripple`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `ripple`;

-- =============================================
--  TABLE: users
--  Stores registered campus users
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL                 COMMENT 'Full name of the user',
  `email`      VARCHAR(150) NOT NULL                 COMMENT 'Unique campus email',
  `password`   VARCHAR(255) NOT NULL                 COMMENT 'Plain text password (per project spec)',
  `role`       ENUM('student','faculty') NOT NULL
                            DEFAULT 'student'         COMMENT 'User role on campus',
  `created_at` DATETIME     NOT NULL
                            DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation timestamp',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ripple registered users';


-- =============================================
--  TABLE: posts
--  Stores all microthread posts
-- =============================================
CREATE TABLE IF NOT EXISTS `posts` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL                 COMMENT 'Author – foreign key to users.id',
  `content`    TEXT         NOT NULL                 COMMENT 'Post body (max ~1000 chars enforced in API)',
  `topic`      ENUM('general','academics','events','announcements')
                            NOT NULL DEFAULT 'general' COMMENT 'Topic category tag',
  `likes`      INT(11)      NOT NULL DEFAULT 0        COMMENT 'Like count (client-side toggling)',
  `created_at` DATETIME     NOT NULL
                            DEFAULT CURRENT_TIMESTAMP COMMENT 'Post creation timestamp',

  PRIMARY KEY (`id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_topic`      (`topic`),
  KEY `idx_created_at` (`created_at`),

  CONSTRAINT `fk_posts_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ripple microthread posts';


-- =============================================
--  NOTE: No seed data inserted.
--  All records will be added manually via
--  the platform UI or phpMyAdmin.
-- =============================================

-- You can manually insert a test user like this:
-- INSERT INTO users (name, email, password, role) VALUES ('Admin User', 'admin@campus.edu', 'admin123', 'faculty');

-- =============================================
--  TABLE: replies
--  Stores replies to posts
-- =============================================
CREATE TABLE IF NOT EXISTS `replies` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `post_id`    INT(11)      NOT NULL                 COMMENT 'Parent post',
  `user_id`    INT(11)      NOT NULL                 COMMENT 'Author',
  `content`    TEXT         NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_post_id`    (`post_id`),
  KEY `idx_user_id`    (`user_id`),

  CONSTRAINT `fk_replies_post`
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_replies_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Replies to microthread posts';


-- =============================================
--  TABLE: notifications
--  Stores per-user notifications
-- =============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL                 COMMENT 'Recipient user',
  `from_user_id` INT(11)    NOT NULL                 COMMENT 'Actor user',
  `type`       ENUM('reply','like','mention') NOT NULL DEFAULT 'reply',
  `post_id`    INT(11)      NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),

  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_from`
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_post`
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User notifications';
