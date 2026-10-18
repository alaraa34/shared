-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 12 oct. 2026 à 20:09
-- Version du serveur : 8.2.0
-- Version de PHP : 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `hurri2183258`
--

-- --------------------------------------------------------

--
-- Structure de la table `sh_lien_type`
--

DROP TABLE IF EXISTS `sh_lien_type`;
CREATE TABLE IF NOT EXISTS `sh_lien_type` (
  `id` int NOT NULL COMMENT 'Identifiant à saisir, pas auto : référencé par <prefixe>lien.idTypeLien',
  `nom` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom pour afficher dans les combos',
  `nomAffiche` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom pour affichage',
  `nomLong` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Libellé long du type de lien',
  `externe` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True : externe (url) / False : fichier',
  `repertoire` varchar(56) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Répertoire de classement',
  `couleur` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Classe utilisée pour l''affichage',
  `extensions` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Types MIME autorisés',
  `icone` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Icône à afficher',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de lien communs à toutes les applications';

--
-- Déchargement des données de la table `sh_lien_type`
--

INSERT INTO `sh_lien_type` (`id`, `nom`, `nomAffiche`, `nomLong`, `externe`, `repertoire`, `couleur`, `extensions`, `icone`) VALUES
(1, 'MP3ORI', 'mp3ori', 'mp3 original', 0, 'mp3ori', 'texteMP3', 'audio/mp3', '<i class=\"bi bi-cassette\"></i>'),
(2, 'Accords', 'accords', '', 0, 'accords', 'texteACCORDS', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(3, 'Partition', 'partition', '', 0, 'partition', 'textePARTITION', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(4, 'You Tube', 'site', '', 1, '', 'texteSITE', '', '<i class=\"bi bi-globe\"></i>'),
(5, 'Image', 'image', '', 0, 'image', 'textePHOTO', 'png image/png jpeg jpg image/jpeg', '<i class=\"bi bi-file-image\"></i>'),
(9, 'Paroles', 'paroles', '', 0, 'paroles', 'textePAROLES', 'application/pdf,application.txt', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(11, 'Google Maps', 'maps', '', 1, '', 'texteMAPS', '', '<i class=\"fa-solid fa-location-dot\"></i>'),
(12, 'Facebook', 'facebook', 'facebook', 1, '', 'texteMAPS', '', '<i class=\"bi bi-facebook\"></i>'),
(13, 'Instagram', 'instagram', 'instagram', 1, '', 'texteMAPS', '', '<i class=\"bi bi-instagram\"></i>'),
(15, 'PDF', 'pdf', 'fichier pdf', 0, '', 'textePARTITION', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(18, 'MP3jou', 'mp3jou', 'mp3 joué', 0, 'mp3jou', 'texteMP3', 'audio/mp3', '<i class=\"bi bi-cassette-fill\"></i>'),
(19, 'MP3', 'mp3', 'mp3 autre', 0, 'mp3', 'textePAD', 'mp3 audio/mpeg', '<i class=\"bi bi-volume-up-fill\"></i>'),
(21, 'Autre', 'autre docu', '', 0, 'autres', 'texteAUTRE', '.pdf ,.txt', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(28, 'PAD', 'pad', '', 0, 'pad', 'textePAD', 'mp3 audio/mpeg', '<i class=\"bi bi-volume-up-fill\"></i>'),
(29, 'Vidéo', 'vidéo', '', 0, 'video', 'texteVIDEO', 'video/mpeg,video/mp4,video/mkv,video/webm', '<i class=\"fa-solid fa-video\"></i>'),
(49, 'Choeurs', 'choeurs', '', 0, 'choeurs', 'texteCHOEURS', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(50, 'Séquence', 'séquence', 'séquence de notes saisie dans l''éditeur', 0, 'sequence', 'textePARTITION', '.json application/json', '<i class=\"bi bi-music-note-beamed\"></i>');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
