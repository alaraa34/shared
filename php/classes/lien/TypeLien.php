<?php
/*******************************************************************************
 * type de lien associé au lien 
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\toolbox\Toolbox_classe    as TbClasse;
use shared\php\database\Model            as Model;

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
   
    //table commune à toutes les applications (sans préfixe) : contenu de référence dans sh_lien_type.sql
    public const TABLE  = "sh_lien_type";
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
    public static function listePourUnSujet(string $sujet, bool $retourIdSeul = false, string  $zoneSelect = "nomAffiche"){
    //types de lien admis pour un sujet : $sujet est la constante SUJET_LIEN de la classe qui demande les liens
        return self::mdTypesLiensListe($sujet,$retourIdSeul,$zoneSelect);
    }

    public static function listeTous():array{
    //tous les types de lien, pour la grille de paramétrage des usages
        $requete = "SELECT id, nom, nomAffiche, nomLong, externe, icone FROM " . self::TABLE . " ORDER BY nom;";
        return Model::mdRequeteLister($requete);
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
        TbClasse::classeLoadFromArrayAndAlias($this,$infos, $alias);
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
   
    private static function mdTypesLiensListe(string $sujet, bool $retourIdSeul, string  $zoneSelect ):array {
    //retourne les types de lien admis pour un sujet (constante SUJET_LIEN de la classe demandeuse)
    //Retour id seul liste juste les identifiants des types
        $requete = "SELECT lien_type.ID, " . $zoneSelect 
                . " FROM " . self::TABLE . " as lien_type INNER JOIN " . TypeLienUsage_ass::TABLE . " as lien_type_usage on lien_type.ID = lien_type_usage.idTypeLien "
                . " WHERE lien_type_usage.sujet = ? order by nomAffiche;";
        if ($retourIdSeul) {
            return Model::mdRequeteListerZoneUnique($requete, "ID", [$sujet]);
        } else {
            return Model::mdRequeteLister($requete, [$sujet]);
        }
    }
    
    public static function mdTypesLiensListePDF(string $sujet):array {
    //retourne les types de lien PDF admis pour un sujet (constante SUJET_LIEN de la classe demandeuse)
        $requete = "SELECT lien_type.ID as identifiant, nomAffiche as zone
                FROM " . self::TABLE . " as lien_type INNER JOIN " . TypeLienUsage_ass::TABLE . " as lien_type_usage on lien_type.ID = lien_type_usage.idTypeLien 
                WHERE extensions like '%pdf%'  AND lien_type_usage.sujet = ? order by nomAffiche;";
       
        return Model::mdRequeteLister($requete,[$sujet]);
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



