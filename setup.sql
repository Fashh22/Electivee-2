-- CodeQuest Secure MVP Schema
-- Run this in phpMyAdmin SQL tab.

CREATE DATABASE IF NOT EXISTS `quiz_data`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `quiz_data`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `email_verification_tokens`;
DROP TABLE IF EXISTS `quiz_attempt_answers`;
DROP TABLE IF EXISTS `quiz_attempts`;
DROP TABLE IF EXISTS `subject_join_requests`;
DROP TABLE IF EXISTS `subject_enrollments`;
DROP TABLE IF EXISTS `matching_pairs`;
DROP TABLE IF EXISTS `answer_choices`;
DROP TABLE IF EXISTS `answers`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `quizzes`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(40) NULL,
  `address` VARCHAR(255) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `status` ENUM('pending', 'active', 'rejected', 'disabled') NOT NULL DEFAULT 'pending',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `approved_at` DATETIME NULL,
  `approved_by` INT UNSIGNED NULL,
  `password_changed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `subjects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `subject_code` VARCHAR(16) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_code` (`subject_code`),
  KEY `idx_subject_teacher` (`teacher_id`),
  CONSTRAINT `fk_subject_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `quizzes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `quiz_type` ENUM('multiple_choice', 'true_false', 'short_answer', 'mixed') NOT NULL DEFAULT 'multiple_choice',
  `time_limit` INT UNSIGNED NOT NULL DEFAULT 0,
  `attempt_limit` INT UNSIGNED NOT NULL DEFAULT 1,
  `total_points` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quizzes_subject` (`subject_id`),
  KEY `idx_quizzes_teacher` (`teacher_id`),
  CONSTRAINT `fk_quizzes_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quizzes_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('multiple_choice', 'true_false', 'short_answer') NOT NULL DEFAULT 'multiple_choice',
  `points` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_quiz` (`quiz_id`),
  CONSTRAINT `fk_questions_quiz`
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `answer_choices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `choice_text` TEXT NOT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_answer_choices_question` (`question_id`),
  CONSTRAINT `fk_answer_choices_question`
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `answer_text` TEXT NOT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_answers_question` (`question_id`),
  CONSTRAINT `fk_answers_question`
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `matching_pairs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `left_item` VARCHAR(255) NOT NULL,
  `right_item` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_matching_pairs_question` (`question_id`),
  CONSTRAINT `fk_matching_pairs_question`
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `subject_enrollments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `status` ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
  `enrolled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_student` (`subject_id`, `student_id`),
  KEY `idx_enrollment_student` (`student_id`),
  CONSTRAINT `fk_enrollment_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_student`
    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `subject_join_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  `reviewed_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_request` (`subject_id`, `student_id`),
  KEY `idx_requests_status` (`status`),
  CONSTRAINT `fk_request_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_request_student`
    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_request_reviewer`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `quiz_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `attempt_no` INT UNSIGNED NOT NULL DEFAULT 1,
  `score` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `total_points` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` DATETIME NULL,
  `is_finished` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quiz_attempt_no` (`quiz_id`, `student_id`, `attempt_no`),
  KEY `idx_quiz_attempt_student` (`student_id`),
  KEY `idx_quiz_attempt_quiz` (`quiz_id`),
  CONSTRAINT `fk_attempt_quiz`
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempt_student`
    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `quiz_attempt_answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `choice_id` INT UNSIGNED NULL,
  `short_answer` TEXT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  `awarded_points` DECIMAL(8,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_attempt_answer_question` (`question_id`),
  CONSTRAINT `fk_attempt_answers_attempt`
    FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempt_answers_question`
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempt_answers_choice`
    FOREIGN KEY (`choice_id`) REFERENCES `answer_choices` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `content` TEXT NOT NULL,
  `target_user_id` INT UNSIGNED NULL,
  `target_role` ENUM('admin', 'teacher', 'student', 'all') NOT NULL DEFAULT 'all',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_target_user` (`target_user_id`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `email_verification_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verification_token_hash` (`token_hash`),
  KEY `idx_email_verification_user` (`user_id`),
  CONSTRAINT `fk_email_token_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  CONSTRAINT `fk_password_reset_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED NULL,
  `action` VARCHAR(120) NOT NULL,
  `context_type` VARCHAR(80) NULL,
  `context_id` INT UNSIGNED NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_actor` (`actor_user_id`),
  KEY `idx_activity_action` (`action`),
  CONSTRAINT `fk_activity_actor`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users`
(`name`, `email`, `password_hash`, `role`, `status`, `is_active`, `is_email_verified`, `email_verified_at`, `is_approved`, `approved_at`, `password_changed_at`)
VALUES
('System Admin', 'admin@wmsu.edu.ph', '$2y$10$16MwjJlOA8z0v5DlyYv1KuM9bt2wEKfQGa6J6Lqqg6NfDfRwUQfRy', 'admin', 'active', 1, 1, NOW(), 1, NOW(), NOW()),
('Sample Teacher', 'teacher@wmsu.edu.ph', '$2y$10$16MwjJlOA8z0v5DlyYv1KuM9bt2wEKfQGa6J6Lqqg6NfDfRwUQfRy', 'teacher', 'active', 1, 1, NOW(), 1, NOW(), NOW()),
('Sample Student', 'student@wmsu.edu.ph', '$2y$10$16MwjJlOA8z0v5DlyYv1KuM9bt2wEKfQGa6J6Lqqg6NfDfRwUQfRy', 'student', 'active', 1, 1, NOW(), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Upgrade existing installs (run once if `users` has no phone/address yet):
-- ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(40) NULL DEFAULT NULL AFTER `email`;
-- ALTER TABLE `users` ADD COLUMN `address` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`;
