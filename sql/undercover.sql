-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 24 août 2025 à 17:00
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `undercover`
--

-- --------------------------------------------------------

--
-- Structure de la table `hints`
--

DROP TABLE IF EXISTS `hints`;
CREATE TABLE IF NOT EXISTS `hints` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_id` varchar(10) NOT NULL,
  `player_id` int NOT NULL,
  `hint` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hint` (`room_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players`
--

DROP TABLE IF EXISTS `players`;
CREATE TABLE IF NOT EXISTS `players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_id` int DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `role` enum('civil','undercover') DEFAULT NULL,
  `word` varchar(100) DEFAULT NULL,
  `is_creator` tinyint(1) DEFAULT '0',
  `is_undercover` tinyint(1) NOT NULL DEFAULT '0',
  `is_alive` tinyint(1) DEFAULT '1',
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_connected` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `state` enum('waiting','started','finished','closed','voting') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `common_word` varchar(255) DEFAULT NULL,
  `undercover_word` varchar(255) DEFAULT NULL,
  `creator_id` int NOT NULL,
  `players_order` text,
  `current_turn` int DEFAULT '0',
  `result` varchar(50) DEFAULT NULL,
  `remake` int NOT NULL DEFAULT '0',
  `anonymize_votes` tinyint(1) NOT NULL DEFAULT '0',
  `turn_time_seconds` int NOT NULL DEFAULT '30',
  `undercover_count` tinyint UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `votes`
--

DROP TABLE IF EXISTS `votes`;
CREATE TABLE IF NOT EXISTS `votes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `voter_id` int NOT NULL,
  `target_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
