-- phpMyAdmin SQL Dump
-- version 5.2.2deb1+deb13u1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : dim. 05 avr. 2026 à 20:23
-- Version du serveur : 11.8.3-MariaDB-0+deb13u1 from Debian
-- Version de PHP : 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de données : `examsappv3`
--

-- --------------------------------------------------------

--
-- Structure de la table `answer_options`
--

CREATE TABLE `answer_options` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `explanation` varchar(255) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `class_students`
--

CREATE TABLE `class_students` (
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exams`
--

CREATE TABLE `exams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `title` varchar(150) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 45,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `allow_print` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attempt_token` char(64) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `exam_id` bigint(20) UNSIGNED NOT NULL,
  `started_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('in_progress','pending_sync','finalizing_offline','submitted','expired') NOT NULL DEFAULT 'in_progress',
  `last_sync_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_token` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exam_monitoring`
--

CREATE TABLE `exam_monitoring` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_token` varchar(128) DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `last_heartbeat_at` datetime DEFAULT NULL,
  `status` enum('active','idle','offline','suspicious') DEFAULT 'active',
  `warnings` int(11) DEFAULT 0,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exam_results`
--

CREATE TABLE `exam_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_exam_id` bigint(20) UNSIGNED NOT NULL,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `answered_questions` int(11) NOT NULL DEFAULT 0,
  `correct_questions` int(11) NOT NULL DEFAULT 0,
  `wrong_questions` int(11) NOT NULL DEFAULT 0,
  `blank_questions` int(11) NOT NULL DEFAULT 0,
  `final_score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lab_computers`
--

CREATE TABLE `lab_computers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `hostname` varchar(100) NOT NULL,
  `ip_lan` varchar(45) DEFAULT NULL,
  `ip_wifi` varchar(45) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `room_name` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `login_attempt_alerts`
--

CREATE TABLE `login_attempt_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `username_attempted` varchar(50) NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `existing_session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `existing_computer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `existing_ip` varchar(45) DEFAULT NULL,
  `attempted_computer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attempted_ip` varchar(45) NOT NULL,
  `attempted_network_type` enum('lan','wifi','unknown') NOT NULL DEFAULT 'unknown',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('refused','suspect','validated','ignored') NOT NULL DEFAULT 'refused',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pdf_jobs`
--

CREATE TABLE `pdf_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_type` enum('student_ticket','exam_sheet','exam_result','bulk_export') NOT NULL,
  `reference_type` varchar(50) NOT NULL,
  `reference_id` bigint(20) UNSIGNED NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `output_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `questions`
--

CREATE TABLE `questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `exam_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `question_text` text NOT NULL,
  `points` decimal(6,2) NOT NULL DEFAULT 0.00,
  `type` varchar(50) NOT NULL,
  `num` int(11) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `system_flags`
--

CREATE TABLE `system_flags` (
  `key` varchar(50) NOT NULL,
  `value` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `numero` int(11) NOT NULL DEFAULT 0,
  `code_massar` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `secret` varchar(32) NOT NULL DEFAULT '',
  `can_login` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `nom` varchar(100) NOT NULL DEFAULT '',
  `prenom` varchar(100) NOT NULL DEFAULT '',
  `nom_ar` varchar(100) NOT NULL DEFAULT '',
  `prenom_ar` varchar(100) NOT NULL DEFAULT '',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_answers`
--

CREATE TABLE `user_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_exam_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `question_num` int(11) NOT NULL,
  `awarded_points` decimal(6,2) NOT NULL DEFAULT 0.00,
  `answer_text` mediumtext DEFAULT NULL,
  `correct_answer_text` mediumtext DEFAULT NULL,
  `question_snapshot` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_answers_draft`
--

CREATE TABLE `user_answers_draft` (
  `id` bigint(20) NOT NULL,
  `attempt_token` char(64) NOT NULL,
  `question_id` bigint(20) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_exams`
--

CREATE TABLE `user_exams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `exam_id` bigint(20) UNSIGNED NOT NULL,
  `is_absent` tinyint(1) NOT NULL DEFAULT 1,
  `is_cheat` tinyint(1) NOT NULL DEFAULT 0,
  `is_retake` tinyint(1) NOT NULL DEFAULT 0,
  `score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `duration_seconds` int(11) NOT NULL DEFAULT 0,
  `status` enum('assigned','started','submitted','corrected','cancelled') NOT NULL DEFAULT 'assigned',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_token` char(64) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `computer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `network_type` enum('lan','wifi','unknown') NOT NULL DEFAULT 'unknown',
  `status` enum('active','closed','expired','refused') NOT NULL DEFAULT 'active',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `answer_options`
--
ALTER TABLE `answer_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_answer_options_question` (`question_id`),
  ADD KEY `idx_answer_options_question_sort` (`question_id`,`sort_order`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_classes_name_year` (`name`,`school_year`),
  ADD KEY `idx_classes_active` (`is_active`);

--
-- Index pour la table `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`class_id`,`user_id`),
  ADD KEY `idx_class_students_user` (`user_id`);

--
-- Index pour la table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_exams_code` (`code`),
  ADD KEY `idx_exams_active` (`is_active`);

--
-- Index pour la table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attempt_token` (`attempt_token`),
  ADD KEY `idx_user_exam` (`user_id`,`exam_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_token` (`attempt_token`);

--
-- Index pour la table `exam_monitoring`
--
ALTER TABLE `exam_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `attempt_token` (`attempt_token`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_exam_results_user_exam` (`user_exam_id`);

--
-- Index pour la table `lab_computers`
--
ALTER TABLE `lab_computers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lab_computers_name` (`name`),
  ADD UNIQUE KEY `uk_lab_computers_hostname` (`hostname`),
  ADD UNIQUE KEY `uk_lab_computers_ip_lan` (`ip_lan`),
  ADD UNIQUE KEY `uk_lab_computers_ip_wifi` (`ip_wifi`),
  ADD KEY `idx_lab_computers_active` (`is_active`);

--
-- Index pour la table `login_attempt_alerts`
--
ALTER TABLE `login_attempt_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alerts_class` (`class_id`),
  ADD KEY `fk_alerts_existing_session` (`existing_session_id`),
  ADD KEY `fk_alerts_existing_computer` (`existing_computer_id`),
  ADD KEY `fk_alerts_attempted_computer` (`attempted_computer_id`),
  ADD KEY `idx_alerts_user` (`user_id`),
  ADD KEY `idx_alerts_attempted_at` (`attempted_at`),
  ADD KEY `idx_alerts_status_time` (`status`,`attempted_at`);

--
-- Index pour la table `pdf_jobs`
--
ALTER TABLE `pdf_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pdf_jobs_status_created` (`status`,`created_at`),
  ADD KEY `idx_pdf_jobs_ref` (`reference_type`,`reference_id`),
  ADD KEY `idx_pdf_jobs_requested_by` (`requested_by`);

--
-- Index pour la table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_questions_exam_num` (`exam_id`,`num`),
  ADD KEY `idx_questions_exam_sort` (`exam_id`,`sort_order`),
  ADD KEY `idx_questions_category` (`category_id`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_code` (`code`);

--
-- Index pour la table `system_flags`
--
ALTER TABLE `system_flags`
  ADD PRIMARY KEY (`key`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_code_massar` (`code_massar`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_can_login` (`can_login`),
  ADD KEY `idx_users_numero` (`numero`);

--
-- Index pour la table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_answers_exam_question` (`user_exam_id`,`question_id`),
  ADD KEY `fk_user_answers_question` (`question_id`),
  ADD KEY `idx_user_answers_user_exam` (`user_exam_id`),
  ADD KEY `idx_user_answers_question_num` (`user_exam_id`,`question_num`);

--
-- Index pour la table `user_answers_draft`
--
ALTER TABLE `user_answers_draft`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt_question` (`attempt_token`,`question_id`),
  ADD KEY `idx_attempt` (`attempt_token`);

--
-- Index pour la table `user_exams`
--
ALTER TABLE `user_exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_exams_triplet` (`user_id`,`class_id`,`exam_id`),
  ADD KEY `idx_user_exams_class_exam` (`class_id`,`exam_id`),
  ADD KEY `idx_user_exams_exam_status` (`exam_id`,`status`),
  ADD KEY `idx_user_exams_user` (`user_id`),
  ADD KEY `idx_user_exams_user_status_started` (`user_id`,`status`,`started_at`),
  ADD KEY `idx_user_exams_user_cheat` (`user_id`,`is_cheat`);

--
-- Index pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_sessions_token` (`session_token`),
  ADD KEY `fk_user_sessions_class` (`class_id`),
  ADD KEY `idx_user_sessions_user` (`user_id`),
  ADD KEY `idx_user_sessions_computer` (`computer_id`),
  ADD KEY `idx_user_sessions_status_activity` (`status`,`last_activity_at`),
  ADD KEY `idx_user_sessions_user_status` (`user_id`,`status`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `answer_options`
--
ALTER TABLE `answer_options`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exam_monitoring`
--
ALTER TABLE `exam_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lab_computers`
--
ALTER TABLE `lab_computers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `login_attempt_alerts`
--
ALTER TABLE `login_attempt_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `pdf_jobs`
--
ALTER TABLE `pdf_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_answers_draft`
--
ALTER TABLE `user_answers_draft`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_exams`
--
ALTER TABLE `user_exams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `answer_options`
--
ALTER TABLE `answer_options`
  ADD CONSTRAINT `fk_answer_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `fk_class_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_class_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `exam_results`
--
ALTER TABLE `exam_results`
  ADD CONSTRAINT `fk_exam_results_user_exam` FOREIGN KEY (`user_exam_id`) REFERENCES `user_exams` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `login_attempt_alerts`
--
ALTER TABLE `login_attempt_alerts`
  ADD CONSTRAINT `fk_alerts_attempted_computer` FOREIGN KEY (`attempted_computer_id`) REFERENCES `lab_computers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_alerts_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_alerts_existing_computer` FOREIGN KEY (`existing_computer_id`) REFERENCES `lab_computers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_alerts_existing_session` FOREIGN KEY (`existing_session_id`) REFERENCES `user_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_alerts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `pdf_jobs`
--
ALTER TABLE `pdf_jobs`
  ADD CONSTRAINT `fk_pdf_jobs_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `user_answers`
--
ALTER TABLE `user_answers`
  ADD CONSTRAINT `fk_user_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_answers_user_exam` FOREIGN KEY (`user_exam_id`) REFERENCES `user_exams` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_exams`
--
ALTER TABLE `user_exams`
  ADD CONSTRAINT `fk_user_exams_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_exams_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_exams_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_sessions_computer` FOREIGN KEY (`computer_id`) REFERENCES `lab_computers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
