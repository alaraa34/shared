<?php
declare(strict_types=1);
namespace shared\php\toolbox;

/*******************************************************************************
//paramètres $infos description des données
//$infosColonnes description des colonnes en tableau nommé
//  zone = nom de la zone dans les infos
//  variable = si le champ correspond à une variable qui n'est pas dans les données, nom de la variable
//  align = Alignement de la colonne
//  fonction nom d'une fonction à appliquer. Cette fonction s'applique à la variable de la ligne, sinon il faut définir le tag
//  parametres = paramètres de la fonction si ce n'est pas juste la valeur. Si la valeur en fait partie il faut indiquer $valeur
//                  exemple 'parametres'=>['$valeur',0,false,true] ou 'parametres'=>array('$info[\'lienMP3\']')]
//CleId Clé de la ligne
//Actions = actions à déclencher
 *******************************************************************************/

use shared\php\bricks\Brick_table                      as BkTable;
use shared\php\modale\Toolbox_modal                    as TbModal;
use shared\php\toolbox\Toolbox_date                    as TbDate;
use shared\php\classes\personalisation\Nomenclature    as Nomenclature;

class Toolbox_liste {
    /***************************************************************************
    * FONCTIONS QUI GENERE UNE LISTE AUTOMATIQUE
    ****************************************************************************/
    
    //constantes pour piloter l'affichage
    public const string FONCTION_VASN = '\shared\php\toolbox\Toolbox::valeurAffichableSiNull';
    public const string FONCTION_FALC ='\shared\php\classes\lien\Lienhtml::afficherLiensCellule';
    public const string FONCTION_ATH = '\shared\php\toolbox\Toolbox::afficherTexteHTML';
    public const string FONCTION_ATHF = '\shared\php\toolbox\Toolbox::afficherTexteHTMLFormate';
    private const string  BOOL_ON = '<i class="bi bi-toggle-on"></i>';
    private const string  BOOL_OFF = '<i class="bi bi-toggle-off"></i>';
    
    private static function listerColonnes(array $infos, array $infosColonnes1 ,array $actions){
    //mise en forme du tableau InfoColonnes
        if(count($actions)>0){
            //rajout de zone action si actions
            $infosColonnes[] = ['zone'=>'actions'];
        }
        
        if(count($infosColonnes1)==0){
            //si pas d'info colonne , constitution par le tableau des infos
            foreach($infos[0] as $key=>$info){
                $align = (\is_numeric($info) || TbDate::dateValiderAMJ((string)$info))  ? "MC" : "M";
                //remplacement des _ par blanc pour les entêtes issus de requetes sql
                $infosColonnes[] = ['zone'=>$key,'align'=>$align];
            }
        }
        else{
            //sinon récupéré en l'état en complétant par colonne
            foreach($infosColonnes1 as $info){
                $infosColonnes[]= $info;
            }
        }
             
        return $infosColonnes;

    }

    public static function constituerListe(array $infos, array $infosColonnes1=[],array $actions=[],string $cleId="id",bool $afficherModales=true){
    //Paramètres    $infos =données
    //              $infosColonnes Titre des colonnes
    //              $CleId nom du la zone qui contient l'ID dans le tableau $infos
    //              $actions liste des actions à prévoir
        if (count($infos)==0){
            return Toolbox::messageColorer(false,"","Aucune information n'est disponible");
        }
        else{
            $infosColonnes= self::listerColonnes($infos,$infosColonnes1,$actions);
            
            ob_start(); 
            $maTable = new BkTable(tableClass:"table table-hover table-bordered table-sm");

            //En tête des colonnes
            $maTable->addHeaders(self::generiqueTableauEnteteListe($infosColonnes));

            //liste des données
            foreach ($infos as $numero=>$info) {
                //$info = une ligne de la liste
                //Alimentation du numéro de lignes pour que les fonctons qui en ont besoin puissent l'utiliser
                $info['numero'] = (int) ($numero+1);
                foreach ($infosColonnes as $index=>$element){
                    if ($index===0){$nouveau = true;} //création d'une nouvelle ligne
                    if (str_contains($element['zone'], BkTable::COLONNE_NUMERO)){
                        //pas de traitement, c'est la numérotation des colonnes
                    }
                    elseif ($element['zone']=="actions"){
                        //traitement de la colonne action
                        $maTable->addCell(TbModal::mettreEnFormeActions($actions,$info[$cleId]), nouveau: $nouveau);
                        $nouveau = false;}
                    else{
                        //le reste
                        $align = isset($element['align']) ? $element['align'] : "M";
                        $maTable->addCell(self::generiqueTraiterElement($element,$info),alignement:$align,nouveau:$nouveau);
                        $nouveau = false;
                    }
                }
            }
            echo $maTable->render();
            unset ($maTable);
             //Rajout des div modales 
            if($afficherModales){echo TbModal::afficherListeDivModales($actions);}

            return ob_get_clean();
        }
    }

    private static function generiqueTraiterElement($element, $info){
    //cacule la valeur de la ligne string ou numerique
    //
        //calcul valeur de base
        $valeur = self::generiqueTraiterValeur($element, $info);

        //si fonction à appliquer                
        $valeur1 = self::generiqueTraiterFonction($element, $info,$valeur);
        //affichage
        
        return $valeur1;
    }

    private static function generiqueTraiterValeur(array $element,array $info){
    //la valeur est une concaténation
        if (isset($element['valeur'])){       
            //si il y a un tag valeur c'est lui qui pilote son estimation pour une concaténation de variables
            $valeur = "";
            foreach ($element['valeur'] as $texte){
                //si zone existe on prend sa valeur
                if (isset($info[$texte])){
                    $valeur .= $info[$texte];
                }
                else{
                    //sinon c'est un littéral
                    $valeur .= $texte;
                }
            }
        }
        else {
            //sinon c'est la variable $zone
            $valeur = $info[$element['zone']];
        }
        return $valeur;
    }
    private static function generiqueTraiterFonction(array $element,array $info, $valeur){
    //applique une fonction à la valeur, avec ou sans paramètres en plus
    //en paramètres $valeur est le mot clé pour désigner la valeur de la colonne
        if(isset($element['fonction'])){
            //fonctions spéciales
            switch ($element['fonction']){
                case "concatener":
                    //concaténations des zones 
                    $valeur = "";
                    foreach($element['parametres'] as $parametre){
                        $valeur .= trim($info[$parametre]) & " " ;
                    }
                    break;
                default :
                    //générique
                    if(isset($element['parametres'])){
                        //paramètres en plus à appliquer
                        $parametres = self::generiqueTraiterFonction1($element,$info,$valeur);
                        return self::generiqueTraiterFonction2 ($element['fonction'],$parametres);
                    }
                    else{
                    //il faut appliquer une fonction avec juste la valeur en paramètre
                    return  $element['fonction'] ($valeur); 
                    }
            }
        }
        else{
            return $valeur;
        }
    }
    private static function generiqueTraiterFonction1(array $element,array $info, $valeurColonne){
    //retourne un tableau qui evalue les paramètres
    //exemple d'expression '$info[\'liens\'][0][\'lien\']
        $retour=[];
        //paramètres en plus à appliquer
        foreach ($element['parametres'] as $parametre){
            switch (gettype($parametre)){
                case "boolean" :
                case "integer" :
                case "double" :
                    $retour[] = $parametre;
                    break;
                case "string":
                    if ($parametre=='$valeur'){
                        $retour[] = $valeurColonne;
                    }
                    elseif (str_contains($parametre,'$info')){
                        //c'est un paramètre du tableau d'infos
                        $retour[] = self::generiqueTraiterFonction1_tableauParametres($parametre,$info);
                    }
                    break;
            }
        }
        return $retour;
    }
    private static function generiqueTraiterFonction1_tableauParametres(string $parametre,array $info){
    //traite un paramètre qui exprime un tableau nommé
    //exemple d'expression '$info['liens'][0]['lien']!! attention mettre \ devant la qi=uote
    //isole les variables su tableau
        $valeurRetour = $info;
        //paramètres en plus à appliquer
        $pos1 = strpos($parametre,"[");
        while (is_numeric($pos1)){
            $pos2 = strpos($parametre,"]",$pos1+1);
            //si on a trouvé un intervalle
            $valeur = substr($parametre,$pos1+1,$pos2-$pos1-1);
            //si c'est numérique c'est un indice
            if (is_numeric($valeur)){
                //traitement d'un poste numérique, rien à préparer
            }
            else{
                //sinon c'est un littéral pour un poste nommé, il faut prendre la valeur qui est entre les quotes
                 $valeur = explode("'",$valeur)[1];
            }
            //traitement de la valeur à estimer
            $valeurRetour= self::generiqueTraiterFonction1_tableauParametres1($valeur,$valeurRetour);
            if ($valeurRetour == "££"){
                $valeurRetour = "";
                break;
            }
            else{
                $pos1 = strpos($parametre,"[",$pos2+1);
            }
        }
        return $valeurRetour;
    }
    private static function generiqueTraiterFonction1_tableauParametres1(string $valeur, array $tableau){
    //teste si la valeur estimée existe
    //sinon retourne ££
                if (isset($tableau[$valeur])){
                    $valeurRetour = $tableau[$valeur];
                }
                else{
                    $valeurRetour ="££";
                }
        return $valeurRetour;
    }
    
    private static function generiqueTraiterFonction2(string $fonction,array $parametres){
    //appel la fonction avec le bon nombrede paramètres
        switch (count($parametres)){
            case 1 :
                $valeur1 = $fonction($parametres[0]);
                break;
            case 2:
                $valeur1 = $fonction($parametres[0],$parametres[1]);
                break;
            case 3:
                $valeur1 = $fonction($parametres[0],$parametres[1],$parametres[2]);
                break;
            case 4:
                $valeur1 = $fonction($parametres[0],$parametres[1],$parametres[2],$parametres[3]);
                break;
            default :
                die ("Toolbox_liste/generiqueTraiterFonction2, plus de 4 paramètres");
        }
        return $valeur1;
    }
 
    private static function afficherPastilleDansListe($nb){
        $valeur = "<span class=\"badge rounded-pill ";
        if ($nb==0){
            $valeur .= " bg-info \">";
        }
        else{
            $valeur .= " bg-success border border-light \">En Cours";
        } 
        return $valeur  . " </span>";
    }
    
    private static function generiqueTableauEnteteListe(array $infos):array{
    //retourne un tableau pour afficher les entêtes de colonne d'une liste de zones 
    //les _ sont remplacés par des blancs, la zone identifiant est ignorée
        $tableau = [];
        foreach($infos as $info){
            //si un titre particulier exprimé, sinon c'est le nom directement
            if (isset($info['titre'])){
                $texte = $info['titre'];
            }
            else {$texte = $info['zone'];}
            $tableau[] =ucfirst(str_replace("_"," ",$texte));
        }
        return $tableau;
    }   
    
    /***************************************************************************
    * FONCTIONS QUI GENERE UNE LISTE AUTOMATIQUE
    ****************************************************************************/
    public static function formaterEntete(array $tableau, bool $avecTR = true): string {
        $table = "";			
        foreach ($tableau as $poste)  
        {	
            //Cas particulier pour le ID
            if ($poste=="N")
                {$table .= "<th class=\"enteteColonnes\">N&deg;</th>";}
            else
                {$table .= "<th class=\"enteteColonnes\">" . $poste . "</th>";}	
        }
        if ($avecTR){$table = "<tr>" .$table . "</tr>"; }
        return $table;
    }
    
    public static function valeursChoixListeNomenclature(string $groupe,string $zoneRetour="ValeurA",$selected = 0){
    //constitue une liste d'après une nomenclature
        return self::valeursChoixListe(Nomenclature::mdNomenclatureGetListe($groupe, zoneRetour: $zoneRetour), selected: $selected);
    }
    
    public static function valeursChoixListe(array $valeurs, bool $choisir = false, string $identifiant="identifiant",string $zone="zone", $selected =0) :string {
        //met en forme un choix valeur à partir d'un tableau de tableaux associatifs ['identifient', 'zone']
        //ajoute les lignes <option value="4">Site</option> 
        //f$ fOnclock fonction à déclancher en cas de choix sur une liste et non une combo
        //tête de liste non prise en compte <select id="choixSong" name="choixSong" class="form-select" size="10" multiple >
        //$select contient l'identifiant à sélectionner 
        $element = "";
        //var_dump ($valeurs);
        if ($choisir){$element = '<option disabled selected value="">Choisir...</option>';}
        foreach ($valeurs as $valeur) {
            //$valeur est un tableau associatif à deux postes  ['identifiant', $zone]
            $element .= "<option value=\"" . $valeur[$identifiant] . "\"";
            //test si identifiant selectionné, soit multiple, soit simple
            if (is_array($selected)){
                foreach($selected as $item){
                    if ($valeur[$identifiant]==$item){
                        $element .= " selected ";
                        break;
                    }
                }
            }
            else{
                if ($valeur[$identifiant]==$selected){
                    $element .= " selected ";
                }
            }

            $element .= ">" . $valeur[$zone] . "</option>";
        }
        return $element ;
    }
    
    public static function valeursChoixListeSansID(array $valeurs, bool $choisir = false, string $selected = "" ) :string {
        //met en forme un choix valeur à partir d'un tableau de valeurs qui servent d'identifiant et d'affichage
        //ajoute les lignes <option value="4">Site</option> 
        //f$fOnChange fonction à déclancher en cas de choix
        //tête de liste non prise en compte <select id="choixSong" name="choixSong" class="form-select" size="10" multiple >
        //$select contient l'identifiant à sélectionner 
        $element = "";
        //var_dump ($valeurs);
        if ($choisir){$element = "<option value=\"\">Choisir...</option>";}
        foreach ($valeurs as $valeur) {
            //$valeur est un tableau associatif à deux postes  ['identifiant', $zone]
            $element .= "<option value=\"" . $valeur . "\"";
            //test si identifiant selectionné, soit multiple, soit simple
            if (is_array($selected)){
                foreach($selected as $item){
                    if ($valeur==$item){
                        $element .= " selected ";
                        break;
                    }
                }
            }
            else{
                if ($valeur==$selected){
                    $element .= " selected ";
                }
            }
            $element .= ">" . $valeur . "</option>";
        }
        return $element ;
    }
    
}