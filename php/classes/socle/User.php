<?php
declare(strict_types=1);
/*******************************************************************************
 * 
 ******************************************************************************/
namespace shared\php\classes\socle;

use shared\php\database\Model            as Model;
use shared\php\toolbox\Toolbox_classe    as tbClasse;
use shared\php\database\Database         as Database;
use shared\php\toolbox\Toolbox           as Tbx;

class User extends Mere{
    public string $nom="";
    public string $prenom="";
    public string $pseudo="";
    public string $abrev="";
    public string $avatar="";
    public string $mail="";
    public int $actif=1;                //1 : peut se connecter
    public int $administrateur=0;       //1 : accès aux fonctions d'administration
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
    
    public static function estAdministrateur():bool {
    //indique si le user connecté est administrateur (colonne administrateur de la table user)
        return self::userConnecte() && ($_SESSION['LOGGED_USER']['administrateur'] ?? false) === true;
    }
    
    public static function deconnecter(){
    //deconnexion du user si il est connecté
        if(self::userConnecte()){unset($_SESSION['LOGGED_USER']);}
    }
    
    public static function listeTous():array{
    //liste de tous les utilisateurs (sans le mot de passe)
        $requete = "SELECT id, nom, prenom, abrev, pseudo, mail, actif, administrateur, avatar
                    FROM " . self::TABLE . " ORDER BY nom, prenom;";
        return Model::mdRequeteLister($requete);
    }

    public static function genererMotDePasse(int $longueur = 10):string{
    //mot de passe aléatoire lisible (sans 0/O, 1/l/I) pour une création ou une réinitialisation
        $caracteres = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $motDePasse = '';
        for ($i = 0; $i < $longueur; $i++) {
            $motDePasse .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        return $motDePasse;
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
    //teste si le user donne les bons renseignements de connexion (mot de passe stocké hashé)
        $this->pseudo = (string)$pseudo;
        $saisi = (string)$password;
        $tableau = $this->rechercheUsr();
        if (count($tableau)===0 || !password_verify($saisi, (string)$tableau['password'])){
            return false;
        }
        //si PHP propose un algorithme plus récent, le hash est régénéré
        if (password_needs_rehash((string)$tableau['password'], PASSWORD_DEFAULT)){
            self::passwordEnregistrer((int)$tableau['id'], $saisi);
        }
        //le mot de passe ne doit jamais rester dans l'instance ni en session
        $administrateur = (bool)$tableau['administrateur'];
        unset($tableau['password'], $tableau['administrateur']);
        $this->loadfromarray($tableau);
        //nouvel identifiant de session à la connexion (évite la fixation de session)
        if (!headers_sent()){session_regenerate_id(true);}
        $_SESSION['LOGGED_USER'] = ['id'=>$this->id,
                                    'abrev'=>$this->abrev,
                                    'prenom'=>$this->prenom,
                                    'avatar'=>$this->avatar,
                                    'administrateur'=>$administrateur];
        return true;
    }

    public function enregistrer():bool{
    //crée (id = 0) ou met à jour l'utilisateur, sans toucher au mot de passe (voir passwordEnregistrer)
    //requête sans contrôle du nombre de lignes modifiées : enregistrer sans rien changer n'est pas une erreur
        $zones = ['nom'=>$this->nom, 'prenom'=>$this->prenom, 'abrev'=>$this->abrev, 'pseudo'=>$this->pseudo,
                  'mail'=>$this->mail, 'avatar'=>$this->avatar, 'actif'=>$this->actif, 'administrateur'=>$this->administrateur];
        if ($this->id === 0){
            $retour = Model::mdInsert(self::TABLE, $zones);
            if ($retour){$this->id = (int)Database::dbConnect()->lastInsertId();}
            return $retour;
        }
        $zones['id'] = $this->id;
        $requete = "UPDATE " . self::TABLE . " SET " . Model::mdUpdateInsertRequeteZones(Tbx::oterDatas($zones, ['id'])) . " WHERE id=:id";
        return Model::mdRequeteExecuter($requete, $zones);
    }

    public function controlerUnicite():string{
    //vérifie que le pseudo et l'abréviation ne sont pas déjà utilisés par un autre utilisateur
    //retourne un message d'erreur ou une chaine vide
        $requete = "SELECT pseudo, abrev FROM " . self::TABLE . " WHERE (pseudo = ? OR abrev = ?) AND id <> ?;";
        $doublons = Model::mdRequeteLister($requete, [$this->pseudo, $this->abrev, $this->id]);
        foreach ($doublons as $doublon){
            if (strcasecmp((string)$doublon['pseudo'], $this->pseudo) === 0){return "L'identifiant « " . $this->pseudo . " » est déjà utilisé.";}
            if (strcasecmp((string)$doublon['abrev'], $this->abrev) === 0){return "L'abréviation « " . $this->abrev . " » est déjà utilisée.";}
        }
        return "";
    }

    public static function passwordEnregistrer(int $idUser, string $motDePasse):bool{
    //enregistre le hash d'un mot de passe (création ou changement de mot de passe)
        return Model::mdUpdate(self::TABLE, ['password'=>password_hash($motDePasse, PASSWORD_DEFAULT)], "id=" . $idUser);
    }

    //************************************************************************************************
    //MODELE 
    //************************************************************************************************      
 
    private function rechercheUsr() :array{
        //Retourne le détail d'un user à partir de son pseudo (le mot de passe est vérifié en PHP)
        $requete = "SELECT ID as id,abrev,pseudo,password,nom,prenom,avatar,administrateur
                     FROM " . self::TABLE . " as user 
                     WHERE pseudo = ?;";
         return Model::mdRequeteListerUnique($requete ,[$this->pseudo]);
    }
}

