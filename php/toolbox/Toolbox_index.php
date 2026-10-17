<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * Fonction outillage divers
 *******************************************************************************/
use shared\php\classes\socle\Menu    as Menu;
use shared\php\database\Database     as Database;

class Toolbox_index {
    
    public static function demarrer(string $projet){
    //paramètre $projet la racine du namespace, exemple Toolbox_index::demarrer("theBand")
    //paramètres de requête (sans préfixe ct) :
    //  dom : domaine = répertoire et namespace  ex song  -> theBand\src\php\song
    //  ctr : controleur dans le domaine         ex song  -> theBand/src/php/song/ctSong.php
    //  fct : fonction appelée dans le controleur ex editer -> ctEditer
    //compatibilité : si dom est absent (anciennes URL ?fct=xx&ctr=yy) le domaine est le controleur
    //                si ctr est absent le controleur est le domaine
        define('PROJET', $projet);
        $fonction   = (string)filter_input(INPUT_GET, Menu::FONCTION);
        $domaine    = (string)filter_input(INPUT_GET, Menu::DOMAINE);
        $controleur = (string)filter_input(INPUT_GET, Menu::CONTROLEUR);

        //Recherche de l'identifiant si il existe
        $identifiant = (int)filter_input(INPUT_GET, 'id');

        //traitement par défaut c'est l'accueil
        if (strlen($fonction)===0){
            $fonction   = "accueil";
            $domaine    = "accueil";
            $controleur = "accueil";
            unset($_SESSION[Database::SESSION_ENVIR]);
            unset($_SESSION[Menu::SESSION_MENU]);
        }
        
        //compatibilité avec les URL qui ne fournissent que ctr et fct
        if (strlen($domaine)===0)   {$domaine = $controleur;}
        if (strlen($controleur)===0){$controleur = $domaine;}
        
        //sécurité : uniquement des noms simples (pas de chemin ../ ni de caractères spéciaux)
        foreach ([Menu::DOMAINE=>$domaine, Menu::CONTROLEUR=>$controleur, Menu::FONCTION=>$fonction] as $nom=>$valeur){
            if (!preg_match('/^[A-Za-z0-9_]+$/', $valeur)){
                http_response_code(404);
                die("TBX010 Paramètre " . $nom . " invalide");
            }
        }

        //chargement du controleur ex dom=song ctr=song -> 'theBand/src/php/song/ctSong.php'
        //plusieurs controleurs possibles dans un même domaine (même namespace)
        $namespace = $projet . "\\src\\php\\" . $domaine;
        $fichier = ROOT_PATH . "/" . $projet . "/src/php/" . $domaine . "/ct" . \ucfirst($controleur) . ".php";
        if (!is_file($fichier)){
            http_response_code(404);
            die("TBX011 Controleur " . $domaine . "/ct" . \ucfirst($controleur) . " introuvable");
        }
        require_once($fichier);

        //mise en forme de la fonction appelée dans le controleur,
        //toutes les fonctions qui correspondent à une action dans un controleur commencent par ct
        $func = $namespace . "\\ct" . \ucfirst($fonction);//ex theBand\src\php\song\ctEditer
        if (!function_exists($func)){
            http_response_code(404);
            die("TBX012 Fonction ct" . \ucfirst($fonction) . " introuvable dans " . $domaine . "/ct" . \ucfirst($controleur));
        }
        if ($identifiant===0) {
             $func();
        }
        else{
            $func($identifiant);
        }
    }
    
    public static function projetPath() :string{
        return ROOT_PATH .  "/" . PROJET . "/" ;
    }
}
