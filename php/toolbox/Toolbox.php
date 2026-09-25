<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * OUTILS DIVERS
 *******************************************************************************/


class Toolbox {
    
    public const int CSS = 1;
    public const int JS= 2;
    //==============================================================================            
    // OUTILS INCLUDES
    //==============================================================================
    public static function includeJS($nomSansJS, string $racine='shared'):string {
    //Renvoie les includes des JS publics
        return self::includeCSSJS($nomSansJS,self::JS, $racine);
    }
    public static function includeCSS($nomSansCSS, string $racine='shared'):string {
    //Renvoie les includes des JS publics
        return self::includeCSSJS($nomSansCSS,self::CSS,$racine);
    }
    public static function includeCSSJS($tableau, int $css_js, $racine):string {
    //Renvoie les includes des JS publics ou des CSS
    //<link rel="stylesheet" href="src/css/theBand.css" type="text/css" media="screen" /> ou 
    //<script type="text/javascript" src="/shared/js/ajax.js"></script> 
        $include = "";
        $noms =  is_array($tableau) ? $tableau : array($tableau);
        foreach ($noms as $nom){
            if ($css_js==self::JS){
                $include .= '<script type="text/javascript" src="/' . $racine .'/js/' . $nom .'.js"></script> ';
            }
            else{
                $include .= '<link rel="stylesheet" href="/' . $racine .'/css/' . $nom . '.css" type="text/css"/>';
            }
        }
        return $include;
    }
    //==============================================================================            
    // OUTILS TRANSFORMATIONS DONNEES
    //==============================================================================
    public static function valeurAffichableSiNull($valeur, $defaut ="",$specialchars=true, bool $blancSiZero= false){
    //si null retourne une valeur par défaut
        if(is_null($valeur)){
            $retour= $defaut;
        }
        else{
            if(is_string($valeur)and $specialchars ==true)
                {$retour = (htmlspecialchars($valeur));}
            else
                {$retour = $valeur;}
        }
        if ($blancSiZero){$retour = self::blancSiZero($valeur);}
        return $retour;
    }
    
    public static function blancSiZero( $valeur){
    //si null retourne une valeur par défaut
        return $valeur == 0 ? '' : $valeur;
    }
    
    public static function getConstanteTableau(object $classe, string $constante):array{
    //interroge les tableau des classes en attendant que chaque classe hérite
        return isset($classe::$constante) ? $classe::$constante :[];
    }
    
    public static function afficherTexteHTML ($value):string{
    //affiche un texte HTML comme texte brut sans les émléments de mise en forme
        if (is_null($value)){return "";}
        else{
        return html_entity_decode(strip_tags($value));}
    }
    
    public static function afficherTexteHTMLFormate ($value):string{
    //affiche le texte HTML avec sa mise en page
        if (is_null($value) or strlen($value)===0){
            return "";
        }
        else{
            return '<div>'.$value.'</div>';  
        }
    }
    
    public static function afficherTexteHTMLFormateReduit ($value):string{
    //affiche un texte html réduit via un popover
        if (is_null($value) or strlen($value)===0){
            return "";
        }
        else{
            return '<div>'.$value.'</div>';  
        }
    }
    
    //==============================================================================
    //                  TABLEAUX 
    //==============================================================================
    public static function afficherData(array $infos, string $info, $defaut = ""){
    //retourne une info du tableau associatif si elle existe
    // isset renvoie false si valeur = null
        if (isset($infos[$info])){
            $retour = $infos[$info];
            //forcer un format numerique en retour
            if (is_int($defaut)){$retour = (int)$retour;}
        }
        else {$retour = $defaut;}
        return $retour;
    }

    public static function extraireDatas(array $infos, array $infosRequises){
    //Extrait une liste d'information du tableau $infos
       $tableau = [];
       //ne prend l'info demandéedans listeinfo que si elle existe
       foreach($infosRequises as $info){
           if (isset($infos[$info])) {
               $tableau[$info] =  $infos[$info];}
       }
       return $tableau;
    }
    
    public static function oterDatas(array $infos, array $infosExclues){
    //Extrait une liste d'information du tableau nominatif $infos
       $tableau = [];
       //On prend toutes les infos sauf celles spécifiées
       foreach($infos as $key => $valeur){
           //si pas dans le tableau exclu on prend
           if (!in_array($key,$infosExclues)) {
               $tableau[$key] = $valeur;}
       }
       return $tableau;
    }

    
    public static function listerTableau(array $tableauAssociatif, string $nomTableau, bool $tdt = false):string {
    // $tbt indique que le tableau est un tableau de tableau
        $retour="";
        if ($tdt){
            foreach ($tableauAssociatif as $key ) {
                $retour .= listerTableau($key,"itération",false) ;
            }
        }
        else {
            $retour ="";
            foreach ($tableauAssociatif as $key => $value) {
                $retour .= $nomTableau ."(" . $key . ")=" . $value . "<br>" ;
            }
        }
        return $retour;
    }
    
    public static function nombreOccurencesDansTableau(string|int $recherche, array $tableau) :int{
    //compte le nombre d'occurence de $recherche dans $tableau
    //array count value produit un tableau avec les totaux par poste
        $compteur = 0;
        $values = array_count_values($tableau);
        if (isset($values[$recherche])){
            $compteur = $values[$recherche];
        }
        return $compteur;
    }
    
    public static function valeurLaPlusFrequente(array $tableau){
    //renvoie la valeur qui revient le plus grand nombre de fois dans un tableau nommé
            $comptage = \array_count_values($tableau);
            \arsort($comptage,\SORT_NUMERIC);
            return array_key_first($comptage);
    }
    
    public static function tableauIndexPourUneCle(int $key,array $tableau,int $premierPoste = 0) :int{
    //retourne l'index d'un poste de tableau associatif à partir de sa clé
    //premier poste si il faut une réponse qui démarre par exemple à 1
        return array_search($key,array_keys($tableau)) + $premierPoste;
    }
    
    public static function tableauDefaultAbscisses(array $ys) :array{
    //retourne un tableau avec la même dimension que celui en paramètre une suite d'entiers
        $xs=[];
        for($i=1;$i<= count($ys);$i++){
            $xs[]= $i;
        }
        return $xs;
    }
    //==============================================================================
    //                   MESSAGES AFFICHES 
    //==============================================================================
    public static function messageColorer(bool $resultat, string $messageOK ="Opération terminée avec succès",
                                                            string $messageKO = "Erreur détectée lors de l'opération") :string{
    /**
     * le html du post doit contenir si il faut l'information de retour à la liste
     *  <input type="hidden"  name="referer"  value="<?= TbAdressage::getReferer($bibliotheque->id)?>";
     */
   
        $affichage = '<div class="mt-5 ';

        if ($resultat) {
            $affichage .= ' alert alert-success"';
            $message = $messageOK;
        } else {
            $affichage .= ' alert alert-danger"';
            $message = $messageKO;
        }
        $affichage .= ' role="alert">' .$message ;
        
        //test présence de referer dans le post
        $referer = Toolbox_adressage::getPost("S","referer");
        if (strlen($referer) > 0 && $referer!="#"){
            $affichage .= ' <a href="' . $referer . '" class="btn btn-secondary btn-sm ms-3">Retour à la liste</a>';
        }
        $affichage .= "</div>";
        return $affichage;
    }
    
    
    public static function messageUpdateInsert(string $mode, bool $bonneFin):string{
        switch ($mode) {
            case "U":
                $modeTexte = "Mise à jour ";
                break;
            case "I":
                //insert
                 $modeTexte = "Création ";
                break;
        }
        if ($bonneFin){ 
            $content =  $modeTexte .  "terminée avec succès." ;
        }
        else{
            $content= "Problème durant l'opération";
        }
        return $content;
    }
   
}