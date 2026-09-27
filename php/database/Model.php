<?php
namespace shared\php\database;

/*******************************************************************************
 * Description of Model
 * Implémente les fonction d'accès à la base de données
 * @author araib
 ******************************************************************************/
use PDO;
use shared\php\toolbox\Toolbox    as Tbx;

class Model {
    
    public const string SYMBOLE_POINT = "ZZZ";
 
    public static function mdDelete(string $table, array $zones) {
    //Traitement spécial si suppression sur ID
    //Exemple mdDelete("etablissement_contact", ['ID'=>$contact['idContact']]);
        $requete = "DELETE FROM " . $table . self::mdClauseWhere($zones) . " ;";
        return self::mdRequeteExecuter($requete,$zones);
    }
    
    public static function mdDeleteAvecClauseWhere(string $table, string $clauseWhere) {
    //Traitement spécial si suppression sur ID
    //Exemple mdDelete("etablissement_contact", "id > 18");
        $requete = "DELETE FROM " . $table . " WHERE " . $clauseWhere . " ;";
        return self::mdRequeteExecuter($requete);
    }
    
     public static function mdDeleteAvecClauseWhereStatique(string $table, array $zonesClauseWhere) {
    //constitue une clause where where ID=valeur et le tableau $zones contient ['ID'=>3]
        // parenthèses pour être couplées à d'autres clauses
        $requete = "DELETE FROM " . $table . self::mdClauseWhereStatique($zonesClauseWhere);
        return self::mdRequeteExecuter($requete);
    }

    public static function mdDeleteMultiple(array $tablesCibles, array $zones, array $tablesSupp=[]){
    //tablesCibles : tables utilisées pour la sélection et les jointures
    //  exemple [['nom' =>"prospect",'cle'=>'id'],
    //          ['nom' =>"prospect_suivi",'cle'=>'idProspect','jointure'=>'I']],
    //          ['prospect'.SYMBOLE_POINT.'id' => $this->id]);*/
    //tablesSupp liste des tables à supprimer, si omis c'est les tables cibles qui seront prises en compte exemple['table1','table2']
    //zones : zones utilisées pour la clause where 
  
    /*exemple de requete finale 
    DELETE seanceElement
    FROM tr_seance_element as seanceElement
    INNER JOIN tr_exercice as exercice ON seanceElement.idExercice = exercice.ID 
    WHERE seanceElement.idSeance = 1 AND exercice.idBibliotheque=4*/
              
        //tables à supprimer
        $requete = self::mdDeleteMultiple_delete($tablesCibles,$tablesSupp);
        
        //tables de clause FROM avec jointures
        $requete .= self::mdClauseFromJoin($tablesCibles);
        
        //clause where 
        $requete .=  " " .  self::mdClauseWhere($zones);
        
        //exécution
        return self::mdRequeteExecuter($requete,$zones);
    }
    
    private static function mdDeleteMultiple_delete(array $tablesCibles, array $tablesSupp ) :string {
    //formate le début de la clause delete
        if (count($tablesSupp)==0){
            foreach($tablesCibles as $tableCible){
                $tablesSupp[] = $tableCible['nom'];
            }
        }
        $requete = "DELETE " . $tablesSupp[0];
        for ($i=1; $i< count($tablesSupp); $i++){
            $requete .= " , " . $tablesSupp[$i] . " ";
        }
        return $requete . " ";
    }

    public static function mdUpdate(string $table , array $zones, string $clauseWhere ):bool{
    //mise à jour d'une table à partir d'un tableau de données['nom'=>valeur] et d'une clause where
        if (strlen($clauseWhere)==0){die ("mdUpdateInsert : Clause Where absente" . " pour update table " . $table);}
        $requete =  "UPDATE " . $table . " set " . self::mdUpdateInsertRequeteZones($zones);
        $requete .= " WHERE " . $clauseWhere;
        return  self::mdRequeteExecuter($requete,$zones,true);      //controle d'existence de la clé
    }

    public static function mdInsert(string $table , array $zones):bool{
        $requete =  "INSERT " . $table . " set " . self::mdUpdateInsertRequeteZones($zones);
        return  self::mdRequeteExecuter($requete,$zones);
    }

    public static function mdUpdateInsertAuto(string $table,array $zones,string $zoneIdentifiant='id'){
    //si identifiant connu c'est un update, sinon un insert
    //zoneIdentifiant nom de la zone cle primaire
      
        //recherche existence enregistrement avec id renseigné
        if (count(Model_utils::mdSelectTable($table,[$zoneIdentifiant=>$zones[$zoneIdentifiant]]))===0){
            if (Model_utils::hasAutoIncrementPK($table)){
                //si la table est en autoidentifiant, l'identifiant est supprimé des zones
                $tableau = Tbx::oterDatas($zones,[$zoneIdentifiant]);
                $retour = self::mdInsert($table,$tableau);
            }
            else{
                //sinon création avec l'id fourni
                $retour = self::mdInsert($table,$zones);
            }
        }
        else {
            //sinon modif de l'enregistrement existant
            $tableau = Tbx::oterDatas($zones,[$zoneIdentifiant]);
            $retour = self::mdUpdate($table,$tableau,$zoneIdentifiant ."=" . $zones[$zoneIdentifiant]);
        }
        return $retour;
    }
    
    

    public static function mdUpdateInsertRequeteZones(array $zonesNommees): string {
        $requete ="";    
        //zones = tableau association exemple zone['nom']="Raibaut"
        //'SELECT nom, prix FROM jeux_video WHERE possesseur = :possesseur AND prix <= :prixmax'
        foreach ($zonesNommees as $key => $value) {
            $requete .= $key . "=:" . $key . ",";
        }
        //retourne sans la dernière virgule
        return substr($requete, 0, - 1);
    }

    public static function mdClauseWhere(array $zonesNommees): string {
    //constitue une clause where where ID=:ID et le tableau $zones contient ['ID'=>3]
    //si table d'appartenance mettre le contenu de la constante SYMBOLE_POINT à la place du . , exemple ['song SYMBOLE_POINT ID'=>3]
        // parenthèses pour être couplées à d'autres clauses
        $requete = " WHERE (";    
         //'SELECT nom, prix FROM jeux_video WHERE possesseur = :possesseur AND prix <= :prixmax'
        // A faire évoluer pour gérer les OR et les < ou >
        $ctr = 0;
        foreach ($zonesNommees as $key => $value) {
            $ctr++;
            $requete .= $ctr ===1 ? "" : " AND ";
            $requete .= str_replace(self::SYMBOLE_POINT,".",$key) . "=:" . $key ;
        }
        return $requete . ")" ;
    }
    
    public static function mdClauseWhereStatique(array $zones): string {
    //constitue une clause where where ID=valeur et le tableau $zones contient ['ID'=>3]
        // parenthèses pour être couplées à d'autres clauses
        $ctr = 0;
        $requete = " WHERE (";    
        foreach ($zones as $key => $value) {
            $ctr++;
            $requete .= $ctr ===1 ? "" : " AND ";
            $requete .= $key . "=" . $value;
        }
         //retour
        return $requete . ")" ;
    }
    
    
    public static function mdClauseIn(array $valeurs, bool $forcer = false):string {
    // formatte une clause In à partir d'un tableau
    //forcer retourne une clause in même si un seul item (au cas où not devant)
    //les nombres sont insérés tels quels, les textes sont échappés par PDO (protection injection SQL)
        $valeurs = array_values($valeurs);
        if (count($valeurs) === 0){
            //liste vide : condition toujours fausse au lieu d'une erreur SQL
            return "IN(NULL)";
        }
        $formatees = array_map(
            fn($valeur) => is_int($valeur) || is_float($valeur) || (is_string($valeur) && is_numeric($valeur))
                ? (string)(0 + $valeur)
                : Database::dbConnect()->quote((string)$valeur),
            $valeurs);
        if (count($formatees) === 1 && !$forcer){
            return "=" . $formatees[0];
        }
        return "IN(" . implode(",", $formatees) . ")";
    }

    public static function mdClauseFromJoin(array $tables){
    /*exempleFROM etudiant
    JOIN etudiant_cours ON etudiant.id = etudiant_cours.etudiant_id
    JOIN cours ON cours.id = etudiant_cours.cours_id;*/
    //$tables liste des tables sous forme de tableau à 3 postes, table, cle, jointure
        $requete = " FROM ";
        //table principale
        $tableMaitre = $tables[0]['nom'];
        $cleMaitre = $tables[0]['cle'];
        $requete .= $tableMaitre . " " ;
        //tables associées
        for ($i=1; $i<count($tables) ; $i++){
            //Traitement de chaque table
            $table = $tables[$i];
            if (!isset($table['jointure'])){$table['jointure']="I";} //défaut jointure interne
            switch ($table['jointure']){
                case "I" :
                   $requete .= " INNER JOIN ";
                    break;
                case "L" :
                   $requete .= " LEFT JOIN ";
                    break;
                case "R" :
                   $requete .= " RIGHT JOIN ";
                    break;
            }
            // jointure
            $requete .= $table['nom'] . " ON " . $tableMaitre . "." . $cleMaitre . " = " . $table['nom']. "." . $table['cle'] . " ";
        }
        return $requete;
    }
    
    public static function mdRequeteExecuter( string $requete, array $zones=[], bool $controle = false){
    // Exécute une requete et renvoie true ou false selon si bien exécuté ou pas
    //zones = tableau association exemple zone['nom']="Raibaut"
        $database = Database::dbConnect();
        //exécution 
        $statement = $database->prepare($requete);
        $statement->execute($zones);
        
        //retourne true ou false;
        $retour = false;
        if($statement->errorCode()==PDO::ERR_NONE){$retour=true;}
        if($controle && $statement->rowCount() === 0) {$retour = false;}
        return $retour;
    }
    
    public static function mdRequeteExecuterParamPositionnel( string $requete, array $zones=[]){
    //exemple requete WHERE  lien.idTypeLien= ? AND setlist_detail.idSetlist = ?  et appel Model::mdRequeteLister($requete,[$idTypeLien, $idSetList]);
            return self::mdRequetePreparer($requete,$zones);
    }

    public static function mdRequeteListerZoneUnique(string $requete, string $zone, array $parametres =[]) {
    //Retourne un tableau multienregistrements mais pour une zone seule
        $statement =  self::mdRequetePreparer($requete,$parametres);
        $retours = [];
        while ($row = $statement->fetch()) {
            $retours[] = $row[$zone];
        }
        return $retours;
    }
    
    public static function mdRequeteListerUnique(string $requete, array $parametres =[]) :array{
    //Retourne un tableau avec les zones du select pour un select avec un enregistrement
    //la requete ne doit renvoyer qu'un enregistrement
        $tableau = self::mdRequeteLister($requete,$parametres);
        if (count($tableau)== 0)
        {return [];}
        else 
        {return $tableau[0];}
    }
    
    public static function mdRequeteLister(string $requete, array $parametres =[]) :array {
    //Liste qui renvoie un tableau de tableau des zones retournées par le sélect 
    //une ligne de tableau = 1 enregistrement
    //chaque poste est un tableau associatif
    //exemple requete WHERE  lien.idTypeLien= ? AND setlist_detail.idSetlist = ?  et appel Model::mdRequeteLister($requete,[$idTypeLien, $idSetList]);
        $statement =  self::mdRequetePreparer($requete,$parametres);
        $retours = [];
        while ($row = $statement->fetch()) {
            $retour = [];
            foreach ($row as $key => $value){
                if (!is_int($key)){
                    $retour[$key] = $value;
                }
            }
            if (count($retour) > 0){ $retours[] = $retour;}
        }
        return $retours;
    }
    
    public static function mdRequetePreparer(string $requete, array $parametres=[]) {
    //gènère un statement
    // exemple  WHERE  lien.idTypeLien= ? AND setlist_detail.idSetlist = ?  et appel Model::mdRequeteLister($requete,[$idTypeLien, $idSetList])
        $database = Database::dbConnect();
        if (count($parametres)==0){
           //pas de parametres passés
           $statement = $database->query($requete); 
        }
        else {
            //paramètres à exécuter
            $statement = $database->prepare($requete);
            $statement->execute($parametres);    
        }
        return $statement ;
    }
    
    public static function mdPrimaryIsAutoIncrement(string $table):bool{
        
        $requete ="SHOW COLUMNS FROM ". $table ." WHERE `Key` = 'PRI'";
        $result = self::mdRequeteListerUnique($requete);

        if ($result && str_contains($result['Extra'], 'auto_increment')) {
           return true;
        }
        else{
            return false;
        }
    }
}
