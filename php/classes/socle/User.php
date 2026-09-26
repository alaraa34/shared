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
    //Les mots de passe sont stockés hashés (password_hash). Un mot de passe encore en clair
    //(ancienne base) est accepté une dernière fois puis converti automatiquement en hash.
        $this->pseudo = (string)$pseudo;
        $saisi = (string)$password;
        $tableau = $this->rechercheUsr();
        if (count($tableau)===0 || !self::passwordVerifier($saisi, (string)$tableau['password'])){
            return false;
        }
        //mise à niveau du stockage : hash si encore en clair, ou algorithme plus récent disponible
        if (password_needs_rehash((string)$tableau['password'], PASSWORD_DEFAULT)){
            self::passwordEnregistrer((int)$tableau['id'], $saisi);
        }
        //le mot de passe ne doit jamais rester dans l'instance ni en session
        unset($tableau['password']);
        $this->loadfromarray($tableau);
        //nouvel identifiant de session à la connexion (évite la fixation de session)
        if (!headers_sent()){session_regenerate_id(true);}
        $_SESSION['LOGGED_USER'] = ['id'=>$this->id,
                                    'abrev'=>$this->abrev,
                                    'prenom'=>$this->prenom,
                                    'avatar'=>$this->avatar];
        return true;
    }

    public static function passwordVerifier(string $saisi, string $stocke):bool{
    //vérifie un mot de passe saisi contre la valeur stockée (hash, ou texte clair pour les anciennes bases)
        if ($stocke === ''){return false;}
        if (password_get_info($stocke)['algo'] !== null){
            return password_verify($saisi, $stocke);
        }
        //ancienne valeur en clair : comparaison à temps constant
        return hash_equals($stocke, $saisi);
    }

    public static function passwordEnregistrer(int $idUser, string $motDePasse):bool{
    //enregistre le hash d'un mot de passe (création, changement ou conversion d'un ancien mot de passe)
    //ne fait rien si la colonne password est trop courte pour un hash : sinon le hash serait tronqué
    //et le user ne pourrait plus se connecter. Voir le script SQL de migration.
        if (!self::mdColonnePasswordAssezLongue()){return false;}
        try {
            return Model::mdUpdate(self::TABLE, ['password'=>password_hash($motDePasse, PASSWORD_DEFAULT)], "id=" . $idUser);
        } catch (\Throwable $e) {
            //l'échec de la conversion ne doit pas empêcher la connexion
            error_log("User::passwordEnregistrer : " . $e->getMessage());
            return false;
        }
    }

    //************************************************************************************************
    //MODELE 
    //************************************************************************************************      
 
    private function rechercheUsr() :array{
        //Retourne le détail d'un user à partir de son pseudo (le mot de passe est vérifié en PHP)
        $requete = "SELECT ID as id,abrev,pseudo,password,nom,prenom,avatar
                     FROM " . self::TABLE . " as user 
                     WHERE pseudo = ?;";
         return Model::mdRequeteListerUnique($requete ,[$this->pseudo]);
    }

    private static function mdColonnePasswordAssezLongue() :bool{
        //true si la colonne password peut contenir un hash (60 caractères minimum, 255 recommandé)
        $requete = "SELECT CHARACTER_MAXIMUM_LENGTH as longueur
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'password';";
        $infos = Model::mdRequeteListerUnique($requete, [self::TABLE]);
        return isset($infos['longueur']) && (int)$infos['longueur'] >= 60;
    }
}

