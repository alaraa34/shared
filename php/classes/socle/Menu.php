<?php
declare(strict_types=1);
namespace shared\php\classes\socle;
/* *****************************************************************************
 * classe menu
 * le menu et composé à partir de la table menu. Dutant une session il est stocké dans une variable de session $_SESSION['menu'])
 * cette variable est détruite si l'utilisateur se déconnecte
 ********************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;
use shared\php\database\Model as Model;

class Menu {
  
    public string $myhtml="";
    private string $indentationPrecedente="";
    private string $typeLignePrecedent="";
    private string $indentation="";
    private string $typeLigne="";
    private string $libelle="";
    private string $action="";
    private string $controleur="";
    private bool $production=false;
    
    
    //Table
    private const string TABLE = PREFIXE_BDD. "menu";
    //constante paramètre de requete
    public const FONCTION = "fct";
    public const CONTROLEUR = "ctr";
    //Constantes
    public  const string SESSION_MENU = "MENU";
    private const string MENU = "menu";
    private const string DIVIDER = "divider";
    private const string DROPDOWN = "dropdown";
    private const string ACTION = "action";
    private const string EXTERNE = "externe";
    
    //Méthodes
    public function __construct() {
        if (isset($_SESSION[self::SESSION_MENU])){
            $this->myhtml = $_SESSION[self::SESSION_MENU];
        }
        else{
            $this->ecrireMenu();
            $_SESSION[self::SESSION_MENU]= $this->myhtml;
        }
    } 
    
    //************************************************************************************************
    //CHARGEMENT
    //************************************************************************************************          
    private function ligneTraiter(){
        switch ($this->typeLigne){
            case self::DROPDOWN :
                $this->ligneDropdown();
                break;
            case self::DIVIDER :
                $this->ligneDivider();
                break;
            case self::MENU :
                //Ligne de menu sans sous menu
                $this->ligneMenu();
                break;
            case self::EXTERNE :
                $this->ligneExterne();
                break;
            case self::ACTION :
                //ligne de menu qui génère une action
                $this->ligneAction();
        }
    }
    private function charger(array $tableau):void{
        $this->action = $tableau['action'];
        $this->typeLigne = $tableau['typeLigne'];
        $this->indentation = sprintf("%'.06d\n", $tableau['indentation']);
        $this->libelle = $tableau['libelle'];
        $this->controleur = $tableau['domaine'];
        if ($tableau['production']===1){$this->production = true;}
    }
  
    //************************************************************************************************
    //METIER
    //************************************************************************************************  
    private function ecrireMenu(){
    //constitution du menu avant stockage
        $tableau = $this->getdetails();
        $this->indentationPrecedente = sprintf("%'.06d\n", $tableau[0]['indentation']);
        foreach($tableau as $poste){
            $this->charger($poste);
            $this->findeUL();
            $this->ligneTraiter();
            //mise en mémoire des valeurs précédentes
            $this->indentationPrecedente = $this->indentation;
            $this->typeLignePrecedent = $this->typeLigne;
        }
        $this->myhtml.= "</ul></li>";
    }
    private function findeUL(){
    //rajout si pas menu simple et si changement de niveau
        if ($this->typeLignePrecedent != self::MENU &&  $this->testRuptureNiveau()){$this->myhtml .= "</ul></li>";}
    }
    
   
    private function ligneDivider (){
        $this->myhtml .= "<li><hr class=\"dropdown-divider\">";
        if (strlen($this->libelle)>0){$this->myhtml .= "<strong>".$this->libelle . "</strong>";}
        $this->myhtml .= "</li>";
    }
    
    private function ligneDropdown (){
        $this->myhtml .= "<li class=\"nav-item dropdown\"> " . 
                "<a class=\"nav-link dropdown-toggle\" href=\"#\" role=\"button\" data-bs-toggle=\"dropdown\" aria-expanded=\"false\">" .
                $this->libelle .
                "</a>" .
                "<ul class=\"dropdown-menu\">";
    }
    
    private function ligneMenu(){
    //C'est un élément de menu sans dropdown
         $this->myhtml .= "<li class=\"nav-item\">
                    <a class=\"nav-link active\" aria-current=\"page\" href=\"" .
                            $this->formatterParametresURL() ."\">" .$this->libelle ."</a>
                    </li>";
    }
    private function ligneAction(){
    //ligne qui va appeler un controleur
    //ex<li><a class="dropdown-item" href="index.php?action=editerPlanningIndividuel">Individuel</a></li>
        $this->myhtml .= "<li><a class=\"dropdown-item\" href=\"". 
                    $this->formatterParametresURL() . "\">" . $this->libelle ."</a></li>";
    }
   
    private function ligneExterne(){
        $this->myhtml .= "<li><a class=\"dropdown-item\" href=\"" . $this->action . "\" target='_blank' rel='noopener'>". $this->libelle. "</a></li>";
    }
    
    private function testRuptureNiveau():bool{
    //teste si une des trois niveaux est en rupture, code indentation sous form NN NN NN
        $retour = false;
      
        if (substr($this->indentation,0,2) != substr($this->indentationPrecedente,0,2)){
            $retour = true;
        }
       
        return $retour;
    }
    
    private function formatterParametresURL():string{
    // met en forme les paramètres action et ctrl
    // modele"http://www.votredomaine.com/?usertype=admin&program=kali%20linux&level=top%20secret"
    
        return TbAdressage::getURLstatic($this->controleur,$this->action);
    }
            
    //************************************************************************************************
    //MODELE 
    //************************************************************************************************      
    private function getDetails() :array{
        //Retourne la liste du menu
        $requete = "SELECT * from "  . self::TABLE ;
        if (!TbAdressage::isDeveloppement()){
            $requete .= " WHERE production = 1 " ;
        }
        $requete .= " order by indentation ";
         return Model::mdRequeteLister($requete ,[]);
    }  
    
}

