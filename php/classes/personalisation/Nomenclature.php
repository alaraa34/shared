<?php
namespace shared\php\classes\personalisation;
/**
 * Description of Nomenclature
 *
 * @author araib
 */

class Nomenclature extends Personalisation{
    
    public const string TABLE = PREFIXE_BDD . "nomenclature";
      
    //*************************************************************************************************************
    //NOMENCLATURE
    //***************************************************************************************************************
    public static function mdNomenclatureGetListe(string $groupe, string $zoneRetour = "Nom", string $ordreTri = "ASC", $zoneTri = "ordre", string $zoneID = "ID"): array {
        if ($zoneRetour=="*"){
            return self::mdGetListeComplete($groupe,$ordreTri,$zoneTri);
        }
        else{
            return self::mdGetListe($groupe, $zoneRetour, $ordreTri, $zoneTri, $zoneID);
        }
    }
      
    public static function mdNomenclatureGetDetail(string $groupe,string $nom, string $zoneRetour="ValeurA",string|int|null $defaut = null ){
        return self::mdGetDetail($groupe, $nom, $zoneRetour);
    }
    
    public static function mdNomenclatureGetDetailFromID(int $ID, string $zoneRetour="valeurA",string|int|null $defaut = null  ){
        return self::mdGetDetailFromID($ID, $zoneRetour);
    }
    
  
    //*************************************************************************************************************
    //HTML paramètre saisie nouveau
    //***************************************************************************************************************
}
