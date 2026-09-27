<?php
namespace shared\php\classes\personalisation;
/**
 * Description of Nomenclature
 *
 * @author araib
 */
use shared\php\toolbox\Toolbox_classe    as TbClasse;
use shared\php\database\Model            as Model;

class Parametre extends Personalisation {
    public string $groupe="";
    public string $nom="";
    public int $ordre=0;
    public string $libelle="";
    public string $valeurA="";
    public float $valeurN=0;
    public DateTime $valeurD;
    public bool $valeurB = false;
    
    public const string TABLE = PREFIXE_BDD. "parametre";
     
      
    //*************************************************************************************************************
    //PARAMETRES
    //***************************************************************************************************************
    public static function mdParametreGetListe(string $groupe, string $zoneRetour = "Nom", string $ordreTri = "ASC", 
                $zoneTri = "ordre", string $zoneID = "ID"): array {
        return self::mdGetListe($groupe, $zoneRetour, $ordreTri, $zoneTri, $zoneID);
    }
    public static function mdParametreGetDetail(string $groupe,string $nom, string $zoneRetour,string|int|null $defaut = null){
        return self::mdGetDetail($groupe, $nom, $zoneRetour, $defaut);
    }
    public static function mdParametreGetDetailFromID(int $ID, string $zoneRetour,string|int|null $defaut = null ){
       return self::mdGetDetailFromID($ID, $zoneRetour, $defaut);
    } 
    
    public function set (array $tableau){
        TbClasse::classeLoadFromArray($this, $tableau);
    }
    //*************************************************************************************************************
    //Saisie nouveau
    //***************************************************************************************************************
    public function creer(array $tableau):bool{
        return Model::mdInsert($this::TABLE, $tableau);
    }
}
