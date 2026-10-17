<?php
declare(strict_types=1);
namespace shared\php\classes\lien;
/*******************************************************************************
 * Lecteur PDF à défilement automatique (PC et tablette)
 * Le PDF est affiché par PDF.js, page par page, dans une zone que le script fait défiler.
 * Boutons : lecture/pause, plus lent / plus vite (x1,2), plein écran, retour.
 * Clavier : espace = lecture/pause, flèches haut/bas = vitesse.
 * Tablette : on reprend la main au doigt, le défilement repart de là où on a lâché ;
 *            l'écran ne se met pas en veille pendant la lecture.
 * Vitesse : calée pour que le défilement complet dure autant que le MP3 du titre ($urlAudio :
 *           MP3 joué par le groupe s'il existe, sinon MP3 original), 3 min sans MP3.
 *           Les boutons -/+ appliquent un facteur à cette durée (x1,2), mémorisé par document dans le
 *           navigateur (et envoyé en POST url/facteur à $urlSauvegarde si fournie) ;
 *           un clic sur la durée affichée revient à la durée du MP3.
 *
 * Utilisations :
 *  - page autonome commune à tous les projets : /shared/lecteurPdf.php?url=...&titre=...
 *                    lien : echo LecteurPdf::lien($urlPdf, "Défiler", $titre);
 *                    Lien::genererLienHref ajoute automatiquement l'icône de défilement aux liens PDF
 *  - composant dans une page : LecteurPdf::includes() dans le head/les scripts
 *                    puis LecteurPdf::composant($urlPdf, id: 'lecteur') ;
 *                    en JS : document.getElementById('lecteur').lecteurPdf.charger(url)
 * Limite : le PDF doit être sur le même serveur (sinon il faut que l'autre site autorise CORS).
 *******************************************************************************/
use shared\php\toolbox\Toolbox              as Tbx;

class LecteurPdf {

    public const string PDFJS        = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js";
    public const string PDFJS_WORKER = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    public const int    DUREE_DEFAUT = 180;    //secondes de défilement complet quand il n'y a pas de MP3
    public const string PAGE = "/shared/lecteurPdf.php";     //page autonome commune à tous les projets

    public static function includes(): string {
    //scripts et style du lecteur (PDF.js, configuration du worker, JS et CSS de shared)
        return '<script src="' . self::PDFJS . '"></script>'
             . '<script>if (window.pdfjsLib) {pdfjsLib.GlobalWorkerOptions.workerSrc = "' . self::PDFJS_WORKER . '";}</script>'
             . Tbx::includeCSS('lecteurPdf') . Tbx::includeJS('lecteurPdf');
    }

    public static function composant(string $urlPdf = "", string $urlAudio = "", string $urlSauvegarde = "",
                                     string $urlRetour = "", string $id = "lecteurPdf", string $attributs = ""): string {
    //zone de lecture + barre de boutons ; initialisée automatiquement par lecteurPdf.js
    //$attributs : attributs HTML supplémentaires sur la racine (ex. style="display:none")
        $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $retour = $urlRetour === "" ? ""
            : '<a class="lpdf-bouton lpdf-retour" href="' . $e($urlRetour) . '" title="Retour">&#10005;</a>';
        return '<div class="lecteur-pdf" id="' . $e($id) . '"'
             . ' data-url="' . $e($urlPdf) . '"'
             . ' data-audio="' . $e($urlAudio) . '"'
             . ' data-duree-defaut="' . self::DUREE_DEFAUT . '"'
             . ' data-url-sauvegarde="' . $e($urlSauvegarde) . '"'
             . ($attributs === "" ? "" : " " . $attributs) . '>'
             .   '<div class="lpdf-zone"><div class="lpdf-pages"></div><div class="lpdf-message"></div></div>'
             .   '<div class="lpdf-barre">'
             .     '<button type="button" class="lpdf-bouton lpdf-moins" title="Ralentir (flèche bas)">&minus;</button>'
             .     '<button type="button" class="lpdf-bouton lpdf-lecture" title="Lancer / arrêter (espace)">&#9654;</button>'
             .     '<button type="button" class="lpdf-bouton lpdf-plus" title="Accélérer (flèche haut)">+</button>'
             .     '<span class="lpdf-vitesse" title="Durée du défilement complet"></span>'
             .     '<button type="button" class="lpdf-bouton lpdf-plein-ecran" title="Plein écran">&#x26F6;</button>'
             .     $retour
             .   '</div>'
             . '</div>';
    }

    public static function page(string $urlPdf, string $titre = "", string $urlRetour = "",
                                string $urlAudio = "", string $urlSauvegarde = ""): string {
    //page HTML complète, sans le layout de l'application
        $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
             . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
             . '<title>' . $e($titre === "" ? "Document" : $titre) . '</title>'
             . self::includes()
             . '</head><body class="lpdf-page">'
             . self::composant($urlPdf, $urlAudio, $urlSauvegarde, $urlRetour)
             . '</body></html>';
    }

    public static function pageDepuisRequete(): string {
    //page autonome : paramètres GET url (PDF), titre, retour, audio (MP3 qui fixe la durée)
        $url = (string)filter_input(INPUT_GET, 'url');
        $titre = (string)filter_input(INPUT_GET, 'titre');
        $retour = (string)filter_input(INPUT_GET, 'retour');
        $audio = (string)filter_input(INPUT_GET, 'audio');
        if (!self::urlAutorisee($url)) {
            http_response_code(400);
            return "LecteurPdf : document absent ou non autorisé";
        }
        if (!self::urlAutorisee($retour)) {$retour = "";}
        if (!self::urlAutorisee($audio)) {$audio = "";}
        return self::page($url, $titre, $retour, $audio);
    }

    public static function lien(string $urlPdf, string $libelle, string $titre = "", string $classe = "",
                                string $urlAudio = ""): string {
    //lien qui ouvre la page autonome du lecteur dans un nouvel onglet
        return '<a href="' . htmlspecialchars(self::urlPage($urlPdf, $titre, $urlAudio), ENT_QUOTES, 'UTF-8') . '"'
             . ($classe === "" ? "" : ' class="' . $classe . '"')
             . ' title="Faire défiler le document" target="_blank" rel="noopener">' . $libelle . '</a>';
    }

    public static function urlPage(string $urlPdf, string $titre = "", string $urlAudio = ""): string {
    //url de la page autonome pour un PDF (chemins relatifs au projet convertis en chemins absolus)
        return self::PAGE . "?url=" . rawurlencode(self::urlAbsolue($urlPdf))
             . ($titre === "" ? "" : "&titre=" . rawurlencode($titre))
             . ($urlAudio === "" ? "" : "&audio=" . rawurlencode(self::urlAbsolue($urlAudio)));
    }

    public static function estPdf(string $url): bool {
    //vrai si l'url désigne un fichier .pdf (paramètres et ancre ignorés)
        $chemin = (string)parse_url($url, PHP_URL_PATH);
        return strtolower(pathinfo($chemin, PATHINFO_EXTENSION)) === "pdf";
    }

    public static function urlAbsolue(string $url): string {
    //"fichiers/x.pdf" (relatif au projet courant) devient "/theBand/fichiers/x.pdf"
        if ($url === "" || str_starts_with($url, "/") || parse_url($url, PHP_URL_SCHEME) !== null) {return $url;}
        $dossier = str_replace("\\", "/", dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        return rtrim($dossier, "/") . "/" . $url;
    }

    private static function urlAutorisee(string $url): bool {
    //PDF du même serveur seulement : chemin relatif, ou url absolue vers le même hôte
        if ($url === "" || preg_match('/^\s*(javascript|data):/i', $url)) {return false;}
        $hote = parse_url($url, PHP_URL_HOST);
        return $hote === null || $hote === false
            || strcasecmp($hote, (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0;
    }
}
