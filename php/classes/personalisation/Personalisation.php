<?php
namespace shared\php\classes\personalisation;
/**
 * Description of Nomenclature
 *
 * @author araib
 */
use \shared\php\database\Model       as Model;
use shared\php\toolbox\Toolbox       as Tbx;
use shared\php\classes\socle\Mere    as Mere;

abstract class Personalisation extends Mere {
    
        
    //*************************************************************************************************************
    //COMMUNS
    //***************************************************************************************************************
    protected  static function retourUnique(string $requete, string $zoneRetour, array $parametres, string|int|null $defaut){
    //gère le fait qu'un paramètre ou une nomenclature unique doivent exister
        $tableau = Model::mdRequeteListerZoneUnique($requete, $zoneRetour,$parametres);
        if (count($tableau)===0){
            if (is_null($defaut)){
                die ("La requete " . $requete . Tbx::listerTableau($parametres,"paramètres") . " n'a pas de resultat");
            }
            else{
                return $defaut;
            }
        }
        else{
            return $tableau[0];
        }
    }
    
   protected static function mdGetListe(string $groupe, string $zoneRetour, string $ordreTri, 
                        $zoneTri, string $zoneID): array {
    //donne la liste des nomenclatures d'un groupe donné 
    // $zone retour permet de préciser la zone à retourner (valeurA, ValeurN...)
    // par défaut c'est la liste des noms
    // zone tri si la valeur de tri n'est pas ordre
    // retourne un tableau de tableaux associatifs ['identifiant','zone']

        $requete = "SELECT " . $zoneID . " as identifiant, " . $zoneRetour . " as zone "
                . "FROM " . static::TABLE 
                . " WHERE groupe=? "
                . " ORDER BY " . $zoneTri . " " . $ordreTri . ";";
        return Model::mdRequeteLister($requete, [$groupe]);
    }
    
    protected static function mdGetListeComplete(string $groupe, string $ordreTri, $zoneTri): array {
    //donne la liste des nomenclatures d'un groupe donné 
    // $zone retour permet de préciser la zone à retourner (valeurA, ValeurN...)
    // par défaut c'est la liste des noms
    // zone tri si la valeur de tri n'est pas ordre
    // retourne un tableau de tableaux associatifs ['identifiant','zone']

        $requete = "SELECT * "
                . " FROM " . static::TABLE 
                . " WHERE groupe=? "
                . " ORDER BY " . $zoneTri . " " . $ordreTri . ";";
        return Model::mdRequeteLister($requete, [$groupe]);
    }
    
    protected static function mdGetDetail(string $groupe,string $nom, string $zoneRetour, string|int|null $defaut=null ){
        $requete = "SELECT " . $zoneRetour . " FROM " . static::TABLE   . " WHERE groupe=? and nom = ?";
        return self::retourUnique($requete, $zoneRetour, [$groupe,$nom],$defaut);
    }
    
    protected static function mdGetDetailFromID(int $ID, string $zoneRetour, string|int|null $defaut=null){
        $requete = "SELECT " . $zoneRetour . " FROM " . static::TABLE   . " WHERE ID=?";
        return self::retourUnique($requete, $zoneRetour, [$ID],$defaut);
    } 
    
    
}
