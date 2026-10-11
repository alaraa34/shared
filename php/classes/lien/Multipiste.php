<?php
declare(strict_types=1);
/*******************************************************************************
 * Pistes séparées (voix, batterie, basse, guitare, piano, autre) d'un lien MP3
 *
 * Une ligne de <prefixe>multipiste par lien traité. Le calcul est fait par un agent
 * Python (Demucs) sur un PC : il demande le prochain fichier à traiter, renvoie les
 * pistes une par une, puis signale la fin. Les fichiers sont rangés dans
 *      fichiers/multipistes/<idLien>/t0/<piste>.mp3   (+ pistes.json)   tonalité d'origine
 *      fichiers/multipistes/<idLien>/t+2/<piste>.mp3  (+ pistes.json)   transposée de +2 demi-tons
 * La transposition demandée (colonne tona) est fixée par l'application (ex. tona de scène du titre).
 * Deux sortes de travaux pour l'agent :
 *      complet       : séparation Demucs + transposition éventuelle (MP3 nouveau ou remplacé)
 *      transposition : seule la transposition change, les pistes t0 existantes sont réutilisées
 *
 * Clé de l'agent : première ligne du fichier <projet>/ENVIRagent.txt (jamais versionné,
 * protégé du web par le .htaccess comme les autres ENVIR*.txt)
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\database\Model            as Model;
use shared\php\toolbox\Toolbox_upload    as TbUpload;
use shared\php\toolbox\Toolbox_index     as TbIndex;

class Multipiste
{
    // Constantes
    public const string TABLE = PREFIXE_BDD . "multipiste";

    //statuts
    public const int A_TRAITER = 1;
    public const int EN_COURS  = 2;
    public const int TERMINE   = 3;
    public const int ERREUR    = 4;
    //états calculés pour l'affichage
    public const int A_REFAIRE    = 5;    //terminé mais le MP3 source a été remplacé depuis
    public const int A_TRANSPOSER = 6;    //terminé mais la transposition demandée a changé (tona de scène modifiée)

    //pistes produites par le modèle htdemucs_6s (noms de fichiers) et libellés affichés
    public const array PISTES = ['vocals'=>'Voix', 'drums'=>'Batterie', 'bass'=>'Basse',
                                 'guitar'=>'Guitare', 'piano'=>'Piano', 'other'=>'Autre'];
    //pistes copiées telles quelles lors d'une transposition (la batterie sonne mieux non transposée)
    public const array PISTES_NON_TRANSPOSEES = ['drums'];
    public const string REPERTOIRE = "multipistes";
    //au-delà, un traitement "en cours" est considéré comme abandonné (PC éteint, plantage)
    public const int DELAI_ABANDON_MINUTES = 180;
    //un agent qui n'a pas contacté le site depuis ce délai est considéré comme arrêté (il appelle toutes les 30 s)
    public const int DELAI_AGENT_ACTIF_SECONDES = 120;
    //types de lien dont on peut générer les pistes
    public const array TYPES_SOURCE = [TypeLien::MP3, TypeLien::MP3TB];

    //------------------------------------------------------------------------------------------------
    //ETATS
    //-----------------------------------------------------------------------------------------------
    public static function etatsParLien(array $idLiens): array {
    //retourne [idLien => ligne multipiste] pour les liens demandés
        $idLiens = array_values(array_filter(array_map("intval", $idLiens)));
        if (count($idLiens) === 0) {return [];}
        $retour = [];
        $requete = "SELECT * FROM " . self::TABLE . " WHERE idLien " . Model::mdClauseIn($idLiens);
        foreach (Model::mdRequeteLister($requete) as $ligne) {
            $retour[(int)$ligne['idLien']] = $ligne;
        }
        return $retour;
    }

    public static function etatAffiche(?array $multipiste, string $urlSource, int $tonaAttendue = 0): int {
    //état à afficher pour un lien : 0 = jamais traité
    //$tonaAttendue : transposition en demi-tons que l'application veut entendre (ex. tona de scène)
        if ($multipiste === null) {return 0;}
        $etat = (int)$multipiste['statut'];
        if ($etat === self::TERMINE) {
            if ((int)$multipiste['tailleSource'] !== self::tailleFichier($urlSource)) {return self::A_REFAIRE;}
            if ((int)$multipiste['tona'] !== $tonaAttendue) {return self::A_TRANSPOSER;}
        }
        return $etat;
    }

    public static function iconeHtml(array $lien, int $etat, string $urlDemande): string {
    //icône d'un MP3 colorée selon l'état, cliquable sauf pendant l'attente
    //$lien : url, nomAffiche (type de lien), message (erreur éventuelle)
        $textes = [0                  => ['text-danger',  'pas encore traité : cliquer pour générer les pistes'],
                   self::A_TRAITER    => ['text-warning', 'en attente de traitement par un PC'],
                   self::EN_COURS     => ['text-warning', 'traitement en cours sur un PC : cliquer pour le relancer si ce PC est arrêté'],
                   self::TERMINE      => ['text-success', 'pistes générées : cliquer pour les régénérer'],
                   self::ERREUR       => ['text-danger',  'erreur lors du dernier traitement : cliquer pour relancer'],
                   self::A_REFAIRE    => ['text-danger',  'le MP3 a changé depuis la génération : cliquer pour régénérer'],
                   self::A_TRANSPOSER => ['text-danger',  'tonalité de scène modifiée : cliquer pour générer la transposition (rapide)']];
        [$couleur, $texte] = $textes[$etat] ?? $textes[0];
        //cause de l'erreur enregistrée par l'agent
        if ($etat === self::ERREUR && ($lien['message'] ?? '') !== '') {$texte .= ' (' . $lien['message'] . ')';}
        $titre = htmlspecialchars($lien['nomAffiche'] . " " . basename($lien['url']) . " : " . $texte, ENT_QUOTES);
        $html = '<i class="fa-regular fa-file-audio fs-3 ' . $couleur . '"></i>';
        if ($etat === self::EN_COURS) {$html .= '<i class="bi bi-hourglass-split fs-5 ms-1 text-warning"></i>';}
        if ($etat === self::A_TRAITER) {
            return '<span class="me-2" title="' . $titre . '">' . $html . '</span>';
        }
        //confirmation avant de refaire un traitement terminé ou de relancer un traitement en cours (PC arrêté)
        $questions = [self::TERMINE  => 'Régénérer les pistes de ce fichier ?',
                      self::EN_COURS => 'Relancer ce fichier ? A faire seulement si le PC qui le traite est arrêté.'];
        $confirmation = isset($questions[$etat]) ? ' onclick="return confirm(\'' . $questions[$etat] . '\')"' : '';
        return '<a class="me-2" href="' . $urlDemande . '" title="' . $titre . '"' . $confirmation . '>' . $html . '</a>';
    }

    //------------------------------------------------------------------------------------------------
    //DEMANDE (page de pilotage)
    //-----------------------------------------------------------------------------------------------
    public static function demander(int $idLien, int $idDemandeur, int $tona = 0, bool $complet = true): bool {
    //met un lien "à traiter" ; refusé si ce n'est pas un MP3
    //$tona : transposition voulue en demi-tons ; $complet = false : seule la transposition est à refaire
    //un traitement en cours peut être relancé (PC arrêté) : l'agent qui le faisait verra ses envois refusés
        $lien = Model::mdRequeteListerUnique("SELECT id, idTypeLien FROM " . Lien::TABLE . " WHERE id=?", [$idLien]);
        if (count($lien) === 0 || !in_array((int)$lien['idTypeLien'], self::TYPES_SOURCE, true)) {return false;}
        $tona = max(-12, min(12, $tona));
        //sans pistes d'origine complètes, il faut tout refaire
        if (!$complet && !self::pistesCompletes($idLien, 0)) {$complet = true;}
        $existant = Model::mdRequeteListerUnique("SELECT statut FROM " . self::TABLE . " WHERE idLien=?", [$idLien]);
        if (!$complet && $tona === 0) {
            //retour à la tonalité d'origine : les pistes t0 existent déjà, rien à calculer
            return Model::mdUpdate(self::TABLE, ['statut'=>self::TERMINE, 'tona'=>0, 'message'=>''], "idLien=" . $idLien);
        }
        $zones = ['statut'=>self::A_TRAITER, 'dateDemande'=>date('Y-m-d H:i:s'), 'idDemandeur'=>$idDemandeur,
                  'dateDebut'=>null, 'dateFin'=>null, 'agent'=>'', 'message'=>'', 'tona'=>$tona, 'complet'=>(int)$complet];
        if (count($existant) === 0) {
            return Model::mdInsert(self::TABLE, ['idLien'=>$idLien] + $zones + ['tailleSource'=>0]);
        }
        return Model::mdUpdate(self::TABLE, $zones, "idLien=" . $idLien);
    }

    public static function supprimerPistesDuLien(int $idLien): void {
    //lien supprimé : suppression de ses pistes (toutes tonalités) et de sa ligne de suivi, pour libérer l'espace disque
        if ($idLien <= 0) {return;}
        $dossier = TbUpload::fichierPath() . self::REPERTOIRE . "/" . $idLien;
        foreach (glob($dossier . "/t*", GLOB_ONLYDIR) ?: [] as $sousDossier) {
            self::viderRepertoire($sousDossier);
            @rmdir($sousDossier);
        }
        if (is_dir($dossier)) {@rmdir($dossier);}
        try {
            Model::mdDelete(self::TABLE, ['idLien'=>$idLien]);
        }
        catch (\Throwable $erreur) {
            //application sans table multipiste : rien à supprimer
        }
    }

    //------------------------------------------------------------------------------------------------
    //LECTEUR (affichage du lecteur multipiste ; comportement dans shared/js/multipiste.js)
    //-----------------------------------------------------------------------------------------------
    public static function sourceLecteur(int $idLien, string $url, ?array $multipiste, int $tona): array {
    //ce que le lecteur doit jouer pour un lien MP3 (idLien 0 = pas de MP3)
    //retour : mode (pistes, simple, absent), base (dossier des pistes), mp3 (url du MP3), avertissement
        if ($idLien === 0 || $url === '') {
            return ['mode'=>'absent', 'base'=>'', 'mp3'=>'', 'avertissement'=>''];
        }
        $etat = self::etatAffiche($multipiste, $url, $tona);
        if ($etat === self::TERMINE && self::pistesCompletes($idLien, $tona)) {
            return ['mode'=>'pistes', 'base'=>self::urlWeb(self::repertoire($idLien, $tona)) . '/', 'mp3'=>'', 'avertissement'=>''];
        }
        //transposition à faire ou en cours : les pistes d'origine restent jouables en attendant
        $transpositionSeule = $etat === self::A_TRANSPOSER || ($multipiste !== null && (int)$multipiste['complet'] === 0);
        if ($transpositionSeule && $etat !== self::A_REFAIRE && self::pistesCompletes($idLien, 0)) {
            return ['mode'=>'pistes', 'base'=>self::urlWeb(self::repertoire($idLien, 0)) . '/', 'mp3'=>'',
                    'avertissement'=>'pistes en tonalité d\'origine (transposition pas encore générée)'];
        }
        //pas de pistes générées pour ce lien : lecture du MP3 lui-même
        return ['mode'=>'simple', 'base'=>'', 'mp3'=>self::urlWeb(self::cheminSource($url)), 'avertissement'=>''];
    }

    public static function lecteurHtml(array $source, string $libelleType = ''): string {
    //lecteur replié : lecture, position, temps ; dépliable pour choisir les pistes
    //$libelleType : nom du type de MP3 choisi, pour l'étiquette du lecteur rouge (ex. original, groupe)
        if ($source['mode'] === 'absent') {
            $etiquette = $libelleType === '' ? 'Pas de mp3 pour ce titre' : 'Pas de mp3 de type ' . $libelleType . ' pour ce titre';
            return '<div class="mp-lecteur mp-absent"><div class="mp-barre">'
                 . '<button type="button" class="btn btn-sm mp-play" disabled aria-label="Lecture"><i class="bi bi-play-fill"></i></button>'
                 . '<input type="range" class="form-range mp-position" disabled value="0" aria-label="Position">'
                 . '<span class="mp-etiquette">' . htmlspecialchars($etiquette) . '</span></div></div>';
        }
        $pistes = $source['mode'] === 'pistes' ? implode(',', array_keys(self::PISTES)) : '';
        $html = '<div class="mp-lecteur mp-mode-' . $source['mode'] . '"'
              . ' data-base="' . htmlspecialchars($source['base'], ENT_QUOTES) . '"'
              . ' data-mp3="' . htmlspecialchars($source['mp3'], ENT_QUOTES) . '"'
              . ' data-pistes="' . $pistes . '">'
              . '<div class="mp-barre">'
              . '<button type="button" class="btn btn-sm mp-play" aria-label="Lecture"><i class="bi bi-play-fill"></i></button>'
              . '<input type="range" class="form-range mp-position" min="0" max="0" step="0.1" value="0" aria-label="Position">'
              . '<span class="mp-temps">0:00</span>';
        if ($source['mode'] === 'pistes') {
            $html .= '<button type="button" class="btn btn-sm mp-deplier" title="Choisir les pistes" aria-expanded="false">'
                   . '<i class="bi bi-sliders2-vertical"></i></button>';
        }
        $html .= '</div>';
        if ($source['mode'] === 'pistes') {
            $html .= '<div class="mp-pistes" hidden>';
            foreach (self::PISTES as $code => $libelle) {
                $html .= '<label class="mp-piste"><input type="checkbox" class="form-check-input" value="' . $code . '" checked> '
                       . $libelle . '</label>';
            }
            $html .= '</div>';
        }
        if ($source['avertissement'] !== '') {
            $html .= '<div class="mp-avertissement">' . htmlspecialchars($source['avertissement']) . '</div>';
        }
        return $html . '</div>';
    }

    //------------------------------------------------------------------------------------------------
    //AGENT
    //-----------------------------------------------------------------------------------------------
    public static function agentCleValide(string $cle): bool {
    //compare la clé envoyée par l'agent avec celle du fichier ENVIRagent.txt du projet
        $fichier = TbIndex::projetPath() . "ENVIRagent.txt";
        if (strlen($cle) < 20 || !is_file($fichier)) {return false;}
        $attendue = trim((string)strtok((string)file_get_contents($fichier), "\r\n"));
        return strlen($attendue) >= 20 && hash_equals($attendue, $cle);
    }

    public static function agentsActifs(): array {
    //agents qui ont contacté le site récemment : [nom => secondes depuis le dernier contact]
        $retour = [];
        foreach (self::agentsLirePresences() as $nom => $horodatage) {
            $ecart = time() - (int)$horodatage;
            if ($ecart <= self::DELAI_AGENT_ACTIF_SECONDES) {$retour[$nom] = $ecart;}
        }
        return $retour;
    }

    private static function agentSignalerPresence(string $agent): void {
    //note l'heure du dernier contact de l'agent (fichier agents.json du dossier des pistes)
        $presences = self::agentsLirePresences();
        $presences[$agent] = time();
        if (TbUpload::uploadIsDirOrCreateIt(self::fichierPresences(true))) {
            @file_put_contents(self::fichierPresences(), json_encode($presences), LOCK_EX);
        }
    }

    private static function agentsLirePresences(): array {
        $contenu = is_file(self::fichierPresences()) ? (string)@file_get_contents(self::fichierPresences()) : '';
        return is_array($tableau = json_decode($contenu, true)) ? $tableau : [];
    }

    private static function fichierPresences(bool $dossierSeul = false): string {
        $dossier = TbUpload::fichierPath() . self::REPERTOIRE;
        return $dossierSeul ? $dossier : $dossier . "/agents.json";
    }

    //version minimale de l'agent (agent_pistes.py, constante VERSION) pour les travaux avec transposition
    public const int VERSION_AGENT_TRANSPOSITION = 2;

    public static function agentProchain(string $agent, int $versionAgent = 1): array {
    //réserve le prochain lien à traiter pour cet agent et retourne la description du travail
    //retourne ['idLien'=>0] s'il n'y a rien à faire
        self::agentSignalerPresence($agent);
        self::agentRelancerAbandons();
        $requete = "SELECT mp.idLien, mp.tona, mp.complet, lie.url FROM " . self::TABLE . " as mp INNER JOIN " . Lien::TABLE . " as lie ON lie.id = mp.idLien
                    WHERE mp.statut = ? ORDER BY mp.dateDemande, mp.idLien";
        foreach (Model::mdRequeteLister($requete, [self::A_TRAITER]) as $candidat) {
            $idLien = (int)$candidat['idLien'];
            $tona = (int)$candidat['tona'];
            //réservation : ne réussit que pour un seul agent si plusieurs PC demandent en même temps
            $reserve = Model::mdUpdate(self::TABLE,
                ['statut'=>self::EN_COURS, 'agent'=>$agent, 'dateDebut'=>date('Y-m-d H:i:s'), 'message'=>''],
                "idLien=" . $idLien . " AND statut=" . self::A_TRAITER);
            if (!$reserve) {continue;}
            if (!is_file(self::cheminSource($candidat['url']))) {
                self::agentTerminer($idLien, $agent, false, "Fichier MP3 introuvable sur le serveur");
                continue;
            }
            if (($tona !== 0 || (int)$candidat['complet'] === 0) && $versionAgent < self::VERSION_AGENT_TRANSPOSITION) {
                self::agentTerminer($idLien, $agent, false,
                    "Agent trop ancien pour transposer : recopier shared/outils/multipistes/agent_pistes.py dans C:\\multipistes, puis relancer");
                continue;
            }
            $complet = (int)$candidat['complet'] === 1 || !self::pistesCompletes($idLien, 0);
            if ($complet) {
                //on repart de dossiers vides pour ne pas mélanger avec une génération précédente
                self::viderRepertoireLien($idLien);
            }
            elseif ($tona !== 0) {
                self::viderRepertoire(self::repertoire($idLien, $tona));
            }
            return ['idLien'=>$idLien, 'mode'=>$complet ? 'complet' : 'transposition', 'tona'=>$tona,
                    'chemin'=>self::cheminWebSource($candidat['url']),
                    'pistes'=>array_keys(self::PISTES), 'nonTransposees'=>self::PISTES_NON_TRANSPOSEES,
                    'cheminsT0'=>$complet ? [] : array_map(fn($piste) => self::urlWeb(self::repertoire($idLien, 0) . "/" . $piste . ".mp3"),
                                                          array_combine(array_keys(self::PISTES), array_keys(self::PISTES)))];
        }
        return ['idLien'=>0];
    }

    public static function agentRecevoir(int $idLien, string $agent, string $piste, array $fichier, int $tona = 0): string {
    //enregistre une piste envoyée par l'agent (tona 0 = pistes d'origine) ; retourne "" si ok, sinon le message d'erreur
        self::agentSignalerPresence($agent);
        if (!array_key_exists($piste, self::PISTES)) {return "Piste inconnue";}
        $ligne = self::agentLigneEnCours($idLien, $agent);
        if ($ligne === null) {return "Ce fichier n'est pas en cours de traitement par cet agent";}
        if ($tona !== 0 && $tona !== (int)$ligne['tona']) {return "Transposition non demandée";}
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "Envoi refusé par le serveur (code " . ($fichier['error'] ?? '?') . ", taille maximale " . ini_get('upload_max_filesize') . ")";
        }
        $repertoire = self::repertoire($idLien, $tona);
        if (!TbUpload::uploadIsDirOrCreateIt($repertoire)) {return "Impossible de créer le dossier des pistes";}
        if (!move_uploaded_file($fichier['tmp_name'], $repertoire . "/" . $piste . ".mp3")) {
            return "Impossible d'enregistrer la piste";
        }
        return "";
    }

    public static function agentTerminer(int $idLien, string $agent, bool $succes, string $message, array $infos = []): string {
    //clôture un traitement ; retourne "" si ok, sinon le message d'erreur
        $ligne = self::agentLigneEnCours($idLien, $agent);
        if ($ligne === null) {return "Ce fichier n'est pas en cours de traitement par cet agent";}
        $tona = (int)$ligne['tona'];
        $url = (string)(Model::mdRequeteListerUnique("SELECT url FROM " . Lien::TABLE . " WHERE id=?", [$idLien])['url'] ?? '');
        if ($succes) {
            foreach (array_unique([0, $tona]) as $t) {
                $manquantes = self::pistesManquantes($idLien, $t);
                if (count($manquantes) > 0) {
                    $succes = false;
                    $message = "Pistes manquantes (" . self::dossierTona($t) . ") : " . implode(", ", $manquantes);
                    break;
                }
            }
        }
        $zones = ['statut'=>$succes ? self::TERMINE : self::ERREUR, 'dateFin'=>date('Y-m-d H:i:s'),
                  'message'=>mb_substr($message, 0, 255)];
        if ($succes) {
            $zones['tailleSource'] = self::tailleFichier($url);
            $commun = ['idLien'=>$idLien, 'source'=>$url, 'tailleSource'=>$zones['tailleSource'], 'pistes'=>self::PISTES,
                       'agent'=>$agent, 'dateGeneration'=>$zones['dateFin']] + $infos;
            //pistes d'origine : description réécrite seulement si elles viennent d'être générées
            if ((int)$ligne['complet'] === 1 || !is_file(self::repertoire($idLien, 0) . "/pistes.json")) {
                self::ecrireDescription($idLien, 0, $commun);
            }
            if ($tona !== 0) {
                self::ecrireDescription($idLien, $tona, $commun + ['nonTransposees'=>self::PISTES_NON_TRANSPOSEES]);
            }
        }
        return Model::mdUpdate(self::TABLE, $zones, "idLien=" . $idLien) ? "" : "Mise à jour de l'état impossible";
    }

    private static function ecrireDescription(int $idLien, int $tona, array $infos): void {
        file_put_contents(self::repertoire($idLien, $tona) . "/pistes.json",
                          json_encode(['tona'=>$tona, 'dossier'=>self::dossierTona($tona)] + $infos,
                                      JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function agentLigneEnCours(int $idLien, string $agent): ?array {
    //ligne multipiste si le lien est en cours de traitement par cet agent, sinon null
        $ligne = Model::mdRequeteListerUnique("SELECT * FROM " . self::TABLE . " WHERE idLien=?", [$idLien]);
        return (count($ligne) > 0 && (int)$ligne['statut'] === self::EN_COURS && $ligne['agent'] === $agent) ? $ligne : null;
    }

    private static function agentRelancerAbandons(): void {
    //remet "à traiter" les traitements en cours depuis trop longtemps
        $limite = date('Y-m-d H:i:s', time() - self::DELAI_ABANDON_MINUTES * 60);
        Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET statut=:aTraiter, message=:message
                                  WHERE statut=:enCours AND dateDebut < :limite",
            ['aTraiter'=>self::A_TRAITER, 'enCours'=>self::EN_COURS, 'limite'=>$limite,
             'message'=>"Relancé : le traitement précédent n'a pas abouti"]);
    }

    //------------------------------------------------------------------------------------------------
    //FICHIERS
    //-----------------------------------------------------------------------------------------------
    public static function dossierTona(int $tona): string {
    //nom du sous-dossier d'une transposition : t0, t+2, t-1...
        return $tona === 0 ? "t0" : sprintf("t%+d", $tona);
    }

    public static function repertoire(int $idLien, int $tona = 0): string {
    //dossier des pistes, relatif au projet comme les url des liens
        return TbUpload::fichierPath() . self::REPERTOIRE . "/" . $idLien . "/" . self::dossierTona($tona);
    }

    private static function pistesManquantes(int $idLien, int $tona): array {
        return array_values(array_filter(array_keys(self::PISTES),
                            fn($piste) => !is_file(self::repertoire($idLien, $tona) . "/" . $piste . ".mp3")));
    }

    private static function pistesCompletes(int $idLien, int $tona): bool {
        return is_file(self::repertoire($idLien, $tona) . "/pistes.json") && count(self::pistesManquantes($idLien, $tona)) === 0;
    }

    private static function viderRepertoire(string $repertoire): void {
        foreach (glob($repertoire . "/*") ?: [] as $fichier) {
            if (is_file($fichier)) {@unlink($fichier);}
        }
    }

    private static function viderRepertoireLien(int $idLien): void {
    //vide toutes les tonalités d'un lien
        foreach (glob(TbUpload::fichierPath() . self::REPERTOIRE . "/" . $idLien . "/t*", GLOB_ONLYDIR) ?: [] as $dossier) {
            self::viderRepertoire($dossier);
        }
    }

    private static function cheminSource(string $url): string {
    //url du lien (fichiers/mp3/x.mp3 ou ./fichiers/...) en chemin relatif au projet
        return ltrim(preg_replace('#^\./#', '', $url), '/');
    }

    private static function urlWeb(string $chemin): string {
    //chemin relatif au projet encodé pour le web (espaces, accents, +...)
        return implode("/", array_map('rawurlencode', explode("/", $chemin)));
    }

    private static function cheminWebSource(string $url): string {
        return self::urlWeb(self::cheminSource($url));
    }

    private static function tailleFichier(string $url): int {
        $chemin = self::cheminSource($url);
        return is_file($chemin) ? (int)filesize($chemin) : 0;
    }
}
