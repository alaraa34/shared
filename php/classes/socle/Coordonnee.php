<?php
declare(strict_types=1);
namespace shared\php\classes\socle;
/*******************************************************************************
 * 
 ******************************************************************************/


use shared\php\classes\socle\Mere as Mere;

class Coordonnee extends Mere
{
    // Attributs
    public string $mail="";
    public string $tel="";
    public string $adresse="";
    public string $ville="";
    // Constantes
    public const TABLE  =  PREFIXE_BDD .  "coordonnee";
  
    // Méthodes
    public function __construct() {
    }
    
    //------------------------------------------------------------------------------------------------
    //CRUD
    //-----------------------------------------------------------------------------------------------
   
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    
    public function setInfos( int $idCoord=0, string|null $mail= null, string|null $adresse=null, string|null $tel=null, string|null $ville=null) :void{
        if ($idCoord > 0) {$this->id = $idCoord;}
        if (!is_null($mail)){$this->mail = $mail; }    
        if (!is_null($adresse)){$this->adresse = $adresse;}
        if (!is_null($tel)){$this->tel = $tel;}
        if (!is_null($ville)){$this->ville = $ville;}
    }
    
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
  
}