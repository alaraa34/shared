<?php
declare(strict_types=1);
namespace shared\php\classes\lien;
/*******************************************************************************
 * Séquence de notes en clé de sol ou de fa, avec silences et barres de mesure
 *
 * Stockage : c'est un lien de type "Séquence" (TypeLien::SEQUENCE, table sh_lien_type) ;
 *            l'éditeur produit un fichier JSON *.seq.json qui est envoyé comme n'importe quel
 *            fichier de lien (répertoire fichiers/sequence, privé possible, supprimé avec le lien).
 *            Format : voir shared/js/sequence.js
 *
 * Utilisations :
 *  - saisie : Sequence::includes(true) dans $script de la page qui affiche Lienhtml::afficherBlocLien
 *             (le type "Séquence" doit être coché pour le sujet : Paramétrage > Types de lien par sujet) ;
 *             choisir le type dans une ligne de lien ouvre l'éditeur
 *  - lien    : Lien::genererLienHref ouvre automatiquement la page autonome /shared/sequence.php
 *  - écran   : Sequence::includes() puis Sequence::composant($url) ou Sequence::composantsDesLiens($liens) ;
 *              contenu chargé en ajax : SequenceNotes.initialiser(element) en JS
 *******************************************************************************/
use shared\php\toolbox\Toolbox              as Tbx;

class Sequence {

    public const string SUFFIXE = ".seq.json";
    public const string PAGE    = "/shared/sequence.php";     //page autonome commune à tous les projets

    public static function includes(bool $editeur = false): string {
    //CSS et JS ; $editeur : ajoute la modale de saisie (une seule par page)
        return Tbx::includeCSS('sequence') . Tbx::includeJS('sequence')
             . '<script>window.SEQUENCE_TYPE_LIEN = ' . TypeLien::SEQUENCE . ';</script>'
             . ($editeur ? self::modaleEditeur() : "");
    }

    public static function estSequence(string $url): bool {
    //vrai si l'url désigne un fichier de séquence (paramètres et ancre ignorés)
        return str_ends_with(strtolower((string)parse_url($url, PHP_URL_PATH)), self::SUFFIXE);
    }

    public static function urlPage(string $url, string $titre = ""): string {
    //url de la page autonome qui affiche et joue la séquence
        return self::PAGE . "?url=" . rawurlencode(LecteurPdf::urlAbsolue($url))
             . ($titre === "" ? "" : "&titre=" . rawurlencode($titre));
    }

    public static function composant(string $url, string $titre = "", string $id = "", int $tempo = 0): string {
    //portée dessinée par sequence.js (largeur du conteneur, plusieurs portées si besoin) + bouton écouter
    //$tempo > 0 : joue à ce tempo au lieu de celui enregistré avec la séquence (ex. palier en cours d'un exercice)
        $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return ($titre === "" ? "" : '<div class="small fw-semibold mt-2">' . $e($titre) . '</div>')
             . '<div class="sequence-vue"' . ($id === "" ? "" : ' id="' . $e($id) . '"')
             . ' data-url="' . $e(LecteurPdf::urlAbsolue($url)) . '"'
             . ($tempo > 0 ? ' data-tempo="' . $tempo . '"' : '') . '></div>';
    }

    public static function composantsDesLiens(array $liens, int $tempo = 0): string {
    //toutes les séquences d'une collection de liens (instances de Lien), avec leur description
        $html = "";
        foreach ($liens as $lien) {
            if ($lien instanceof Lien && self::estSequence($lien->url)) {
                $html .= self::composant($lien->url, $lien->description, tempo: $tempo);
            }
        }
        return $html;
    }

    //------------------------------------------------------------------------------------------------
    // PAGE AUTONOME
    //------------------------------------------------------------------------------------------------
    public static function pageDepuisRequete(): string {
    //paramètres GET url (fichier .seq.json du même serveur), titre
        $url = (string)filter_input(INPUT_GET, 'url');
        $titre = (string)filter_input(INPUT_GET, 'titre');
        if (!self::urlAutorisee($url)) {
            http_response_code(400);
            return "Sequence : fichier absent ou non autorisé";
        }
        return self::page($url, $titre);
    }

    public static function page(string $url, string $titre = ""): string {
    //page HTML complète, sans le layout de l'application
        $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $titre = $titre === "" ? "Séquence" : $titre;
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
             . '<meta name="viewport" content="width=device-width, initial-scale=1">'
             . '<title>' . $e($titre) . '</title>' . self::includes()
             . '</head><body class="seq-page"><h1>' . $e($titre) . '</h1>'
             . self::composant($url)
             . '</body></html>';
    }

    private static function urlAutorisee(string $url): bool {
    //fichier .seq.json du même serveur seulement
        if ($url === "" || !self::estSequence($url) || preg_match('/^\s*(javascript|data):/i', $url)) {return false;}
        $hote = parse_url($url, PHP_URL_HOST);
        return $hote === null || $hote === false
            || strcasecmp($hote, (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0;
    }

    //------------------------------------------------------------------------------------------------
    // MODALE DE SAISIE (Bootstrap 5)
    //------------------------------------------------------------------------------------------------
    public static function modaleEditeur(): string {
        $b = fn(string $attr, string $titre, string $contenu) =>
            '<button type="button" class="seq-outil" ' . $attr . ' title="' . $titre . '" aria-label="' . $titre . '">' . $contenu . '</button>';
        $tete = fn(string $remplie, string $queue) =>
            '<svg viewBox="0 0 24 24"><ellipse cx="10" cy="17" rx="4.5" ry="3.2" transform="rotate(-20 10 17)" '
            . ($remplie ? 'fill="currentColor" stroke="none"' : 'stroke-width="1.6"') . '/>' . $queue . '</svg>';

        $cles = $b('data-cle="sol"', 'Clé de sol', '<svg viewBox="0 0 24 32" style="height:26px"><path d="M12,28 L13,4 C13,1 17,1 16,5 C15,9 7,12 7,18 C7,22 10,24 13,24 C16,24 18,22 18,19.5 C18,17 16,15.5 13.5,15.5 C11,15.5 9.5,17 10,19 M12,28 c0,2.5 -3.5,3 -4,0.5" stroke-width="1.7" stroke-linecap="round"/></svg>')
              . $b('data-cle="fa"', 'Clé de fa', '<svg viewBox="0 0 24 24"><circle cx="6" cy="8" r="2" fill="currentColor" stroke="none"/><path d="M6,8 c0,-6 11,-6 11,1 c0,7 -6,11 -13,13" stroke-width="1.8" stroke-linecap="round"/><circle cx="20.5" cy="6.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="20.5" cy="11" r="1.3" fill="currentColor" stroke="none"/></svg>');
        $notes = $b('data-outil="note" data-duree="w"', 'Ronde', '<svg viewBox="0 0 24 24"><ellipse cx="12" cy="13" rx="5.5" ry="3.8" transform="rotate(-20 12 13)" stroke-width="1.8"/></svg>')
               . $b('data-outil="note" data-duree="h"', 'Blanche', $tete('', '<path d="M14.3,16 V3" stroke-width="1.5"/>'))
               . $b('data-outil="note" data-duree="q"', 'Noire', $tete('1', '<path d="M14.3,16 V3" stroke-width="1.5"/>'))
               . $b('data-outil="note" data-duree="e"', 'Croche', $tete('1', '<path d="M14.3,16 V3 q2,5 6,8" stroke-width="1.6"/>'));
        $silences = $b('data-outil="silence" data-duree="w"', 'Pause', '<svg viewBox="0 0 24 24"><path d="M4,9 H20" stroke-width="1"/><rect x="7" y="9" width="10" height="4.5" fill="currentColor" stroke="none"/></svg>')
                  . $b('data-outil="silence" data-duree="h"', 'Demi-pause', '<svg viewBox="0 0 24 24"><path d="M4,15 H20" stroke-width="1"/><rect x="7" y="10.5" width="10" height="4.5" fill="currentColor" stroke="none"/></svg>')
                  . $b('data-outil="silence" data-duree="q"', 'Soupir', '<svg viewBox="0 0 24 24"><path d="M10,3 l5,6 l-4,5 l4,5 q-6,-2 -3,4" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>')
                  . $b('data-outil="silence" data-duree="e"', 'Demi-soupir', '<svg viewBox="0 0 24 24"><circle cx="9.5" cy="8" r="2.2" fill="currentColor" stroke="none"/><path d="M9.5,9 q4,1 6.5,-3 l-5,14" stroke-width="1.6" stroke-linecap="round"/></svg>');
        $barre = $b('data-outil="barre"', 'Barre de mesure', '<svg viewBox="0 0 24 24"><path d="M3,6 H21 M3,12 H21 M3,18 H21" stroke-width="0.8"/><path d="M12,6 V18" stroke-width="2.2"/></svg>');
        $alterations = $b('data-alt="#"', 'Dièse', '#') . $b('data-alt="b"', 'Bémol', 'b');
        $actions = '<button type="button" class="seq-outil seq-ecouter" title="Écouter" aria-label="Écouter"><i class="bi bi-play-fill"></i></button>'
                 . '<button type="button" class="seq-outil seq-annuler" title="Annuler la dernière saisie (Ctrl+Z)" aria-label="Annuler"><i class="bi bi-arrow-counterclockwise"></i></button>'
                 . '<button type="button" class="seq-outil seq-effacer" title="Tout effacer" aria-label="Tout effacer"><i class="bi bi-trash3"></i></button>';

        return '<div class="modal fade" id="sequenceModale" tabindex="-1" aria-labelledby="sequenceModaleTitre" aria-hidden="true">'
             . '<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">'
             .   '<div class="modal-header"><h5 class="modal-title" id="sequenceModaleTitre"><i class="bi bi-music-note-beamed"></i> Séquence de notes</h5>'
             .     '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>'
             .   '<div class="modal-body">'
             .     '<div class="seq-barre-outils">'
             .       '<div class="seq-groupe">' . $cles . '</div>'
             .       '<div class="seq-groupe">' . $notes . '</div>'
             .       '<div class="seq-groupe">' . $silences . '</div>'
             .       '<div class="seq-groupe">' . $barre . '</div>'
             .       '<div class="seq-groupe">' . $alterations . '</div>'
             .       '<div class="seq-groupe">' . $actions . '</div>'
             .       '<div class="seq-groupe"><div class="input-group input-group-sm" title="Tempo de lecture">'
             .         '<span class="input-group-text"><i class="fa-solid fa-drum"></i></span>'
             .         '<input type="number" class="form-control seq-tempo" min="30" max="240" step="1" aria-label="Tempo (bpm)">'
             .       '</div></div>'
             .     '</div>'
             .     '<div class="seq-zone"><svg class="seq-svg"></svg></div>'
             .     '<p class="seq-noms"></p><p class="seq-erreur"></p>'
             .   '</div>'
             .   '<div class="modal-footer">'
             .     '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>'
             .     '<button type="button" class="btn btn-primary seq-valider">Valider la séquence</button>'
             .   '</div>'
             . '</div></div></div>';
    }
}
