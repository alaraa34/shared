<?php
declare(strict_types=1);
namespace shared\php\modale;
/*******************************************************************************
 * 
 *******************************************************************************/


/* ces fonctions gère les boutons d'action sur des listes
 Les boutons sont générés via mettre en forme action avec deux comportements possibles
 - MODALE 
 * Chaque modale est une div cachée générée par composerModal 
 * elle a pour action(modale) qui doit être le nom lancé au niveau du target modal
 * elle contient un bouton qui va recevoir d'ID concerné. ce bouton s'appelle "identifiant" + action(modale)
 * propositionMDLsupprimer
 * 
 PAMETRES DU TABLEAU ACTION
 *
 * texte : C'est le texte qui apparait sous forme de bulle sur l'icone de l'action Ex 'texte'=> 'Nouveau suivi'
 
 * logoClass : C'est l'icone choisie sur bootstap ou autre .exemple 'logoClass' =>'bi bi-telephone-outboun
  
 * href : indique le controleur à activer sous la forme controleur;fonction traiter par la fonction
        href est donné sur controleur;fonction;prefixe
        <button type="image" onclick="window.location.href = 'index.php?fcn=prospectEditer&ctl=prospect&id=12'" 12= identidient 
 
 * modale : est l'id de la div modale cachée qui est générée et est sou la forme controleur;fonction;prefixe ou controleurFonction
 exemple 'modale'=>'etablissement;supprimer'.
 
 * onclick : génère un tag onclick sur le bouton exemple paramétrage 'onclick'=> libelleActionOnClick("confirmer")
 - libelleActionOnClick est une fonction PHP de ce module qui génère une chaine sous la forme "jsModifierValeur('" . modalNomInputIdentifiant . "','££ID££')";
 - modalNomInputIdentifiant est un tag appelé "identifiant" + l'id de la modale qui permet de récupérer l'identifiant de la ligne
 - exemple si modale confirmer génère identifiantConfirmer qui est un tag hidden de la modale
 la fonction jsModifierValeur mets dans le tag identifiant de la modale l'identifiant de la ligne
 
 * message : message qui apparait sur le corps de la modale standard
 
 * template ; indique le fichier contenant le template modal, par défaut c'est src/templates/modaleMDL.php, sachant que le nom ne doit 
 par contenir src/templates/ et .php

 
 "Mettre en forme action" est appelée pour chaque ligne avec les actions définies dans le controleur et 
 * l'identifiant de l'occurence
 
 */
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

class Toolbox_modal {
    
   
    public static function mettreEnFormeActions(array $actions,int $id ) : string{
    /* Met en forme la liste des actions présntes sur une ligne de liste
     * paramètres de actions
     * - texte : message qui apparait sur l'icone
     * - id : id du bouton
     * - logo : image qui va être utilisée
     * - modale : nom de la modale à afficher au click
     * 
     */
        $myHtml = "<div class=\"btn-group\" role=\"group\" >";
        // Modale c'est un tag "button", display flex pour aligner les boutons
        foreach ($actions as $action){
            //traitement de chaque action  
            // resultat button type="button" id="boutonTelecharger" style="display:none" data-bs-toggle="modal" 
            // data-bs-target="#telecharger" onclick="jsModifierValeur('identifianttelecharger','££ID££')" class="btn btn-sm mb-2"
            $myHtml .= '<button type="button"';
            $myHtml .= self::mettreEnFormeActionsStyleEtId($action);
            $myHtml .= self::mettreEnFormeActionsModale($action);
            $myHtml .= self::mettreEnFormeActionsHref($action,$id);
            $myHtml .= self::mettreEnFormeActionsOnClick($action,$id);
            $myHtml .= ' class="btn btn-sm mb-2">';
            $myHtml.=  self::mettreEnFormeActionsImageBouton($action);
            $myHtml.=  "</button>";
        }
        return $myHtml . "</div>" ;
    }
    
    
    private static function mettreEnFormeActionsStyleEtId(array $action) : string{
    // met en forme la partie Id Et style
        $myHtml="";
        if (isset($action['id'])){
            $myHtml .= " id=\"" . $action['id'] ."\""; 
        }
        if (isset($action['style'])){
            //exemple style='\"'display:none\"
            $myHtml .= 'style="' . $action['style'] .'"'; 
        }
        return $myHtml;
    }
    
   private static function mettreEnFormeActionsImageBouton(array $action) : string{
        //image du bouton logoClass pour les bouton bootstrap ou font awasome
        //taille des boutons fa-xs, fa-sm, fa-lg
        if (isset($action['logoClass'])){
            $myhtml =  "<span class=\"" . $action['logoClass'] . " fa-lg \"" ;
        }
        //pour les logos images 
        if (isset($action['logo'])){
            $myhtml = "<img src=\"images/" . $action['logo'] . "\"" .
                    " class=\"img-fluid rounded-circle mx-auto d-block img-thumbnail \"" ;
        }
        $myhtml .= " title=\"" . $action['texte']. "\"" .
                   " alt=\"" .  $action['texte'] . "\"" . " />";
        return $myhtml;
    }
    
    private static function mettreEnFormeActionsModale(array $action) : string{
    //mise en forme du bouton qui ouvre la fenêtre modale
    //databs target est # + id de la modale concernée
        $myHtml="";
        if (isset($action['modale'])){
            // cible de la modale
            // exemple <button type="button"  data-bs-toggle="modal" data-bs-target="#staticBackdrop">
            $myHtml = self::mettreEnFormeAppelModal($action['modale']) ;
        }
        return $myHtml;
    }
    public static function mettreEnFormeAppelModal(string $actionModale) :string{
    //mise en forme la partie dédiée à la modale bouton qui va appeler la fenêtre    
        return  ' data-bs-toggle="modal" data-bs-target="#' . self::modalGetNomDiv($actionModale) . '"';
    }

    public static function mettreEnFormeActionsOnClick(array $action,int $id=0) : string{
    //pour les div modales permet de lancer la fonction qui met l'ID dans un button de la modale
    // pour permettre de la transmettre au treitement

        if (isset($action['onclick'])){
            $myHtml = " onclick=\"" . $action['onclick'] . "\"";
        }
        elseif (isset($action['modale'])){
            //Si l'action contient $$id$$ c'est remplacé par ID de l'object
            //exemple onclick="jsModifierValeur('identifiantSupprimer','45')" 
            // dans le code action on remplace la valeur ££ID££ par l'identifiant
            $actionClick = self::libelleActionOnClick($action['modale']);
            $myHtml = " onclick=\"" . str_replace("££ID££", (string)$id,$actionClick) . "\"";
        }
        else {$myHtml = " ";
        }

        return $myHtml;
    }
    
   private static function mettreEnFormeActionsHref(array $action, int $id) : string{
    //C'est un lien vers une autre page, code onclick + ref, le paramètre href contient fonction et controleur 
    //sous la forme fonction;controleur. Le controleur doit contenir le chemin complet ex songEditer;song
    //Rappel règle , le fichier controleur est le nom du répertoire préfixé de ct, exemple ctSong
        $myHtml = "";
        if (isset($action['href'])){
            //exemple   <input type="button" onclick="window.location.href = 'https://www.w3docs.com';" value="w3docs" />
            //au clic envoi le code action et l'id en paramètre récupérés par la page index
            //href sous la forma controleur;fonction;prefixe ex songEditer;song
            $tableau = TbAdressage::getControleurFonctionPrefixeFromCFPadress($action['href']);
            //appel avec fonction/ controleur
            $url = TbAdressage::getURLstatic((string)$tableau['controleur'],(string)$tableau['prefixe'].ucfirst($tableau['fonction']),$id);
            $myHtml = " onclick=\"window.location.href = '" . $url. "'\"";
        }
        return $myHtml;
    }
    
       
    public static function modalGetCtFnModale(string $modale) : array  {
    //le paramètre modal est sous la orme controleur;fonction;prefixe
    //la fenetre a pour nom  prefixeMDLfonction 
        $tableauCFP = TbAdressage::getControleurFonctionPrefixeFromCFPadress($modale);
        return [$tableauCFP['controleur'], $tableauCFP['prefixe']."MDL".$tableauCFP['fonction']];
    }
    
    public static function modalGetNomDiv(string $modale):string{
    //renvoie le nom de la div modale exemple etablissementMDLsupprimer
    //sert pour l'Id de la div modale et la fonction de traitement de la modale
        return self::modalGetCtFnModale($modale)[1];
    }
    
    public static function modalNomInputIdentifiant(string $modale) : string{
    //retourne le nom du bouton de la div qui contient l'identifiant de la modal 
    //il est dormé de la constante "identifiant" concaténé avec la fonction de la modale avec première lettre majuscule
        return "identifiant" . ucfirst(self::modalGetNomDiv($modale));
    }

    public static function libelleActionOnClick(string $modale):string {
    //formatte l'appel à la fonction jsModifierValeur qui permet d'insérer l'identifiant de la ligne concernée dans la div modale
    //ou ajoute un clic si besoin specifique
            return "jsModifierValeur('" . self::modalNomInputIdentifiant($modale) . "','££ID££')";
    }

    public static function modalGetIdFromModal() :int{
    //recupere l'id d'une modale
        //nom de la modale sous la forme song;supprimer
        $identifiant = self::modalNomInputIdentifiant(TbAdressage::getPost("S","nomModale"));
        return TbAdressage::getPost("I",$identifiant);
    }

    public static function afficherListeDivModales(array $actions) :string {
    //affiche les div modales de traitement d'une liste
        ob_start();
        echo ("<br><!--DEBUT Affichage des DIV pop up modales  -->");
        foreach ($actions as $action ){
            //Si action avec modale
            if (isset($action['modale']) && strlen($action['modale']) > 0){
                echo("<br>");
                //si pas de tag fichier dans les paramètres c'est que modale standard
                if (isset($action['template'])){
                    require(ROOT_PATH . $action['template']);
                }
                else{
                    require(ROOT_PATH .'shared/php/modale/tpModaleMDL.php');
                }
            }
        }
        echo ("<br><!--FIN Affichage des DIV pop up modales  -->");
        return ob_get_clean();
    }
}