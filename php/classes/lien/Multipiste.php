<?php
declare(strict_types=1);
/*******************************************************************************
 * Pistes séparées (voix, batterie, basse, guitare, piano, autre) d'un lien MP3
 *
 * Une ligne de <prefixe>multipiste par lien traité. Le calcul est fait par un agent
 * Python (Demucs) sur un PC : il demande le prochain fichier à traiter, renvoie les
 * pistes une par une, puis signale la fin. Les fichiers sont rangés dans
 *      fichiers/multipistes/<idLien>/t0/<piste>.mp3   (+ pistes.json)
 * t0 = tonalité d'origine ; t-2, t+1... réservés aux versions transposées.
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
    //état calculé pour l'affichage : terminé mais le MP3 source a été remplacé depuis
    public const int A_REFAIRE = 5;

    //pistes produites par le modèle htdemucs_6s (noms de fichiers) et libellés affichés
    public const array PISTES = ['vocals'=>'Voix', 'drums'=>'Batterie', 'bass'=>'Basse',
                                 'guitar'=>'Guitare', 'piano'=>'Piano', 'other'=>'Autre'];
    public const string REPERTOIRE = "multipistes";
    public const string TONA_ORIGINE = "t0";
    //au-delà, un traitement "en cours" est considéré comme abandonné (PC éteint, plantage)
    public const int DELAI_ABANDON_MINUTES = 180;
    //un agent qui n'a pas contacté le site depuis ce délai est considéré comme arrêté (il appelle toutes les 30 s)
    public const int DELAI_AGENT_ACTIF_SECONDES = 120;
    //types de lien dont on peut générer les pistes
    public const array TYPES_SOURCE = [TypeLien::MP3, TypeLien::MP3TB];

    //------------------------------------------------------------------------------------------------
    //AFFICHAGE
    //-----------------------------------------------------------------------------------------------
    public static function etatsParLien(array $idLiens): array {
    //retourne [idLien => ligne multipiste + 'etat' calculé] pour les liens demandés
        if (count($idLiens) === 0) {return [];}
        $retour = [];
        $requete = "SELECT * FROM " . self::TABLE . " WHERE idLien " . Model::mdClauseIn(array_map("intval", $idLiens));
        foreach (Model::mdRequeteLister($requete) as $ligne) {
            $ligne['etat'] = (int)$ligne['statut'];
            $retour[(int)$ligne['idLien']] = $ligne;
        }
        return $retour;
    }

    public static function etatAffiche(?array $multipiste, string $urlSource): int {
    //état à afficher pour un lien : 0 = jamais traité
        if ($multipiste === null) {return 0;}
        $etat = (int)$multipiste['statut'];
        if ($etat === self::TERMINE && (int)$multipiste['tailleSource'] !== self::tailleFichier($urlSource)) {
            $etat = self::A_REFAIRE;
        }
        return $etat;
    }

    public static function iconeHtml(array $lien, int $etat, string $urlDemande): string {
    //icône d'un MP3 colorée selon l'état, cliquable sauf pendant un traitement
    //$lien : id, url, nomAffiche (type de lien)
        $textes = [0                => ['text-danger',  'pas encore traité : cliquer pour générer les pistes'],
                   self::A_TRAITER  => ['text-warning', 'en attente de traitement par un PC'],
                   self::EN_COURS   => ['text-warning', 'traitement en cours sur un PC : cliquer pour le relancer si ce PC est arrêté'],
                   self::TERMINE    => ['text-success', 'pistes générées : cliquer pour les régénérer'],
                   self::ERREUR     => ['text-danger',  'erreur lors du dernier traitement : cliquer pour relancer'],
                   self::A_REFAIRE  => ['text-danger',  'le MP3 a changé depuis la génération : cliquer pour régénérer']];
        [$couleur, $texte] = $textes[$etat] ?? $textes[0];
        $icone = $etat === self::EN_COURS ? 'bi bi-hourglass-split' : 'bi bi-volume-up-fill';
        $titre = htmlspecialchars($lien['nomAffiche'] . " " . basename($lien['url']) . " : " . $texte, ENT_QUOTES);
        $html = '<i class="' . $icone . ' fs-5 ' . $couleur . '"></i>';
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
    public static function demander(int $idLien, int $idDemandeur): bool {
    //met un lien "à traiter" ; refusé si ce n'est pas un MP3
    //un traitement en cours peut être relancé (PC arrêté) : l'agent qui le faisait verra ses envois refusés
        $lien = Model::mdRequeteListerUnique("SELECT id, idTypeLien FROM " . Lien::TABLE . " WHERE id=?", [$idLien]);
        if (count($lien) === 0 || !in_array((int)$lien['idTypeLien'], self::TYPES_SOURCE, true)) {return false;}
        $existant = Model::mdRequeteListerUnique("SELECT statut FROM " . self::TABLE . " WHERE idLien=?", [$idLien]);
        $zones = ['statut'=>self::A_TRAITER, 'dateDemande'=>date('Y-m-d H:i:s'), 'idDemandeur'=>$idDemandeur,
                  'dateDebut'=>null, 'dateFin'=>null, 'agent'=>'', 'message'=>''];
        if (count($existant) === 0) {
            return Model::mdInsert(self::TABLE, ['idLien'=>$idLien] + $zones + ['tailleSource'=>0]);
        }
        return Model::mdUpdate(self::TABLE, $zones, "idLien=" . $idLien);
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

    public static function agentProchain(string $agent): array {
    //réserve le prochain lien à traiter pour cet agent et retourne ce qu'il faut pour le télécharger
    //retourne ['idLien'=>0] s'il n'y a rien à faire
        self::agentSignalerPresence($agent);
        self::agentRelancerAbandons();
        $requete = "SELECT mp.idLien, lie.url FROM " . self::TABLE . " as mp INNER JOIN " . Lien::TABLE . " as lie ON lie.id = mp.idLien
                    WHERE mp.statut = ? ORDER BY mp.dateDemande, mp.idLien";
        foreach (Model::mdRequeteLister($requete, [self::A_TRAITER]) as $candidat) {
            //réservation : ne réussit que pour un seul agent si plusieurs PC demandent en même temps
            $reserve = Model::mdUpdate(self::TABLE,
                ['statut'=>self::EN_COURS, 'agent'=>$agent, 'dateDebut'=>date('Y-m-d H:i:s'), 'message'=>''],
                "idLien=" . (int)$candidat['idLien'] . " AND statut=" . self::A_TRAITER);
            if (!$reserve) {continue;}
            if (!is_file(self::cheminSource($candidat['url']))) {
                self::agentTerminer((int)$candidat['idLien'], $agent, false, "Fichier MP3 introuvable sur le serveur");
                continue;
            }
            //on repart d'un dossier vide pour ne pas mélanger avec une génération précédente
            self::viderRepertoire((int)$candidat['idLien']);
            return ['idLien'=>(int)$candidat['idLien'], 'chemin'=>self::cheminWebSource($candidat['url']),
                    'pistes'=>array_keys(self::PISTES)];
        }
        return ['idLien'=>0];
    }

    public static function agentRecevoir(int $idLien, string $agent, string $piste, array $fichier): string {
    //enregistre une piste envoyée par l'agent ; retourne "" si ok, sinon le message d'erreur
        self::agentSignalerPresence($agent);
        if (!array_key_exists($piste, self::PISTES)) {return "Piste inconnue";}
        if (!self::agentProprietaire($idLien, $agent)) {return "Ce fichier n'est pas en cours de traitement par cet agent";}
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "Envoi refusé par le serveur (code " . ($fichier['error'] ?? '?') . ", taille maximale " . ini_get('upload_max_filesize') . ")";
        }
        $repertoire = self::repertoire($idLien);
        if (!TbUpload::uploadIsDirOrCreateIt($repertoire)) {return "Impossible de créer le dossier des pistes";}
        if (!move_uploaded_file($fichier['tmp_name'], $repertoire . "/" . $piste . ".mp3")) {
            return "Impossible d'enregistrer la piste";
        }
        return "";
    }

    public static function agentTerminer(int $idLien, string $agent, bool $succes, string $message, array $infos = []): string {
    //clôture un traitement ; retourne "" si ok, sinon le message d'erreur
        if (!self::agentProprietaire($idLien, $agent)) {return "Ce fichier n'est pas en cours de traitement par cet agent";}
        $url = (string)(Model::mdRequeteListerUnique("SELECT url FROM " . Lien::TABLE . " WHERE id=?", [$idLien])['url'] ?? '');
        if ($succes) {
            $manquantes = array_filter(array_keys(self::PISTES), fn($piste) => !is_file(self::repertoire($idLien) . "/" . $piste . ".mp3"));
            if (count($manquantes) > 0) {
                $succes = false;
                $message = "Pistes manquantes : " . implode(", ", $manquantes);
            }
        }
        $zones = ['statut'=>$succes ? self::TERMINE : self::ERREUR, 'dateFin'=>date('Y-m-d H:i:s'),
                  'message'=>mb_substr($message, 0, 255), 'tailleSource'=>$succes ? self::tailleFichier($url) : 0];
        if ($succes) {
            $json = ['idLien'=>$idLien, 'source'=>$url, 'tailleSource'=>$zones['tailleSource'],
                     'tona'=>self::TONA_ORIGINE, 'pistes'=>self::PISTES, 'agent'=>$agent,
                     'dateGeneration'=>$zones['dateFin']] + $infos;
            file_put_contents(self::repertoire($idLien) . "/pistes.json",
                              json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return Model::mdUpdate(self::TABLE, $zones, "idLien=" . $idLien) ? "" : "Mise à jour de l'état impossible";
    }

    private static function agentProprietaire(int $idLien, string $agent): bool {
    //vrai si le lien est en cours de traitement par cet agent
        $ligne = Model::mdRequeteListerUnique("SELECT statut, agent FROM " . self::TABLE . " WHERE idLien=?", [$idLien]);
        return count($ligne) > 0 && (int)$ligne['statut'] === self::EN_COURS && $ligne['agent'] === $agent;
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
    public static function repertoire(int $idLien, string $tona = self::TONA_ORIGINE): string {
    //dossier des pistes, relatif au projet comme les url des liens
        return TbUpload::fichierPath() . self::REPERTOIRE . "/" . $idLien . "/" . $tona;
    }

    private static function viderRepertoire(int $idLien): void {
        foreach (glob(self::repertoire($idLien) . "/*") ?: [] as $fichier) {
            if (is_file($fichier)) {@unlink($fichier);}
        }
    }

    private static function cheminSource(string $url): string {
    //url du lien (fichiers/mp3/x.mp3 ou ./fichiers/...) en chemin relatif au projet
        return ltrim(preg_replace('#^\./#', '', $url), '/');
    }

    private static function cheminWebSource(string $url): string {
    //chemin encodé pour être ajouté à l'adresse du projet par l'agent (espaces, accents...)
        return implode("/", array_map('rawurlencode', explode("/", self::cheminSource($url))));
    }

    private static function tailleFichier(string $url): int {
        $chemin = self::cheminSource($url);
        return is_file($chemin) ? (int)filesize($chemin) : 0;
    }
}
