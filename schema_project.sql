SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `opencollab_music`;
USE `opencollab_music`;

DROP TABLE IF EXISTS `collab_requests`;
CREATE TABLE `collab_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `song_id` bigint unsigned NOT NULL,
  `requester_user_id` bigint unsigned NOT NULL,
  `target_user_id` bigint unsigned NOT NULL,
  `message` text,
  `status` enum('PENDING','ACCEPTED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `response_reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collab_pending_unique` (`song_id`,`requester_user_id`,`target_user_id`,`status`),
  KEY `idx_collab_target_status` (`target_user_id`,`status`,`created_at`),
  KEY `idx_collab_requester_status` (`requester_user_id`,`status`,`created_at`),
  KEY `idx_collab_song` (`song_id`),
  CONSTRAINT `fk_collab_requester` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collab_song` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_collab_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_requester_not_target` CHECK ((`requester_user_id` <> `target_user_id`))
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `genres`;
CREATE TABLE `genres` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_genres_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `song_genres`;
CREATE TABLE `song_genres` (
  `song_id` bigint unsigned NOT NULL,
  `genre_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`song_id`,`genre_id`),
  KEY `idx_songgenres_song` (`song_id`),
  KEY `idx_songgenres_genre` (`genre_id`),
  CONSTRAINT `fk_songgenres_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_songgenres_song` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `songs`;
CREATE TABLE `songs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint unsigned NOT NULL,
  `title` varchar(140) NOT NULL,
  `description` text,
  `audio_url` varchar(500) DEFAULT NULL,
  `youtube_url` varchar(500) DEFAULT NULL,
  `cover_image_url` varchar(500) DEFAULT NULL,
  `visibility` enum('PUBLIC','PRIVATE') NOT NULL DEFAULT 'PUBLIC',
  `allow_requests` tinyint(1) NOT NULL DEFAULT '1',
  `is_portfolio` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_songs_owner` (`owner_user_id`),
  KEY `idx_songs_visibility` (`visibility`),
  CONSTRAINT `fk_songs_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` smallint unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by_user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_userroles_user` (`user_id`),
  KEY `idx_userroles_role` (`role_id`),
  KEY `fk_userroles_assigned_by` (`assigned_by_user_id`),
  CONSTRAINT `fk_userroles_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_userroles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_userroles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `display_name` varchar(80) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `bio` text,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_tiktok` varchar(255) DEFAULT NULL,
  `social_youtube` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

