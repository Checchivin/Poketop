-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 31, 2026 at 10:12 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `poketop`
--

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `join_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`id`, `name`, `join_code`, `created_at`) VALUES
(3, 'Javi - Campaign', '0D964A', '2025-08-08 23:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_caught`
--

CREATE TABLE `campaign_caught` (
  `encounter_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `caught_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_caught`
--

INSERT INTO `campaign_caught` (`encounter_id`, `user_id`, `caught_at`) VALUES
(22, 3, '2025-08-08 23:45:10'),
(22, 4, '2025-08-08 23:45:10'),
(22, 5, '2025-08-08 23:45:10'),
(23, 3, '2025-08-09 00:01:45'),
(23, 4, '2025-08-09 00:01:45'),
(23, 5, '2025-08-09 00:01:45'),
(24, 3, '2025-08-09 00:17:19'),
(24, 4, '2025-08-09 00:17:19'),
(24, 5, '2025-08-09 00:17:19'),
(26, 3, '2025-08-09 00:36:31'),
(26, 4, '2025-08-09 00:36:31'),
(26, 5, '2025-08-09 00:36:31'),
(27, 3, '2025-08-09 01:01:18'),
(27, 4, '2025-08-09 01:01:18'),
(27, 5, '2025-08-09 01:01:18'),
(28, 3, '2025-08-09 01:18:07'),
(28, 4, '2025-08-09 01:18:07'),
(28, 5, '2025-08-09 01:18:07'),
(29, 3, '2025-08-09 01:32:13'),
(29, 4, '2025-08-09 01:32:13'),
(29, 5, '2025-08-09 01:32:13'),
(31, 3, '2025-08-09 01:46:06'),
(31, 4, '2025-08-09 01:46:06'),
(31, 5, '2025-08-09 01:46:06'),
(34, 3, '2025-08-30 00:04:32'),
(34, 4, '2025-08-30 00:04:32'),
(34, 5, '2025-08-30 00:04:32'),
(36, 3, '2025-08-30 00:35:24'),
(36, 4, '2025-08-30 00:35:24'),
(36, 5, '2025-08-30 00:35:24'),
(38, 3, '2025-08-30 00:55:26'),
(38, 4, '2025-08-30 00:55:26'),
(38, 5, '2025-08-30 00:55:26'),
(40, 3, '2025-08-30 01:14:53'),
(40, 4, '2025-08-30 01:14:53'),
(40, 5, '2025-08-30 01:14:53'),
(41, 3, '2025-08-30 01:43:26'),
(41, 4, '2025-08-30 01:43:26'),
(41, 5, '2025-08-30 01:43:26'),
(42, 3, '2025-08-30 02:04:07'),
(42, 4, '2025-08-30 02:04:07'),
(42, 5, '2025-08-30 02:04:07'),
(43, 3, '2025-08-30 02:18:54'),
(43, 4, '2025-08-30 02:18:54'),
(43, 5, '2025-08-30 02:18:54'),
(44, 3, '2025-08-30 02:44:31'),
(44, 4, '2025-08-30 02:44:31'),
(44, 5, '2025-08-30 02:44:31'),
(57, 3, '2025-10-04 00:44:49'),
(57, 4, '2025-10-04 00:44:49'),
(57, 5, '2025-10-04 00:44:49'),
(58, 3, '2025-10-04 01:05:34'),
(58, 4, '2025-10-04 01:05:34'),
(58, 5, '2025-10-04 01:05:34'),
(60, 3, '2025-10-04 01:34:42'),
(60, 4, '2025-10-04 01:34:42'),
(60, 5, '2025-10-04 01:34:42'),
(61, 3, '2025-11-22 00:19:34'),
(61, 4, '2025-11-22 00:19:34'),
(61, 5, '2025-11-22 00:19:34'),
(64, 3, '2025-11-22 01:59:36'),
(64, 4, '2025-11-22 01:59:36'),
(64, 5, '2025-11-22 01:59:36'),
(64, 6, '2025-11-22 01:59:36'),
(66, 3, '2026-01-10 01:07:18'),
(66, 4, '2026-01-10 01:07:18'),
(66, 5, '2026-01-10 01:07:18'),
(67, 3, '2026-01-10 01:28:04'),
(67, 4, '2026-01-10 01:28:04'),
(67, 5, '2026-01-10 01:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_participants`
--

CREATE TABLE `campaign_participants` (
  `campaign_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_participants`
--

INSERT INTO `campaign_participants` (`campaign_id`, `user_id`, `joined_at`) VALUES
(3, 3, '2025-08-08 23:11:22'),
(3, 4, '2025-08-08 23:22:22'),
(3, 5, '2025-08-08 23:18:53'),
(3, 6, '2025-11-22 00:24:27');

-- --------------------------------------------------------

--
-- Table structure for table `encounters`
--

CREATE TABLE `encounters` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `pokemon_name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL,
  `health` int(11) NOT NULL,
  `current_health` int(11) NOT NULL,
  `is_shiny` tinyint(1) NOT NULL DEFAULT 0,
  `is_legendary` tinyint(1) NOT NULL DEFAULT 0,
  `is_mythical` tinyint(1) NOT NULL DEFAULT 0,
  `sprite_url` varchar(255) DEFAULT NULL,
  `types` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encounters`
--

INSERT INTO `encounters` (`id`, `campaign_id`, `pokemon_name`, `level`, `health`, `current_health`, `is_shiny`, `is_legendary`, `is_mythical`, `sprite_url`, `types`, `created_at`) VALUES
(22, 3, 'zubat', 5, 30, 9, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/41.png', 'poison,flying', '2025-08-08 23:33:02'),
(23, 3, 'porygon', 3, 30, 8, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/137.png', 'normal', '2025-08-08 23:53:39'),
(24, 3, 'ditto', 6, 35, 10, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/132.png', 'normal', '2025-08-09 00:07:57'),
(25, 3, 'zubat', 5, 30, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/41.png', 'poison,flying', '2025-08-09 00:20:15'),
(26, 3, 'butterfree', 10, 50, 4, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/12.png', 'bug,flying', '2025-08-09 00:31:16'),
(27, 3, 'machamp', 10, 70, 1, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/68.png', 'fighting', '2025-08-09 00:46:03'),
(28, 3, 'golbat', 6, 30, 5, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/42.png', 'poison,flying', '2025-08-09 01:06:15'),
(29, 3, 'pidgey', 10, 50, 5, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/16.png', 'normal,flying', '2025-08-09 01:21:23'),
(30, 3, 'porygon', 5, 35, 35, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/137.png', 'normal', '2025-08-09 01:37:50'),
(31, 3, 'scyther', 10, 50, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/123.png', 'bug,flying', '2025-08-09 01:40:03'),
(32, 3, 'pidgey', 10, 110, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/16.png', 'normal,flying', '2025-08-29 23:13:06'),
(33, 3, 'pinsir', 12, 150, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/127.png', 'bug', '2025-08-29 23:31:21'),
(34, 3, 'tangela', 11, 110, 15, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/114.png', 'grass', '2025-08-29 23:44:14'),
(35, 3, 'venonat', 12, 160, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/48.png', 'bug,poison', '2025-08-30 00:09:23'),
(36, 3, 'caterpie', 12, 120, 16, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/10.png', 'bug', '2025-08-30 00:27:00'),
(37, 3, 'butterfree', 15, 180, 180, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/12.png', 'bug,flying', '2025-08-30 00:40:02'),
(38, 3, 'nidoran-m', 15, 180, 5, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/32.png', 'poison', '2025-08-30 00:40:35'),
(39, 3, 'butterfree', 10, 120, 120, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/12.png', 'bug,flying', '2025-08-30 01:08:06'),
(40, 3, 'exeggutor', 10, 120, 19, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/103.png', 'grass,psychic', '2025-08-30 01:08:45'),
(41, 3, 'weezing', 15, 160, 16, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/110.png', 'poison', '2025-08-30 01:25:06'),
(42, 3, 'metapod', 10, 120, 10, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/11.png', 'bug', '2025-08-30 01:49:30'),
(43, 3, 'venonat', 12, 120, 18, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/48.png', 'bug,poison', '2025-08-30 02:07:01'),
(44, 3, 'charmeleon', 16, 200, 18, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/5.png', 'fire', '2025-08-30 02:31:52'),
(53, 3, 'diglett', 9, 88, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/50.png', 'ground', '2025-10-03 23:01:08'),
(54, 3, 'sandshrew', 9, 128, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/27.png', 'ground', '2025-10-03 23:12:30'),
(55, 3, 'geodude', 10, 132, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/74.png', 'rock,ground', '2025-10-03 23:27:54'),
(56, 3, 'onix', 12, 140, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/95.png', 'rock,ground', '2025-10-03 23:41:41'),
(57, 3, 'squirtle', 16, 192, 26, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/shiny/7.png', 'water', '2025-10-04 00:31:35'),
(58, 3, 'pikachu', 6, 100, 18, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/shiny/25.png', 'electric', '2025-10-04 00:51:15'),
(59, 3, 'meowth', 12, 128, 38, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/52.png', 'normal', '2025-10-04 01:23:06'),
(60, 3, 'meowth', 12, 38, 19, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/52.png', 'normal', '2025-10-04 01:32:48'),
(61, 3, 'magikarp', 16, 168, 168, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/shiny/129.png', 'water', '2025-11-22 00:19:07'),
(62, 3, 'oddish', 11, 152, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/43.png', 'grass,poison', '2025-11-22 01:08:03'),
(63, 3, 'bellsprout', 11, 164, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/69.png', 'grass,poison', '2025-11-22 01:24:09'),
(64, 3, 'dugtrio', 12, 152, 15, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/51.png', 'ground', '2025-11-22 01:35:45'),
(65, 3, 'zapdos', 25, 472, 472, 1, 1, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/shiny/145.png', 'electric,flying', '2026-01-10 00:13:48'),
(66, 3, 'mankey', 20, 256, 32, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/56.png', 'fighting', '2026-01-10 00:50:14'),
(67, 3, 'bulbasaur', 20, 284, 284, 1, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/shiny/1.png', 'grass,poison', '2026-01-10 01:27:53'),
(68, 3, 'staryu', 16, 164, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/120.png', 'water', '2026-01-10 01:53:23'),
(69, 3, 'starmie', 21, 328, 0, 0, 0, 0, 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/121.png', 'water,psychic', '2026-01-10 02:07:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('standard','admin') NOT NULL DEFAULT 'standard',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `user_type`, `created_at`) VALUES
(1, 'StaleBanana', '$2y$10$/1fuzulsLZSV8cw1GrfGbuaC08JlyxzTH0L9Gfg6MLBngo0JQY7yG', 'admin', '2025-08-08 02:06:41'),
(3, 'Javier', '$2y$10$6fKdHFIs4y0f0qdTPqCV/OjsR62nq87oGl7v7Wu3LbjQklY7sdd0q', 'standard', '2025-08-08 21:22:39'),
(4, 'Zsordrin', '$2y$10$rZfbo1ahw.J4TrKNAJDgRO7nKqoch/p5IbhCnkFtup91L.37PQLdS', 'standard', '2025-08-08 22:15:46'),
(5, 'Dragoonborn', '$2y$10$vENzz.iL1ZgXlHL19l9O0.O98XXocsy5fhxhhGdXWOf4Tb4y1leOm', 'standard', '2025-08-08 22:16:25'),
(6, 'Coolxghf', '$2y$10$QxA4ayEqHdUnEDnjlXkZROsSF1zSyFRp9VBrlCSM0iijgsTAydmyW', 'standard', '2025-11-22 00:23:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `join_code` (`join_code`);

--
-- Indexes for table `campaign_caught`
--
ALTER TABLE `campaign_caught`
  ADD PRIMARY KEY (`encounter_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `campaign_participants`
--
ALTER TABLE `campaign_participants`
  ADD PRIMARY KEY (`campaign_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `encounters`
--
ALTER TABLE `encounters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `encounters`
--
ALTER TABLE `encounters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `campaign_caught`
--
ALTER TABLE `campaign_caught`
  ADD CONSTRAINT `campaign_caught_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campaign_caught_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campaign_participants`
--
ALTER TABLE `campaign_participants`
  ADD CONSTRAINT `campaign_participants_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campaign_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `encounters`
--
ALTER TABLE `encounters`
  ADD CONSTRAINT `encounters_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
