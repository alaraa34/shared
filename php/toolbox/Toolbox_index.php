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
    //paramètres $chemin c'est __FILE__ et $projet la racine du namespace
    //exemple Toolbox_index("theBand")
        define('PROJET', $projet);
        //nom de la fonction à appeler dans le controleur ex ctAfficherListe
        $fonction = (string)filter_input(INPUT_GET, Menu::FONCTION);

        //nom du controleur à charger ex accueil/ctAccueil
        $controleur = (string)filter_input(INPUT_GET, Menu::CONTROLEUR);

        //Recherche de l'identifiant si il existe
        $identifiant = (int)filter_input(INPUT_GET, 'id');


       //traitement par défaut c'est l'accueil
        if (strlen($fonction)===0){
            $fonction = "accueil";
            $controleur = "accueil";
            unset($_SESSION[Database::SESSION_ENVIR]);
            unset($_SESSION[Menu::SESSION_MENU]);
        }

        //chargement du controleur '$projet/src/php/accueil/ctAccueil.php'
        //un seul contrleur dans une répertoire du même nom ex $controleur accueil devient 'theBand/src/php/accueil/ctAccueil.php'
        $namespace = $projet . "\src\php\\" . $controleur;
        $controleur = ROOT_PATH .  "/" .$projet . "/src/php/" . $controleur . "/ct".  \ucfirst($controleur) . ".php";
        require_once($controleur);

        //mise en forme de la fonction appelée dans le controleur,
        //toutes les fonctions qui correspondent à une action dans un controleuur commencent par ct
        $func = $namespace. "\ct". ucfirst($fonction);//ex ctEditList
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