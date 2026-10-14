<?php
declare(strict_types=1);
/*******************************************************************************
 * Page « Générer les pistes » et points d'entrée de l'agent, communs aux applications
 * (theBand, training). Chaque application garde dans son contrôleur song :
 *  - le contrôle d'accès et l'affichage du layout (ils dépendent de son namespace)
 *  - la requête qui donne ses titres et leurs liens MP3, avec la transposition attendue
 * et appelle ces fonctions. Les url sont celles du contrôleur song de l'application :
 *  genererPistesLecteur, genererPistesATransposer, pistesDemanderLien,
 *  pistesAgentProchain, pistesAgentRecevoir, pistesAgentTerminer
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\bricks\Brick_table            as BkTable;
use shared\php\toolbox\Toolbox               as Tbx;
use shared\php\toolbox\Toolbox_adressage     as TbAdressage;
use shared\php\toolbox\Toolbox_liste         as TbListe;

class MultipisteControle
{
    public const string CONTROLEUR = "song";

    //------------------------------------------------------------------------------------------------
    //PAGE DE GENERATION
    //-----------------------------------------------------------------------------------------------
    public static function pageGeneration(array $lignes, bool $aTransposerSeulement, array $libelles = []): array {
    //liste des titres avec l'état de génération des pistes de leurs MP3 original et joué
    //$lignes : une ligne par lien (ou par titre sans lien) : idSong, titre, interprete, idLien, url, idTypeLien,
    //          nomAffiche, tona (transposition attendue en demi-tons)
    //$libelles : correction (ex. « tona de scène »), jou (ex. « groupe » ; null : pas de colonne MP3 joué)
    //retour : content, script, messageBarreMenu
        $correction = $libelles['correction'] ?? 'correction de tonalité';
        $libelleJou = array_key_exists('jou', $libelles) ? $libelles['jou'] : 'joué';
        $etats = Multipiste::etatsParLien(array_values(array_filter(array_column($lignes, 'idLien'))));

        //regroupement par titre
        $titres = [];
        $enAttente = 0;
        $aTraiter = 0;
        $agentsEnCours = [];
        foreach ($lignes as $ligne) {
            $idSong = (int)$ligne['idSong'];
            $titres[$idSong] ??= ['id'=>$idSong, 'titre'=>$ligne['titre'], 'interprete'=>$ligne['interprete'], 'ori'=>'', 'jou'=>''];
            if (is_null($ligne['idLien'])) {continue;}
            $idLien = (int)$ligne['idLien'];
            $tona = (int)$ligne['tona'];
            $etat = Multipiste::etatAffiche($etats[$idLien] ?? null, (string)$ligne['url'], $tona);
            if ($aTransposerSeulement && ($tona === 0 || $etat === Multipiste::TERMINE)) {continue;}
            if (in_array($etat, [Multipiste::A_TRAITER, Multipiste::EN_COURS], true)) {$enAttente++;}
            if ($etat === Multipiste::A_TRAITER) {$aTraiter++;}
            if ($etat === Multipiste::EN_COURS) {$agentsEnCours[] = $etats[$idLien]['agent'];}
            $urlDemande = TbAdressage::getURLstatic(self::CONTROLEUR, "pistesDemanderLien", $idLien);
            $colonne = (int)$ligne['idTypeLien'] === TypeLien::MP3JOU ? 'jou' : 'ori';
            $titres[$idSong][$colonne] .= Multipiste::iconeHtml(
                ['url'=>(string)$ligne['url'], 'nomAffiche'=>(string)$ligne['nomAffiche'],
                 'message'=>(string)($etats[$idLien]['message'] ?? '')], $etat, $urlDemande);
        }
        foreach ($titres as $idSong => $titre) {
            if ($aTransposerSeulement && $titre['ori'] === '' && $titre['jou'] === '') {unset($titres[$idSong]); continue;}
            foreach (['ori', 'jou'] as $colonne) {
                if ($titre[$colonne] === '') {$titres[$idSong][$colonne] = '<span class="text-muted">—</span>';}
            }
        }
        //choix de la liste : tous les titres ou seulement ceux à transposer
        $urlTous = TbAdressage::getURLstatic(self::CONTROLEUR, "genererPistesLecteur");
        $urlTransposer = TbAdressage::getURLstatic(self::CONTROLEUR, "genererPistesATransposer");
        $filtre = '<div class="btn-group btn-group-sm mb-2" role="group">'
                . '<a href="' . $urlTous . '" class="btn btn-outline-primary' . ($aTransposerSeulement ? '' : ' active') . '">Tous les titres</a>'
                . '<a href="' . $urlTransposer . '" class="btn btn-outline-primary' . ($aTransposerSeulement ? ' active' : '') . '">'
                . 'Titres à transposer (' . $correction . ' ≠ 0)</a></div>';

        $infosColonnes[] = ['zone'=>BkTable::COLONNE_NUMERO];
        $infosColonnes[] = ['zone'=>'titre','valeur'=>['titre',' - ','interprete'],'fonction'=>TbListe::FONCTION_VASN];
        $infosColonnes[] = ['zone'=>'ori','titre'=>'MP3<br>original','align'=>'MC'];
        //les MP3 joués ne sont jamais transposés : colonne inutile dans la liste des titres à transposer
        if (!$aTransposerSeulement && $libelleJou !== null) {$infosColonnes[] = ['zone'=>'jou','titre'=>'MP3<br>' . $libelleJou,'align'=>'MC'];}

        $content = self::bandeauAgents($aTraiter, array_unique($agentsEnCours))
                 . $filtre
                 . '<p class="small mb-2">'
                 . '<i class="fa-regular fa-file-audio text-danger"></i> à générer (ou ' . $correction . ' modifiée) &nbsp; '
                 . '<i class="fa-regular fa-file-audio text-warning"></i> en attente &nbsp; '
                 . '<i class="fa-regular fa-file-audio text-warning"></i><i class="bi bi-hourglass-split small ms-1 text-warning"></i> en cours &nbsp; '
                 . '<i class="fa-regular fa-file-audio text-success"></i> pistes générées'
                 . ($enAttente > 0 ? ' &nbsp;—&nbsp; <b>' . $enAttente . '</b> fichier(s) en attente ou en cours, page rafraîchie toutes les 30 s' : '')
                 . '</p>'
                 . (count($titres) === 0 && $aTransposerSeulement
                    ? Tbx::messageColorer(true, "Tous les titres avec une " . $correction . " ont leurs pistes dans la bonne tonalité")
                    : TbListe::constituerListe(array_values($titres), $infosColonnes));
        return ['content'=>$content,
                'script'=>$enAttente > 0 ? '<script>setTimeout(() => window.location.reload(), 30000);</script>' : '',
                'messageBarreMenu'=>$aTransposerSeulement ? "Pistes à transposer" : "Génération des pistes du lecteur multipiste"];
    }

    private static function bandeauAgents(int $aTraiter, array $agentsEnCours): string {
    //bandeau d'état des agents : signale s'il faut lancer lancer_agent.bat sur un PC
        $actifs = Multipiste::agentsActifs();
        $noms = [];
        foreach ($actifs as $nom => $secondes) {$noms[] = htmlspecialchars((string)$nom) . ' (contact il y a ' . $secondes . ' s)';}
        foreach ($agentsEnCours as $nom) {
            if (!isset($actifs[$nom])) {$noms[] = htmlspecialchars((string)$nom) . ' (traitement en cours)';}
        }
        if (count($noms) > 0) {
            return '<div class="alert alert-success py-2 small"><i class="bi bi-pc-display"></i> Agent actif : ' . implode(', ', $noms) . '</div>';
        }
        if ($aTraiter > 0) {
            return '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> <b>' . $aTraiter
                 . ' fichier(s) en attente et aucun agent actif</b> : lancer <code>lancer_agent.bat</code> sur un PC.</div>';
        }
        return '<div class="alert alert-secondary py-2 small"><i class="bi bi-pc-display"></i> Aucun agent actif'
             . ' (à lancer avant de demander une génération).</div>';
    }

    public static function demanderLien(int $idLien, string $url, int $tona, int $idDemandeur): void {
    //clic sur l'icône d'un MP3 : mise en file d'attente avec la transposition attendue,
    //transposition seule si les pistes d'origine sont à jour ; retour sur la liste d'où vient le clic
        $etat = Multipiste::etatAffiche(Multipiste::etatsParLien([$idLien])[$idLien] ?? null, $url, $tona);
        Multipiste::demander($idLien, $idDemandeur, $tona, $etat !== Multipiste::A_TRANSPOSER);
        $fonction = str_contains((string)filter_input(INPUT_SERVER, 'HTTP_REFERER'), 'genererPistesATransposer')
                  ? "genererPistesATransposer" : "genererPistesLecteur";
        header('Location: ' . TbAdressage::getURLstatic(self::CONTROLEUR, $fonction));
    }

    //------------------------------------------------------------------------------------------------
    //MODIFICATION DE LA TONA DE SCENE D'UN TITRE (écran de détail, shared/js/confirmationTona.js)
    //-----------------------------------------------------------------------------------------------
    public const string CHAMP_CONFIRMATION_TONA = "confirmationTona";

    public static function confirmationTonaHtml(int $idTitre, string $tonaActuelle, string $message): array {
    //pour la liste de choix de la tona de scène d'un titre existant : attributs à placer dans le <select>
    //et champ caché à placer dans le formulaire ; rien pour un nouveau titre
        if ($idTitre <= 0) {return ['attributs'=>'', 'champ'=>''];}
        return ['attributs'=>' data-tona-initiale="' . htmlspecialchars(trim($tonaActuelle), ENT_QUOTES)
                           . '" data-tona-message="' . htmlspecialchars($message, ENT_QUOTES) . '"',
                'champ'=>'<input type="hidden" name="' . self::CHAMP_CONFIRMATION_TONA . '" value="0" />'];
    }

    public static function tonaSceneAEnregistrer(string $ancienne, string $saisie): array {
    //tona saisie pour un titre existant : changée seulement si l'utilisateur a répondu oui au message
    //retour : tona à enregistrer, modifiee (changement confirmé), refusee (changement demandé mais non confirmé)
        $change = (int)trim($ancienne) !== (int)trim($saisie);
        $confirme = TbAdressage::getPost("S", self::CHAMP_CONFIRMATION_TONA) === "1";
        if (!$change) {return ['tona'=>$saisie, 'modifiee'=>false, 'refusee'=>false];}
        if (!$confirme) {return ['tona'=>$ancienne, 'modifiee'=>false, 'refusee'=>true];}
        return ['tona'=>$saisie, 'modifiee'=>true, 'refusee'=>false];
    }

    public static function messageTonaScene(array $decision, array $bilan): string {
    //complément du message d'enregistrement d'un titre
        if ($decision['refusee']) {return " (tona de scène non modifiée)";}
        if (!$decision['modifiee']) {return "";}
        $details = [];
        if ($bilan['originaux'] > 0) {$details[] = "pistes transposées supprimées, à regénérer";}
        if ($bilan['joues'] > 0) {$details[] = $bilan['joues'] . " mp3 joué supprimé";}
        return count($details) > 0 ? " (tona modifiée : " . implode(", ", $details) . ")" : "";
    }

    //------------------------------------------------------------------------------------------------
    //POINTS D'ENTREE DE L'AGENT (POST, réponse JSON, sans session ni layout)
    //Tous reçoivent cle (contenu de ENVIRagent.txt du projet), agent (nom du PC) et version
    //Une piste arrive en formulaire de fichier, ou par morceaux (position, taille, donnees en base64)
    //quand le pare-feu de l'hébergeur refuse les formulaires de fichier
    //-----------------------------------------------------------------------------------------------
    public static function agentProchain(): never {
    //prochain MP3 à traiter : {"idLien":0} s'il n'y a rien
        $agent = self::agentControler();
        self::agentRepondre(Multipiste::agentProchain($agent, max(1, TbAdressage::getPost("I", "version"))));
    }

    public static function agentRecevoir(): never {
    //réception d'une piste : idLien, piste (vocals, drums...), tona (0 = pistes d'origine),
    //et fichier ou morceau (position, taille du fichier, donnees en base64)
        $agent = self::agentControler();
        $donnees = TbAdressage::getPost("S", "donnees");
        if ($donnees !== "") {
            $erreur = Multipiste::agentRecevoirMorceau(TbAdressage::getPost("I", "idLien"), $agent,
                                                       TbAdressage::getPost("S", "piste"), TbAdressage::getPost("I", "tona"),
                                                       TbAdressage::getPost("I", "position"), TbAdressage::getPost("I", "taille"),
                                                       base64_decode($donnees, true));
        }
        else {
            $erreur = Multipiste::agentRecevoir(TbAdressage::getPost("I", "idLien"), $agent,
                                                TbAdressage::getPost("S", "piste"), $_FILES['fichier'] ?? [],
                                                TbAdressage::getPost("I", "tona"));
        }
        self::agentRepondre(['ok'=>$erreur === "", 'message'=>$erreur], $erreur === "" ? 200 : 400);
    }

    public static function agentTerminer(): never {
    //fin de traitement : idLien, succes (1/0), message, et pour information modele et duree (secondes)
        $agent = self::agentControler();
        $infos = ['modele'=>TbAdressage::getPost("S", "modele"), 'dureeSecondes'=>TbAdressage::getPost("I", "duree")];
        $erreur = Multipiste::agentTerminer(TbAdressage::getPost("I", "idLien"), $agent,
                                            TbAdressage::getPost("I", "succes") === 1, TbAdressage::getPost("S", "message"), $infos);
        self::agentRepondre(['ok'=>$erreur === "", 'message'=>$erreur], $erreur === "" ? 200 : 400);
    }

    private static function agentControler(): string {
    //vérifie la clé de l'agent et retourne son nom ; répond 403 et s'arrête sinon
        $agent = substr((string)preg_replace('/[^A-Za-z0-9_-]/', '', TbAdressage::getPost("S", "agent")), 0, 40);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $agent === '' || !Multipiste::agentCleValide(TbAdressage::getPost("S", "cle"))) {
            self::agentRepondre(['ok'=>false, 'message'=>"Accès refusé"], 403);
        }
        return $agent;
    }

    private static function agentRepondre(array $reponse, int $codeHttp = 200): never {
        http_response_code($codeHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($reponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
