-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: hscDB.db
-- Generation Time: Feb 03, 2026 at 12:48 PM
-- Server version: 10.3.29-MariaDB-1:10.3.29+maria~xenial
-- PHP Version: 8.3.27-nfsn1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `supercontest2025`
--

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` bigint(20) NOT NULL,
  `season_year` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `kickoff_utc` datetime DEFAULT NULL,
  `home_team` char(3) NOT NULL,
  `away_team` char(3) NOT NULL,
  `state` enum('pre','in_progress','final') DEFAULT 'pre',
  `home_score` int(11) DEFAULT 0,
  `away_score` int(11) DEFAULT 0,
  `winner_team` char(3) DEFAULT NULL,
  `period` tinyint(4) DEFAULT NULL,
  `clock_seconds` int(11) DEFAULT NULL,
  `last_update_utc` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `lines`
--

CREATE TABLE `lines` (
  `id` bigint(20) NOT NULL,
  `game_id` bigint(20) NOT NULL,
  `fav_team` char(3) NOT NULL,
  `dog_team` char(3) NOT NULL,
  `spread` decimal(4,1) NOT NULL,
  `source` varchar(40) DEFAULT 'westgate',
  `posted_at_utc` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_jobs`
--

CREATE TABLE `maintenance_jobs` (
  `job_name` varchar(64) NOT NULL,
  `last_run_utc` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `picks`
--

CREATE TABLE `picks` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `season_year` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `submitted_at_utc` datetime NOT NULL,
  `admin_override` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `pick_selections`
--

CREATE TABLE `pick_selections` (
  `id` bigint(20) NOT NULL,
  `pick_id` bigint(20) NOT NULL,
  `game_id` bigint(20) NOT NULL,
  `team_abbr` char(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` bigint(20) NOT NULL,
  `season_year` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `wins` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `pushes` int(11) DEFAULT 0,
  `points` decimal(4,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `standings`
--

CREATE TABLE `standings` (
  `id` bigint(20) NOT NULL,
  `season_year` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `total_wins` int(11) DEFAULT 0,
  `total_losses` int(11) DEFAULT 0,
  `total_pushes` int(11) DEFAULT 0,
  `total_points` decimal(5,1) DEFAULT 0.0,
  `last_updated_utc` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_game_results_with_line`
-- (See below for the actual view)
--
CREATE TABLE `v_game_results_with_line` (
`game_id` bigint(20)
,`season_year` int(11)
,`week_number` int(11)
,`kickoff_utc` datetime
,`home_team` char(3)
,`away_team` char(3)
,`home_score` int(11)
,`away_score` int(11)
,`fav_team` char(3)
,`dog_team` char(3)
,`spread` decimal(4,1)
,`ats_winner` varchar(4)
);

-- --------------------------------------------------------

--
-- Table structure for table `weeks`
--

CREATE TABLE `weeks` (
  `id` bigint(20) NOT NULL,
  `season_year` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `lock_at_utc` datetime NOT NULL,
  `visible_after_lock` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lines`
--
ALTER TABLE `lines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_jobs`
--
ALTER TABLE `maintenance_jobs`
  ADD PRIMARY KEY (`job_name`);

--
-- Indexes for table `picks`
--
ALTER TABLE `picks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_week` (`user_id`,`season_year`,`week_number`);

--
-- Indexes for table `pick_selections`
--
ALTER TABLE `pick_selections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pick_game` (`pick_id`,`game_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `season_year` (`season_year`,`week_number`,`user_id`);

--
-- Indexes for table `standings`
--
ALTER TABLE `standings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `season_year` (`season_year`,`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `weeks`
--
ALTER TABLE `weeks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `season_year` (`season_year`,`week_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lines`
--
ALTER TABLE `lines`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `picks`
--
ALTER TABLE `picks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pick_selections`
--
ALTER TABLE `pick_selections`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `standings`
--
ALTER TABLE `standings`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weeks`
--
ALTER TABLE `weeks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_game_results_with_line`
--
DROP TABLE IF EXISTS `v_game_results_with_line`;

CREATE ALGORITHM=UNDEFINED DEFINER=`skyhook`@`%` SQL SECURITY DEFINER VIEW `v_game_results_with_line`  AS SELECT `g`.`id` AS `game_id`, `g`.`season_year` AS `season_year`, `g`.`week_number` AS `week_number`, `g`.`kickoff_utc` AS `kickoff_utc`, `g`.`home_team` AS `home_team`, `g`.`away_team` AS `away_team`, `g`.`home_score` AS `home_score`, `g`.`away_score` AS `away_score`, `l`.`fav_team` AS `fav_team`, `l`.`dog_team` AS `dog_team`, `l`.`spread` AS `spread`, CASE WHEN `g`.`state` <> 'final' THEN NULL WHEN `l`.`fav_team` = `g`.`home_team` AND `g`.`home_score` - `g`.`away_score` > `l`.`spread` THEN `l`.`fav_team` WHEN `l`.`fav_team` = `g`.`away_team` AND `g`.`away_score` - `g`.`home_score` > `l`.`spread` THEN `l`.`fav_team` WHEN `l`.`fav_team` = `g`.`home_team` AND `g`.`away_score` - `g`.`home_score` > -`l`.`spread` THEN `l`.`dog_team` WHEN `l`.`fav_team` = `g`.`away_team` AND `g`.`home_score` - `g`.`away_score` > -`l`.`spread` THEN `l`.`dog_team` WHEN abs(`g`.`home_score` - `g`.`away_score` - `l`.`spread`) < 0.001 THEN 'PUSH' ELSE 'PUSH' END AS `ats_winner` FROM (`games` `g` join `lines` `l` on(`g`.`id` = `l`.`game_id`))  ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `picks`
--
ALTER TABLE `picks`
  ADD CONSTRAINT `picks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pick_selections`
--
ALTER TABLE `pick_selections`
  ADD CONSTRAINT `fk_ps_pick` FOREIGN KEY (`pick_id`) REFERENCES `picks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
