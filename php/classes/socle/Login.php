<?php
declare(strict_types=1);
namespace shared\php\classes\socle;
/*******************************************************************************
 * Classe sur identifications
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
use shared\php\toolbox\Toolbox              as Tbx;
use shared\php\classes\socle\User           as User;

class Login{
    private static function getHtmlLogin(){
    // envoie le html à afficher en content du layout
        $myhtml  = '<div  class="d-flex justify-content-center align-items-center container ">';
        $myhtml  .= '<form method="post" action="' . TbAdressage::getURLstatic("accueil","loginUser") . '">';
        $myhtml  .='       <div class="container">';
        $myhtml  .='            <br><br><br><br><br>';
        $myhtml  .='           <fieldset class="border rounded-3 p-3">';
        $myhtml  .='               <legend class="float-none w-auto px-3" ><h6>Identifiants</h6></legend>';
        $myhtml  .='<div class="row">';
        $myhtml  .='<div class="col-4"><input type="text" name="pseudo" id="pseudo" placeholder="Identifiant" /></div>';
        $myhtml  .='</div>';
        $myhtml  .='<br><div class="row">';
        $myhtml  .='<div class="col-4"><input type="password" name="password" id="password" placeholder="Mot de passe" />';
        $myhtml  .='</div></div><br>';
        $myhtml  .='<div class="row">';
        $myhtml  .='<div class="col-2">';
        $myhtml  .='<input type="submit" class="btn btn-primary" value="S\'identifier" />';
        $myhtml  .='</div> </div> </fieldset></div></form></div>';    
        return $myhtml; 
    }
    public static function loginControl(string $namespace){
    //Si pas de user connecté affiche la page de connection, true sinon
    //exemple appel if (Login::loginControl(__NAMESPACE__)){require('tpSongDetail.php');}
        if(!User::userConnecte()){
            //user est connecté
            $content = Login::getHtmlLogin();
            //affiche page de layout
            require (TbAdressage::projetGetLayout($namespace));
        }
        else{
            return true;
        }
    }
    public static function loginControlDemand(string $namespace){
    //réception de demande d'authent via le du bouton d'authentification de la barre d'outils
    // déconnexion si clic sur se déconnecter
        if(User::userConnecte()){
            unset($_SESSION['LOGGED_USER']);
            //Suppression du menu stocké 
            unset($_SESSION[Menu::SESSION_MENU]);
            $content = Tbx::messageColorer(true,"Déconnexion effectuée"); 
            require(TbAdressage::projetGetLayout($namespace));
        }
        else{
            //affichage du formulaire demande 
            Login::loginControl($namespace);
        }
    }
    public static function loginControlReception(string $namespace){
    //traitement d'une demande de validation lors du click sur bouton de dfenêtre authentification
        $user = new User();
        //alimentation de la variable LOGGED_USER des $SESSION
        if($user->isValid(TbAdressage::getPost("S",'pseudo'), TbAdressage::getPost("S",'password'))){
            //si authentification correcte
            $pageCible = (string)filter_input(INPUT_SERVER,'HTTP_REFERER');
            if (str_contains($pageCible,"?fct=login")){
               //la demande a été faite à partir du bouton, retour menu avec message
               $content = "<div role=\"alert\">" . $_SESSION['LOGGED_USER']['prenom'] . 
                        ",  welcome sur le site! Tu es authentifié, ton avatar est en haut à gauche !!  </div> ";
               require(TbAdressage::projetGetLayout($namespace));
            }
            else{
                header('Location: ' . $pageCible);
            }
        }
        else{
            //erreur sur user password
            $content  = Tbx::messageColorer(false,messageKO:'Ooops il doit y avoir une erreur !!' );
            require(TbAdressage::projetGetLayout($namespace));
        }
    }
}