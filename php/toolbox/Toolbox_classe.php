<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * 
 *******************************************************************************/

use shared\php\database\Model    as Model;

class Toolbox_classe {
    
/********************************************************************************
* Fonctions propriétés
*******************************************************************************/
  
    public static function getInstanceDansCollection(array $collections, int $idRecherche, string $propriete = "id"): int {
    // Recherche quel est la position qui correspond à l'ID
        $indice = 0;
        if (count($collections)>0){
            foreach($collections as $key=>$value){
                if ($value->$propriete == $idRecherche) {
                    $indice = $key;
                    break;
                }
            }
            if ($indice===0){
                die("getInstanceDansCollection : Erreur de rechercher sur instance ". get_class($collections[0]) . " id= " . $idRecherche);
            }
        }
        return $indice;
    }
    
    private static function classeMiseEnFormeInfo($variableClasse, $valeur){
    //Traite l'alimentation des booleans qui sont des entiers    
        if (is_bool($variableClasse) and !is_bool($valeur)){
            return boolval($valeur);
        }
        else{
            return $valeur;
        }
    }
    
          
    private static function classeChargerSiExiste(string $className) {
    //recherche si une classe existe et la charge
    //si pas possible d'instancier la classe renvoie false
        $retour = class_exists($className, false);
        if (!$retour){
            // Méthode PSR-4 : convertir namespace en chemin de fichier
            $classPath = str_replace('\\', '/', $className) . '.php';
            $fullPath = rtrim((string)filter_input(INPUT_SERVER,'DOCUMENT_ROOT'), '/') . '/' . ltrim($classPath, '/');
            $retour = file_exists($fullPath) ? class_exists($className, true) : false;
        }
        return $retour;
    }
    
    public static function getOwnProperties(string $obj): array
    {//retourne uniquement les propriétés de la classe fille
        $childClass  = new \ReflectionClass($obj);
        $parentClass = $childClass->getParentClass();

        $childProps  = array_map(fn($p) => $p->getName(), $childClass->getProperties());

        if ($parentClass === false) {
            return $childProps; // pas de classe mère
        }

        $parentProps = array_map(fn($p) => $p->getName(), $parentClass->getProperties());

        return array_values(array_diff($childProps, $parentProps));
    }
/********************************************************************************
* Fonctions de CHARGEMENT 
*******************************************************************************/
    public static function classeLoadFromPost(object $classe) :void{
    //Charge une classe par le post 
    //chaque propriété de la classe est passée en revue :
    //Si c'est une propriété simple, il faut que le nom de l'info dans post correcponde au nom de la variable
    //Si c'est une classe seul l'ID est chargé et son nom dans le tableau de données doit être idNomdelaclasse, ex IdCommentaire

        //lecture des attributs de la classe (get_class revoie les atrtibuts par défaut)
        $tableau = get_class_vars(get_class($classe));
        //Traitement
        foreach($tableau as $nom=>$value){
            if ($nom=="id"){
                //Alimentation id dans les formulaires est sous la forme idClasse, exemple idEtablissement
                $classe->id = Toolbox_adressage::getPost("I", "id".ucfirst(self::classeGetInfos(get_class($classe))[1]));
            }
            //Chargement de la donnée si pas une classe, si pas une collection (tableau) 
            elseif (!is_null($value) && !is_array($value) && isset($classe->$nom)){
                //Alimentation typée via getpost
                $classe->$nom = Toolbox_adressage::getPost(gettype($value), $nom);
            }
            elseif(is_null($value) && isset($classe->$nom->id)){
                //'Si c'est une classe, alimentation l'id seulement
                $classe->$nom->id = Toolbox_adressage::getPost("I", "id".ucfirst($nom));
            }
        }

    }
    
    public static function classeLoadFromArray(object $classe, array $infos, string $alias="", array $sauf=[]) :void{
    //Charge une classe par un tableau nommé ['nom'=>'valeur']
    //chaque propriété de la classe est passée en revue :
    //Si c'est une propriété simple, il faut que le nom de l'info dans le tableau correcponde au nom de la variable
    //Si c'est une classe seul l'ID est chargé et son nom dans le tableau de données doit être idNomdelaclasse, ex IdCommentaire
    //Alias si il faut rajouter une valeur aux zones à charger, exemple "lien" pour trouver idLien
    //$sauf si il y a des zones à ne pas charger

        //lecture des attributs de la classe (get_class revoie les atrtibuts par défaut)
        $tableau = get_class_vars(get_class($classe));
        //Traitement
        foreach($tableau as $nom=>$value){
            if(in_array($nom, $sauf)){continue;}
            //Chargement de la donnée si pas une classe, si pas une collection (tableau) et présent dans le tableau de données
            //charge aussi les zones wrk le cas échéant
            if (!is_null($value) && isset($classe->$nom) && isset($infos[$nom . $alias])){
                //transformation pour formats spéciaux comme boolean
                $classe->$nom = self::classeMiseEnFormeInfo($classe->$nom,$infos[$nom . $alias]);
            }
            elseif(is_null($value) && isset($classe->$nom->id) && isset($infos["id".ucfirst($nom . $alias)])){
                //'Si c'est une classe, alimentation l'id seulement
                $classe->$nom->id =$infos["id".ucfirst($nom . $alias)];
            }
        }
    }

    public static function classeChargerCollectionParMouvements(object $classeMere, array $mouvements, string $classeTraitee):array{
    //Mets à jour une collection sur la base des mouvements trouvés pour cette collection
    //classe traitée doit contenir l'adresse complète exemple shared\php\classes\lien\Lien
    //object $classeMere = classe propriétaire de la collection
        $collection=[];
        foreach($mouvements as $mouvement){
            $obj = new $classeTraitee();
            self::classeLoadFromArray($obj, $mouvement, self::classeGetInfos($classeTraitee)[1]); //appel avec nom court de la classe traitée
            //recherche du code action exemple actionLien
            switch ($mouvement['action'. self::classeGetAlias($obj)]) {
                case 'C':
                case 'M':
                    if(isset($obj->wrkPoste)){$obj->wrkPoste = $mouvement['poste'];} //stockage n° de poste si besoin ultérieur
                    $collection[]= $obj;
                    break;
                case 'S':
                    //test si présence de collection sur association
                    $ass = self::classeGetInstanceClasseAssociative($classeMere,$classeTraitee);
                    if (strlen($ass) > 0){
                        $classeAss = new $ass($classeMere,$obj);
                        $classeAss->delete();
                        unset($classeAss);
                    }
                    //suppression du contact d'après son id
                    //l'instance n'est pas rajoutée à la collection
                    $obj->delete();
                    break;
            }
            unset($obj);
        }
        return $collection;    
    }
    
    private static function classeGetInstanceClasseAssociative(object $classe, string $classeTraitee) :string{
    //classe traitée doit contenir l'adresse complète exemple shared\php\classes\lien\Lien ou nom seul
    //object $classeMere = instance classe propriétaire de la collection 
        $nomCourtClasseCollection = self::classeGetInfos($classeTraitee)[1];
        $nomClasseAssociative = get_class($classe). $nomCourtClasseCollection . "_ass"; //nom avec namespace
        
        $retour = self::classeChargerSiExiste($nomClasseAssociative);
        if ($retour === false){
        //recherche avec la classe mere
            $classeMere = get_parent_class($classe);
            if (!$classeMere===false and !str_contains($classeMere,"Mere")){
                //si il y a une classe mere et que ce n'est pas mere. Contains car il y a namespace devant
                $nomClasseAssociative = $classeMere . $nomCourtClasseCollection . "_ass"; //nom avec namespace
                $retour = self::classeChargerSiExiste($nomClasseAssociative);
            }
        }
        //La classe associative existe, c'est elle qui gère
        return $retour ? $nomClasseAssociative :"";
    }
    
    public static function classeLoadFromId(object $classe) :void{
    //Charge une classe à partir de son d
    //chaque propriété de la classe est passée en revue :
    //Si c'est une propriété simple, il faut que le nom de l'info dans le tableau correcponde au nom de la variable
    //Si c'est une classe (le tableau restreint contient les noms des variables pour classes et collactions
    //      si restreint la contient seul l'ID est chargé et son nom dans le tableau de données doit être idNomdelaclasse, ex IdCommentaire
    //      si restreint ne la contient pas la même méthode est appliquée à la classe
        //recherche des infos /\ Nom des zones sensible à la casse
        $infos = $classe->mdInfosDetail();
        //lecture des attributs visibles de la classe et leur valeur par défaut (get_class revoie le nom)
        $tableau = get_class_vars(get_class($classe));
        $restreint = Toolbox::getConstanteTableau($classe,"RESTREINT");
        //Traitement des attributs de la classe
        foreach($tableau as $nom=>$value){
            //on ignore les variables qui commencent par wrk
            if (str_starts_with($nom, "wrk")){continue;}
            
            //Chargement de la donnée si pas une classe (isnull), si pas une collection (tableau) et présent dans le tableau de données
            if (!is_null($value) && !is_array($value) && isset($infos[$nom])){
               $classe->$nom= self::classeMiseEnFormeInfo($classe->$nom,$infos[$nom]);
            }
            
            elseif(is_null($value) && isset($classe->$nom->id) && isset($infos["id".ucfirst($nom)])){
                //'Si c'est une classe, alimentation l'id seulement si il est dans les infos et exprimé
                if ($infos["id".ucfirst($nom)] > 0){
                    $classe->$nom->id =$infos["id".ucfirst($nom)];
                    // si pas restreint chargement de la classe avec le constructeur
                    if (!in_array($nom, $restreint)){self::classeLoadFromId($classe->$nom);}
                }
            }
            
            elseif(is_array($value)){
                //si c'est un tableau de données donc une collection d'instances de classes qu'il faut charger
                //contrainte : la collection doit avoir comme nom de variable la classe instanciee avec un s
                if (!in_array($nom, $restreint)){
                    self::classeLoadFromId_Collections($classe,$nom);
                }
            }
        }
    }
    private static function classeLoadFromId_Collections(object $classe, string $nom) :void{
    //Traite le chargement de la collection interne ou associative
    //Si collection interne, elle est forcément dans le même namespace
    //Si collection externe çà passe apr une classe _ass qui connait le namespace de la collection
        
        //nom de la classe instanciée sans le namespace et sans le s de la collection
        $nomCourtClasseCollection = ucfirst(substr($nom, 0, strlen($nom)-1));
                           
        //test si il existe une classe associative
        $nomClasseAssociative = self::classeGetInstanceClasseAssociative($classe,$nomCourtClasseCollection);
        if (strlen($nomClasseAssociative) > 0){
            //récupération du nom complet de la classe instanciée 
            $propriete = lcfirst($nomCourtClasseCollection); //exemple lien
            $classeAss = new $nomClasseAssociative();
            $nomCompletClasseCollection = get_class($classeAss->$propriete); //Classe de la collection avec namespace exemple \\shared\php\classes\lien
            unset($classeAss);
            //instanciation des occurences de la collection
            $listeId = $classe->mdInfosIdCollection($nomClasseAssociative, "id" .$nomCourtClasseCollection) ;
            foreach($listeId as $id){
                $classe->$nom[] = new $nomCompletClasseCollection ((int)$id);
            } 
        } 
        else{
            //nom complet de la classe instanciée dans la collection
            $namespace = self::classeGetInfos(get_class($classe))[0]; //namespace de la classe
            $nomClasseCollection = $namespace . "\\" . $nomCourtClasseCollection;  
            //Liste Id
            $listeId = $classe->mdInfosIdCollection($nomClasseCollection);
            //instanciation des occurences 
            foreach($listeId as $id){
                $classe->$nom[] = new $nomClasseCollection((int)$id);
            }
        }
    }
/********************************************************************************
* Fonctions Extraction de données pour CRUD
*******************************************************************************/
    public static function classeValeurProprietesAvecCleExterne(object $classe,int $valeurCleExterne){
    // ajoute la cle externe au tableau des propriétés
        $tableau = self::classeValeurProprietes($classe);
        $tableau[$classe::CLE_EXTERNE] = $valeurCleExterne;
        return $tableau;
    }

   public static function classeFilleValeurProprietes(object $classe,array $avec = ['id']) :array{
    //retourne les zones de la fille qui ne sont pas de la mère 
    //un tableau nominatif avec les propriétés  et pour les classes l'Id
    //$avec liste des infos de la mère à retourner en plus de celles de la fille
    //
        $zones = [];
        //lecture des attributs de la classe (get_class revoie les atrtibuts par défaut
        $tableauMere = get_class_vars(get_parent_class($classe));    //attribus classe mère
        $tableauFille = get_class_vars(get_class($classe));         //attributs classe fille
        //Epuration de tableau fille
        foreach($tableauFille as $nom=>$value){
            //on garde la proopriété si elle est dans le tableau avec ou si (ds fille et pas dans mère) 
            // - pas dans la tableau "avec" , dans le tableau fille et mère 
            //et pas un tableau elle même car collections
            if (str_starts_with($nom, "wrk")){continue;}
            $dansFilleEtPasDansMere = (!array_key_exists($nom,$tableauMere) AND array_key_exists($nom,$tableauFille));
            //si la zone faity partie des zones fille et pas mère ou que demande de retout obligatoire
            if ((in_array($nom,$avec) OR $dansFilleEtPasDansMere) AND !is_array($value)){
                if(is_null($value) && class_exists($classe->$nom::class,false)){ 
                    //Si c'est une classe, retour de 'idClasse'=id . Exemple 'idCommentaire'=>3
                    $zones['id'. ucfirst($nom)]= $classe->$nom->id;
                }
                else{
                    if ($dansFilleEtPasDansMere){
                        //si zone pas ds mère et dans fille
                        $zones[$nom]= $classe->$nom;
                    }
                    else{
                        //zone dans mère reprise comme çà car héritage
                        $zones[$nom]= $classe->$nom;
                    }
                }  
            }
        }
        return $zones;
    }
    
    public static function classeAssociativeValeurProprietes(object $classe) :array{
    //retourne un tableau nominatif avec les propriétés  et pour les classes l'Id
    //Uniquement pour les propriétés classes car ass = association de plusieurs classes
    //
        $zones = [];
        //lecture des attributs de la classe (get_class revoie les atrtibuts par défaut
         $tableau = get_class_vars(get_class($classe));
        //Epuration de tableau fille
        foreach($tableau as $nom=>$value){
            if (str_starts_with($nom, "wrk")){continue;}
            if(is_null($value)){ 
               //Si c'est une classe, retour de 'idClasse'=id . Exemple 'idCommentaire'=>3
               $zones['id'. ucfirst($nom)]= $classe->$nom->id;
           }
        }
        return $zones;
    }
    
    public static function classeValeurProprietes(object $classe,bool $classeMere = false) :array{
    //retourne un tableau nominatif avec les propriétés  et pour les classes l'Id
    //$sauf liste des infos à ne pas retourner
    //Classe mère = true pour traiter uniquement la classe mère de l'instance
    //les attributs "wrk" sont privés dans les classes et ne ressortent pas dans tableau
        $zones = [];
        //lecture des attributs de la classe (get_class revoie les atributs par défaut
        $tableau = $classeMere ? get_class_vars(get_parent_class($classe)) : get_class_vars(get_class($classe));
        //Boucle sur tableau
        foreach($tableau as $nom=>$value){
            //pas de traitrement des variables de travail
            if (str_starts_with($nom, "wrk")){continue;}
            
            // si la valeur est null (c'est une classe)
            if(is_null($value)){ 
                 //Si c'est une classe, retour de 'idClasse'=id . Exemple 'idCommentaire'=>3
                $zones['id'. ucfirst($nom)]= $classe->$nom->id;
            }
            else{
                //si pas trouvé dans le tableau d'exclusion et pas un tableau car ce sont des collections
                $sauf =  defined(get_class($classe) . '::SAUF') ? $classe::SAUF : [];
                if(!in_array($nom, $sauf) && !is_array($value)){
                    $zones[$nom]= $classe->$nom;
                }
            }
        }

        return $zones;
    }
    
/********************************************************************************
* Fonctions d'UPDATE
*******************************************************************************/

    public static function classeUpdateClassesLiees(object $classe) :void{
    //Met à jour physiquement les classes liées d'une classe pour renseigner leurs ID
        //lecture des attributs de la classe (get_class revoie les atrtibuts par défaut)
        //inclusions contient la liste des classes externes concernées par les mise à jour
        $tableau = get_class_vars(get_class($classe));
        $inclusions = $classe::INCLUDE_CRUD;
        //Traitement
        foreach($tableau as $nom=>$value){
            //is_null indique cque c'est une classe
            if(is_null($value) && isset($classe->$nom->id)  && in_array($nom, $inclusions)){
                //'Si c'est une classe, alimentation l'id seulement
                $classe->$nom->update();
            }
        }
    }
    
    public static function classeUpdateCollection(object $classe){
    //fait la mise à jour physique des classes instanciées en collection
    //oriente vers la collection associative ou interne
        $retour = true;
        //extraction des valeurs tableau seulement
        $tableau= array_filter(get_class_vars(get_class($classe)), function($v) {
                                        return is_array($v) ;
                                    });
        //Traitement
        foreach($tableau as $nom=>$value){
            if(count($classe->$nom) > 0){
                //test si collection interne (la clé est dans la table BDD de l'entité instanciée, exemple detailSetList liée à une seule setlist)
                //ou associative (çà passe par une classe de type ass
                $namespace = self::classeGetInfos(get_class($classe))[0];  //namespace de la classe qui possède la collection
                $nomClasseAssociative = $namespace . "\\" . self::classeGetAlias($classe). self::classeGetAlias($classe->$nom[0]). "_ass";
                if (self::classeChargerSiExiste($nomClasseAssociative)){
                    //si la classe association existe, appel à la méthode associative
                    $retour = self::updateCollectionBDDass($classe,$nom,$nomClasseAssociative);
                }
                else{
                    //'Si c'est une collection interne ,
                    $retour = self::updateCollectionBDDinterne($classe,$nom);
                }
            }
        }
        return $retour;
    }
    
    private static function updateCollectionBDDinterne(object $classe,string $nomCollection){
    //Mise à jour physique de la collection avec une cardinalité 0-n , l'Id de la table mere est dans la table
    //La table contient un cle externe qui est celle du propriétaire de la collection, exemple idEtablissement
    //cleExterne est le nom de cette cle, exemple idEtablissement
    //$nomCollection nom de la variable contenant la collection
    //Classe mère est la classe qui possède la collection
       
        if (count($classe->$nomCollection)=== 0){
            $retour = true;
        }
        else{
            //Balayage des instances de la collection
            $retour = true;
            foreach($classe->$nomCollection as $classeCollection){
                //Appel de la methode de mise à jour avec identifiant de la classe qui possède la collection
                if(!$classeCollection->update($classe->id)){$retour = false;}
            }
        }
        return $retour;    
    }

    private static function updateCollectionBDDass(object $classeMere,string $nomCollection, string $nomClasseAssociative){
    //Mise à jour physique de la collection avec une cardinalité n-n , 
    //Une table d'association, associée à une classe contient les identifiants liés
    //paramètres instance de l'objet qui détient la collection et le nom de la variable collection, exemple "liens"

        if (count($classeMere->$nomCollection)==0){
            $retour = true;
        }  
        else{
            foreach($classeMere->$nomCollection as $classe){
                //Mise à jour de la classe collection pour avoir l'ID si creation
                $retour = $classe->update();
                //Si une mise à jour sans anomalie, mise à jour de la classe associative
                if($retour){
                    //Création de la classe associative en donnant les deux classes associées
                    $classAssociative = new $nomClasseAssociative($classeMere,$classe);
                    //mise à jour de l'association
                    $retour = $classAssociative->update();
                    unset ($classAssociative);
                }
            }
        }
        return $retour;    
    }
    
/********************************************************************************
 * Fonctions de DELETE
 *******************************************************************************/
    public static function classeDelete(object $classe):bool{
    //Supprime physiquement une classe et ses classes liées et ses collections
    //$exclusions indique les classes liées à ne pas supprimer car elles  sont  en dépendance, attention à la casse c'est le nom de la 
    //variable pas de la classe
        $retour = true;
        $tableau = get_object_vars($classe); //tableau des propriétés
        $inclusions = $classe::INCLUDE_CRUD; //tableau des classes non concernées par CRUD
        //
        //D'abord suppression des éléments qui sont des liens (autres classes)
        foreach($tableau as $nom=>$value){
            //examen des différentes propriétés
            if(is_object($value) and in_array($nom, $inclusions)){ 
                //Si c'est une classe et que la classe est dans les inclusions, suppression de la classe
                if($retour) {$retour = $value->delete();}
            }
            elseif(is_array($value) and count($value) > 0){ 
                //Si c'est une collection avec des instances 
                if($retour) {$retour=self::deleteCollectionBDD($value,$classe);}
            }
        }
        
        //enfin suppression physique dans la table qui contient la classe
        if ($retour) {Model::mdDelete($classe::TABLE, ['id' => $classe->id]);}
        return $retour;
    }
    
    public static function deleteCollectionBDD(array $collection,object $classeProprietaire) :bool{
    //mise à jour physique, applique la fonction delete à toutes les instances de la collection
        $retour = true;   
        //recharche si une classe ASS existe si collection pas vide
        if (count($collection) >0){
            $nomclasse = get_class($classeProprietaire). get_Class($collection[0])."_ass";
        }
        foreach($collection as $classe){
            //Si une mise à jour en erreur anomalie
            if(!$classe->delete()){$retour = false;}
            //Suppression de l'association si classe existe  - false pour ne pas vouloir la charger à tous les coups -
            if (class_exists($nomclasse,false)){
                $classeAss = new $nomclasse ($classeProprietaire,$classe);
                if(!$classeAss->delete()){$retour = false;}
            }
        }
        return $retour;    
    }

/********************************************************************************
* Fonctions de restitutions de propriétés 
*******************************************************************************/
      public static function classeAproprieteRenseignee($classe,bool $exclureID = true){
    //teste si une valeur de la classe est renseignée 
        $retour = false;
        $tableau = get_class_vars(get_class($classe));
        foreach($tableau as $nom=>$value){
            if ($nom=='id'){
                //si id il faut traiter mais pas les autres cas
                if(!$exclureID && $classe->id >0){$retour=true;}
            }
            elseif (is_string($value) && strlen($classe->$nom)>0){
                $retour = true;
            }
            elseif(is_numeric($value) && $classe->$nom>0){
                $retour = true;
            }
            elseif(is_array($value) && count($classe->$nom)>0){
                $retour = true;
            }
            elseif(class_exists($nom,false) && $classe->$nom->id > 0){
                //Classe existe avec un ID renseigné
                $retour = true;
            }

            if ($retour){break;}
        }
        //fin
        return $retour;
    }
    public static function majClassePropriete($valeur,$propriete):void {
    // Fait une mise à jour de la valeur seulement si renseignée
        switch (gettype($valeur)) {
            case "integer":
            case "double":
                if ($valeur <> 0){$propriete=$valeur;}
                break;
            case "string":
                if (strlen($valeur) > 0){$propriete=$valeur;}
                break;
            default:
                $propriete=$valeur;
        }
    }
    
    private static function classeGetAlias(object $classe, bool $nomCourt=false):string{
    // L'alias est le nom de la classe parente si il y en a une, sinon la classe elle m^me
    // si la classe parente est classe Mere çà compte pas
    //alias sous la forme shared\php\classes\lien\TypeLien ou sans namespace sit nomcourt = true
        if (get_parent_class($classe)!=false  && !str_contains(get_parent_class($classe),"\Mere")){  //revoie false si pas de classe parente
            $alias = get_parent_class($classe);}
        else{
            $alias = get_class($classe);}
        if ($nomCourt){$alias = self::classeGetInfos($alias)[1];}
        //renvoi du nom de la classe
        return self::classeGetInfos($alias)[1];
    }
    
    public static function classeAttributValorise($instance,string $attribut):bool{
    //renvoie vrai si la valeur de l'attribut est différente de la valeur par défaut
        $retour = true;
        if ($instance->$attribut === get_class_vars(get_class($instance))[$attribut]){
            $retour = false;
        }
        return $retour;
    }
    
    public static function classeGetIdAssociation(object $classe) :int{
    //charche l'Id d'une table d'association en lui passant la table ass
        $tableau = get_object_vars($classe);
        $requete = "SELECT id FROM  " . $classe::TABLE . " WHERE ";
        //Recheche des classes présentes
        $zones=[];
        foreach($tableau as $nom=>$value){
            // Donne la liste des variables et leur valeur
            if(is_object($value)){ 
                 //Si c'est une classe, retour de 'idClasse'=id . Exemple 3
                //la zone de la table est celle de la classe mère si héritage, exemple lien pour lien_etab
                $requete .= count($zones)=== 0 ? "" : " AND ";
                $requete .= " id". ucfirst($nom) . "=? ";
                $zones[]= $classe->$nom->id;
            }
        }
        $retour = Model::mdRequeteListerUnique($requete ,$zones);
        if (count($retour)==0){return 0;}
        else {return $retour['id'];}
    }
    
    public static function classeGetInfos(string $nomComplet):array{
    //reconstitue le nom de la classe et son namespaceà partir du chemin complet avec namespace
        if (str_contains($nomComplet, "\\")){
            $pos = strripos($nomComplet,"\\"); //position de la dernière occurence
            $nom = substr($nomComplet,$pos+1);
            $namespace = substr($nomComplet,0,$pos);
        }
        else{
            $nom = $nomComplet;
            $namespace = "";
        }
        //renvoi tableau avec les deux infos
        return array($namespace,$nom);
    }
   
    public static function classeGetClass(object $classe):string{
    //renvoie le nom d'une classe instanciée sans le nameSpace
        $nom = get_class($classe);
        $tableau = explode('\\', $nom);
        return (string)$tableau[count($tableau)-1];
    }
}