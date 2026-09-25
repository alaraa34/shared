<?php
declare(strict_types=1);
/*******************************************************************************
 * 
 ******************************************************************************/
namespace shared\php\classes\socle;

use shared\php\database\Model as Model;
use shared\php\toolbox\Toolbox_classe as tbClasse;

class User extends Mere{
    public string $nom="";
    public string $prenom="";
    public string $pseudo="";
    public string $abrev="";
    public string $avatar="";
    private string $password="";
    
    //Constantes
    public CONST TABLE = PREFIXE_BDD . "user";
     
    //Méthodes
    public function __construct($id = 0) {
        parent::__construct($id);
        if($id >0){
            $this->loadfromid();
        }
    }
    //------------------------------------------------------------------------------------------------
    //Méthodes statiques publiques
    //-----------------------------------------------------------------------------------------------
    public static function connectUserGetImage(): string {
        return "images/" . self::connectUserGetInfo("avatar","defaut.jpeg");
    }

    public static function connectUserGetInfo(string $nomInfo = 'abrev', $defaut=0 ) {
    //retourne soit la valeur de la zone soit la valeur du défaut
        return self::userConnecte() ? $_SESSION['LOGGED_USER'][$nomInfo]:$defaut;
    }
    
     public static function getUserConnecte():array {
    //indique si un user est conneceté
        return self::userConnecte() ? $_SESSION['LOGGED_USER']:[];
    }
    
    public static function userConnecte():bool {
    //indique si un user est conneceté
        return isset($_SESSION['LOGGED_USER']);
    }
    
    public static function deconnecter(){
    //deconnexion du user si il est connecté
        if(self::userConnecte()){unset($_SESSION['LOGGED_USER']);}
    }
    
    public static function listeTous(){
        return self::liste();
    }
       
    
    //************************************************************************************************
    //CHARGEMENT
    //************************************************************************************************          
    public function loadFromArrayAlias(array $infos,$alias=""):void{
    //Charge l'instance via un tableau de données avec alias
        //Identifiant
        tbClasse::classeLoadFromArrayAndAlias($this,$infos, $alias);
    }
    public function loadFromArray(array $infos):void{
    //Charge l'instance via un tableau de données avec alias
        //Identifiant
        tbClasse::classeLoadFromArray($this,$infos);
    }
    
    //************************************************************************************************
    //METIER
    //************************************************************************************************    
    public function getProperty(string $propriete){
    //vérifie si la classe a été chargée avant de retourner la propriété
        if (strlen($this->$propriete)===0 and $this->id > 0){$this->loadFromId();}
        return  $this->$propriete;
    }  
    
    public  function isValid($pseudo,$password) :bool{
    //teste si le user donne les bons renseignements de connexion
        $this->pseudo = $pseudo;
        $this->password= $password;
        $tableau = $this->rechercheUsrPwd();
        if (count($tableau)===0){
            return false;
        }
        else{
            $this->loadfromarray($tableau);
            $_SESSION['LOGGED_USER'] = ['id'=>$this->id,
                                        'abrev'=>$this->abrev,
                                        'prenom'=>$this->prenom,
                                        'avatar'=>$this->avatar];  
            return true;
        } 
    }
    //************************************************************************************************
    //MODELE 
    //************************************************************************************************      
 
    private function rechercheUsrPwd() :array{
        //Retourne le détail d'un user à partir de sonid, identifiant, abrev
        $requete = "SELECT ID as id,abrev,pseudo,password,nom,prenom,avatar
                     FROM " . self::TABLE . " as user 
                     WHERE pseudo = ? AND password=?;";
         return Model::mdRequeteListerUnique($requete ,[$this->pseudo,$this->password]);
    }   
}

