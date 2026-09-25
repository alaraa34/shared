<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * OUTILS SUR VARIABLES GLOBALES ET ADRESSAGE
 *******************************************************************************/
use shared\php\classes\socle\Menu as Menu;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use shared\php\database\Model as Model;
use shared\php\database\Database as Database;

class Toolbox_adressage {
    public static function isDeveloppement(){
        //Is this the production server or not?
        return str_contains((string)filter_input(INPUT_SERVER,'HTTP_HOST'),"localhost");
    }
    
    public static function urlServeur():string { 
    //fonction qui renvoie l'URL du serveur
        if((string)filter_input(INPUT_SERVER,'HTTPS') === 'on') {
          $url = "https"; 
        }
        else{
          $url = "http"; 
        }
        
        // Ajoutez // à l'URL.
        $url .= "://"; 

        // Ajoutez l'hôte (nom de domaine, ip) à l'URL.
        $url .= (string)filter_input(INPUT_SERVER,'HTTP_HOST'). "/". Database::mdGetENVIR()[5]; 

        // Afficher l'URL
        return $url; 
    }
    
    public static function getControleurFonctionPrefixeFromCFPadress(string $adresseCFP) : array {
     //le paramètre modal est sous la forme controleur;fonction;prefixe
    // retourne le controleur, la fonction et le prefixe, si le préfixe n'est pas passé c'est le controleur
        $tableau = explode(";",$adresseCFP);
        $controleur = $tableau[0];
        $fonction =  $tableau[1];
        //préfixe soit le controleur, soit le prefixe si il est donné
        $prefixe = count($tableau)===2 ? $tableau[0] :  $tableau[2];
        return ['controleur'=>$controleur,'fonction'=>$fonction,'prefixe'=>$prefixe];
    }
    
    public static function getURLControleurFromCFPAdress(string $adresseCFP):string {
    //reourne le parametre de connection'URL d'une modale pour une modale, exemple http://localhost/TheBand/index.php?fct=repertoireConcert&ctr=song
    //la zone modale est sous la forme controleur;fonction;prefixe ou controleur;fonction
    //le nom est  est la fonction  etablissementMDLsupprimer avec le controleur avant MDL
        $tableau = self::getControleurFonctionPrefixeFromCFPadress($adresseCFP); //retourne controleur et fonction
        return Toolbox_adressage::getURLstatic($tableau['controleur'],$tableau['prefixe']. ucfirst($tableau['fonction']));
    }
    
    
    public static function getURLstatic(string $controleur, string $fonction,int $id=0):string{
    // met en forme les paramètres  et ctrl et id pour les url lancées en dehors du menu
    // appel shared\php\toolbox\Toolbox_adressage::getURLstatic("accueil","loginUser")
        $adresse = "index.php?" . Menu::FONCTION ."=". $fonction . 
                    "&" . Menu::CONTROLEUR ."=" . $controleur ;
        $adresse .=  $id===0 ? "" : "&id=".$id ;
        return $adresse;
    }
    
    public static function urlControleur (string $controleur, string $fonction, array $parametres=[]) :string{
    // retourne l'url controleur pour un code action et des parametres
        $url = self::urlServeur() . "/" .  self::getURLstatic($controleur,$fonction);
        foreach ($parametres as $parametre){
            $url.= "&" . $parametre;
        }
        return $url;
    }
    
    public static function getPostRadio(string $groupe):int{
    //recherche si il existe un controle à on dans le post avec les nomenclature
    //le name du controle soit être la valeur nom de la nomenclature
        $retour =0;
        $tableau = Nomenclature::mdNomenclatureGetListe($groupe, "nom");
        foreach ($tableau as $nomenclature){
            if (self::getPost("S", $nomenclature['zone'])=="on"){
                $retour = $nomenclature['identifiant'];
                break;
            }
        }
        return $retour;
    }
    
    public static function getPost(string $type, string $nom) {
    //retourne une valeur du post
        switch ($type){
            case "I":
            case "integer":
                $valeur =(int)filter_input(INPUT_POST,$nom);
                break;
            case "D":
            case "double":
                $valeur =(double)filter_input(INPUT_POST,$nom);
                break;
            case "S":
            case "string":
                $valeur =(string)filter_input(INPUT_POST,$nom);
                break;
            case "B":
            case "boolean":
                $valeur =(boolean)filter_input(INPUT_POST,$nom);
                break;
            case "C":
                //pour check box, si valeur présente c'est true sinon false
                //fonction renvoie false si pas trouvé et le value du check box sinon
                if(is_null(filter_input(INPUT_POST,$nom))){
                    //valeur non trouvée
                    $valeur = false;
                }
                else {$valeur = true;}
                break;
            case "F":
            case "float":
                $valeur =(float)filter_input(INPUT_POST,$nom);
                break;
            case "NC":
                $valeur = filter_input(INPUT_POST,$nom);
                break;
            default:
                die ("TBX002 Type de variable ". $type . " non défini dans fonction getPost");
        }
       return $valeur;
    }
    
     public static function getValeurPostZoneGenerique(string $zone): array {
    //Retourne un tableau de tableaux associatifs fait à partir de $post 
    //dont la clé commence par $eonr
        //liste des cles qui commencent par le poste action, ne sont pas le code générioque et ont une action 
        return array_filter(filter_input_array(INPUT_POST), function($v, $k) use($zone) {
                                        return str_starts_with($k,$zone);
                                    }, ARRAY_FILTER_USE_BOTH);
     }
     
    public static function getValeurPostTableau(array $cles, int $posteAction = 1): array {
    //Retourne un tableau de tableaux associatifs fait à partir de $post 
    //paramètre $posteAction désigne le paramètre du tableau qui contient le code action à traiter
    //cle = tableau de clés à examiner avec le même nombre d'items
    //Ne reourne que les éléments avec un code action renseigné
    //appel getValeurPostTableau(['idContact','actionContact','nomPrenom','portable','mail','commentaireContact']);
    //essayer avec filter_input_array
        $listes = [];
        //liste des cles qui commencent par le poste actioçn, ne sont pas le code générioque et ont une action ($v)
        $mouvements= array_filter(filter_input_array(INPUT_POST), function($v, $k) {
                                        return str_starts_with($k,"action") && substr($k,-2,2)<>"99" && strlen($v) ===1;
                                    }, ARRAY_FILTER_USE_BOTH);
        //examen de $nbPostes clés, il peut y avoir des trous alors pas de break
        //chaque mouvement ds mouvement est un mouvement à traiter
        foreach($mouvements as $codeAction=>$valeur){
            $codeActionCourt = explode("&",$cles[$posteAction])[0];
            // si code action est le bon car il pourrait y avoir des actions sur plusieurs listes
            if (str_starts_with($codeAction,$codeActionCourt)){
                //extraction du poste sur le code action 
                $poste = (int)substr($codeAction,strlen($codeActionCourt));
                //examen des clés
                $liste = [];
                foreach ($cles as $cle){
                    //test si la clé est typée exemple texte&S texte est de tyoe string, retourne false si pas trouvée
                    $retour =  self::getValeurPostTableauTraiterCle($cle,$poste);
                    //retourne getValeurPostTableauTraiterClela clé sans le typage et sans le poste et la valeur
                    $cleSansPoste = substr($retour[0],0,-strlen(strval($poste)));
                    $liste[$cleSansPoste]  = $retour[1];
                }
                //ajout du poste, la valeur sera utilisée pour récupérer le fichier si besoin
                $liste['poste']=$poste;
                $listes[]= $liste;
            }
        }
        return $listes;
    }

    public static function getValeurPostTableauTraiterCle(string $cle, int $poste):array{
    //traite une clé et renvoie la valeur exemple idCle&I
        if (str_contains($cle,"&")){
            //si une valeur type, slit du nom avec ce séparateur
            $cleTableau = explode("&",$cle);
            //calcul de la clé à recherche dans le post
            $clepost= $cleTableau[0]. strval($poste); 
            $valeur = self::getpost($cleTableau[1],$clepost);
        }
        else{
           //pas typé
            $clepost= $cle . strval($poste); //nom de la clé à chercher
            $valeur =self::getPost("NC",$clepost); //retour de la clé sans typage
        }

        return array($clepost,$valeur);
    }
    
    public static function projetGetRacinePHP($namespace){
    //retourne la racine du projet par exemple thenBand\src\php
        $tableau = explode("\\",$namespace);
        return $tableau[0] ."/src/php/" ;   
    }
    
    public static function projetGetLayout($namespace) :string {
        return ROOT_PATH . self::projetGetRacinePHP($namespace) ."socle/tpLayout.php" ;   
    }
    
    public static function getReferer(int $id ):string{
    //retourne le referer ou # si existe pas
        return $id === 0 ? "#": filter_input(INPUT_SERVER, 'HTTP_REFERER') ?? '#';
    }
}