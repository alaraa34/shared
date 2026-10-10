-- =====================================================================
-- sh_lien_type : types de lien communs à toutes les applications
-- (theBand, training...). Table sans préfixe de projet, lue par
-- shared\php\classes\lien\TypeLien.
--
-- CONTENU DE REFERENCE : si la table change dans une base, refaire
-- l'export (phpMyAdmin, structure + données) et remplacer ce fichier.
-- Nouvelle base : importer ce script tel quel.
-- Attention : le script recrée la table (DROP puis CREATE).
--
-- Les types de lien admis pour chaque sujet d'une application sont dans
-- la table <prefixe>lien_type_usage de cette application.
-- =====================================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `sh_lien_type`;
CREATE TABLE `sh_lien_type` (
  `id` int NOT NULL COMMENT 'Identifiant à saisir, pas auto : référencé par <prefixe>lien.idTypeLien',
  `nom` varchar(20) NOT NULL COMMENT 'Nom pour afficher dans les combos',
  `nomAffiche` varchar(10) NOT NULL COMMENT 'Nom pour affichage',
  `nomLong` text NOT NULL COMMENT 'Libellé long du type de lien',
  `externe` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True : externe (url) / False : fichier',
  `repertoire` varchar(56) NOT NULL COMMENT 'Répertoire de classement',
  `couleur` varchar(30) NOT NULL COMMENT 'Classe utilisée pour l''affichage',
  `extensions` varchar(256) NOT NULL COMMENT 'Types MIME autorisés',
  `icone` text NOT NULL COMMENT 'Icône à afficher',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de lien communs à toutes les applications';

INSERT INTO `sh_lien_type` (`id`, `nom`, `nomAffiche`, `nomLong`, `externe`, `repertoire`, `couleur`, `extensions`, `icone`) VALUES
(1, 'MP3', 'mp3', 'mp3 enregistré en répétition ou li', 0, 'mp3', 'texteMP3', 'audio/mp3', '<i class=\"bi bi-volume-up-fill\"></i>'),
(2, 'Accords', 'accords', '', 0, 'accords', 'texteACCORDS', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(3, 'Partition', 'partition', '', 0, 'partition', 'textePARTITION', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(4, 'You Tube', 'site', '', 1, '', 'texteSITE', '', '<i class=\"bi bi-globe\"></i>'),
(5, 'Image', 'image', '', 0, 'image', 'textePHOTO', 'png image/png jpeg jpg image/jpeg', '<i class=\"bi bi-file-image\"></i>'),
(9, 'Paroles', 'paroles', '', 0, 'paroles', 'textePAROLES', 'application/pdf,application.txt', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(11, 'Google Maps', 'maps', '', 1, '', 'texteMAPS', '', '<i class=\"fa-solid fa-location-dot\"></i>'),
(12, 'Facebook', 'facebook', 'facebook', 1, '', 'texteMAPS', '', '<i class=\"bi bi-facebook\"></i>'),
(13, 'Instagram', 'instagram', 'instagram', 1, '', 'texteMAPS', '', '<i class=\"bi bi-instagram\"></i>'),
(15, 'PDF', 'pdf', 'fichier pdf', 0, '', 'textePARTITION', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(21, 'Autre', 'autre docu', '', 0, 'autres', 'texteAUTRE', '.pdf ,.txt', '<i class=\"bi bi-file-earmark-pdf\"></i>'),
(28, 'PAD', 'pad', '', 0, 'pad', 'textePAD', 'mp3 audio/mpeg', '<i class=\"bi bi-volume-up-fill\"></i>'),
(29, 'Vidéo', 'vidéo', '', 0, 'video', 'texteVIDEO', 'video/mpeg,video/mp4,video/mkv,video/webm', '<i class=\"fa-solid fa-video\"></i>'),
(49, 'Choeurs', 'choeurs', '', 0, 'choeurs', 'texteCHOEURS', 'application/pdf', '<i class=\"bi bi-file-earmark-pdf\"></i>');