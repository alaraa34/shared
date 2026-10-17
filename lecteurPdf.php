<?php
declare(strict_types=1);
/*******************************************************************************
 * Page autonome du lecteur PDF à défilement, commune à tous les projets
 * appel : /shared/lecteurPdf.php?url=/theBand/fichiers/x.pdf&titre=...
 * (liens générés par shared\php\classes\lien\LecteurPdf::urlPage / lien, et par Lien::genererLienHref)
 *******************************************************************************/
define('ROOT_PATH', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/');
require_once __DIR__ . '/php/autoload.php';
echo \shared\php\classes\lien\LecteurPdf::pageDepuisRequete();
