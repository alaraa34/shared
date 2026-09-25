<?php
/*******************************************************************************
 * type de lien associé au lien 
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\toolbox\Toolbox_classe as TbClasse;
use shared\php\database\Model as Model;

class TypeLien
{
    // Attributs
    public int $id = 0;
    public string $couleur = "";
    public string $nom="";
    public string $nomAffiche="";
    public string $nomLong="";
    public string $repertoire="";
    public string $extensions="";
    public string $icone="";
    public bool $externe=true;
   
    public const TABLE  = PREFIXE_BDD . "lien_type";
    //types de lien
    public const MP3 = 1;
    public const MP4 = 29;
    public const MP3A = 6;
    public const SITE = 4;
    public const PAD = 28;
    public const MP3D = 54;
    public const MP3SVX = 31;
       
    // Méthodes
    public function __construct($id=0) {
        if($id > 0){
            $this->id = $id;
            TbClasse::classeLoadFromId($this);
        }
    }
    //------------------------------------------------------------------------------------------------
    //Méthodes statiques publiques
    //-----------------------------------------------------------------------------------------------
    public static function listePourUnSujet(int $sujet, bool $retourIdSeul = false, string  $zoneSelect = "nomAffiche"){
        return self::mdTypesLiensListe($sujet,$retourIdSeul,$zoneSelect);
    }
   
    public static function externes(){
    //retourne laliste des types de liens externes 
        $requete = "SELECT ID FROM " . self::TABLE . " as lien_type WHERE externe=true;";
        return implode(';', Model::mdRequeteListerZoneUnique($requete, "ID"));
    }
    
    public static function extensionsAutoriseesParTypes(){
    //revoie sou forme de tableau encodé json 
        $tableau =  json_encode(self::mdExtensionsAutoriseesParTypes());
        return htmlspecialchars($tableau, ENT_QUOTES, 'UTF-8');
    }
    //------------------------------------------------------------------------------------------------
    //CRUD
    //-----------------------------------------------------------------------------------------------
     
    //------------------------------------------------------------------------------------------------
    // MISES A JOUR
    //-----------------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function loadFromArray(array $lien) :void{
    //Charge toutes les zones si elles sont dans le tableau
    //Comme le détail n'est pas souvent dans les 
        TbClasse::classeLoadFromArray($this,$lien);
    }
    public function loadFromId(){
        $tableau = $this->mdTypesLiensDetail();
        $this->loadFromArray($tableau);
    }
    public function loadFromArrayAlias(array $infos,$alias=""):void{
    //Charge l'instance via un tableau de données avec alias
        tbClasse::classeLoadFromArrayAndAlias($this,$infos, $alias);
    }
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    
    
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    public function mdTypesLiensDetail(): array {
        //retourne un tableau de détail d'un lien 
        $requete = "SELECT * FROM " . self::TABLE . " as lien_type WHERE ID=?;";
        return Model::mdRequeteListerUnique($requete, [$this->id]);
    }
   
    private static function mdTypesLiensListe(int $usage, bool $retourIdSeul, string  $zoneSelect ):array {
    //retourne un tableau des liens possible pour song,prospect,etablissement, répétition ou telechargement 
    //usage = nom de la zone de la table (proposition, song...)
    //Retour nom seul liste juste les types
        $requete = "SELECT lien_type.ID, " . $zoneSelect 
                . " FROM " . self::TABLE . " as lien_type LEFT JOIN " . TypeLienUsage_ass::TABLE . " as lien_type_usage on lien_type.ID =lien_type_usage.idTypeLien "
                . " WHERE saisie = true AND lien_type_usage.idUsage= " . $usage . " order by nomAffiche;";
        if ($retourIdSeul) {
            return Model::mdRequeteListerZoneUnique($requete, "ID");
        } else {
            return Model::mdRequeteLister($requete);
        }
    }
    
    public static function mdTypesLiensListePDF(int $usage):array {
    //retourne un tableau des liens possible pour song
    //usage = nom de la zone de la table (proposition, song...)
    //Retour nom seul liste juste les types
        $requete = "SELECT lien_type.ID as identifiant, nomAffiche as zone
                FROM " . self::TABLE . " as lien_type LEFT JOIN " . TypeLienUsage_ass::TABLE . " as lien_type_usage on lien_type.ID =lien_type_usage.idTypeLien 
                WHERE extensions like '%pdf%'  AND lien_type_usage.idUsage=? order by nomAffiche;";
       
        return Model::mdRequeteLister($requete,[$usage]);
}
    
   public function mdInfosDetail():array{
    //infos de la table de la classe
        $requete = "SELECT * FROM " . static::TABLE. " WHERE id=?";
        return Model::mdRequeteListerUnique($requete, [$this->id]);
    }
    
    private static function mdExtensionsAutoriseesParTypes():array{
    //infos de la table de la classe
        $requete = "SELECT id, extensions FROM " . self::TABLE . " WHERE externe=false ";
        return Model::mdRequeteLister($requete);
    }
}



