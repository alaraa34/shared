<?php
declare(strict_types=1);
/*******************************************************************************
 * Page autonome d'une séquence de notes, commune à tous les projets
 * appel : /shared/sequence.php?url=/training/fichiers/sequence/x.seq.json&titre=...
 * (liens générés par shared\php\classes\lien\Sequence::urlPage, et par Lien::genererLienHref)
 *******************************************************************************/
define('ROOT_PATH', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/');
require_once __DIR__ . '/php/autoload.php';
echo \shared\php\classes\lien\Sequence::pageDepuisRequete();
