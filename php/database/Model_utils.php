<?php
namespace shared\php\database;

/*******************************************************************************
 * Description of Model
 * Implémente les fonctions utilitaires sql
 * @author araib
 ******************************************************************************/
use PDO;
class Model_utils {
    
    public const BETWEEN_VALEUR=1;
    public const BETWEEN_POURCENT=2;
    public const BETWEEN_BORNES=3;
    
    public static function mdCount(string $table , array $zonesNommees ,string $nomId="id") {
    // Retourne la requete count
        return self::mdStat($table,"count",$zonesNommees,$nomId);
    }
    
    public static function mdStat(string $table ,string $stat, array $zonesNommees,string $nomId="id" ) {
    // Retourne la requete stats avec stats = sum, avg, count
        $requete = "SELECT " . $stat . "(". $nomId . ") as nbr
                FROM " . $table . Model::mdClauseWhere($zonesNommees) . " ;";
        $nb =  Model::mdRequeteListerUnique($requete,$zonesNommees);
        return (int)$nb['nbr'];
    }
    
    public static function mdGetMax( string $table , string $nomZone="id" ): int {
    //Retourne l'identifiant maximum qui vient d'être créé
        $requete =  "SELECT max(" . $nomZone . ") as valeurMax FROM " . $table ;
        $nb =  Model::mdRequeteListerUnique($requete);
        return  (int)$nb['valeurMax'];
    }
    
    public static function mdSelectFromClasse($classe, array $zonesWhere, array $zonesOrdre){
    //renvoie une selection pour charger une classe avec une clause loadFrom array
        $tableau = is_object(classe) ? get_class_vars(get_class($classe)) : get_class_vars($classe);
        $clauseSelect = "SELECT ";
        $clauseFrom = " FROM " . $classe::TABLE . " as " . $classe  ;
        foreach($tableau as $nom=>$value){
            if (str_starts_with($nom, "wrk")){continue;}
            
            //Chargement de la donnée si pas une classe, si pas une collection (tableau) 
            if (!is_null($value) && !is_array($value)){
                //exemple bibliotheque.id as idBibliotheque
                $clauseSelect .=  $classe . "." . $nom . " as " . $nom . ucfirst($classe);
            }
            elseif(is_null($value)){
                //'Si c'est une classe, alimentation l'id seulement
                $clauseSelect .=  $nom . "." . $nom . " as " . $nom . ucfirst($classe);
            }
        }
        if (count($clauseWhere) > 0) {$requete.= Model::mdClauseWhere($clauseWhere);}
        if (strlen($zonesOrdre) > 0) {$requete.= " ORDER BY " . $zonesOrdre;}
        $requete = "SELECT bibliotheque.* , commentaire.texte
                FROM " . self::TABLE . " as bibliotheque 
                              LEFT JOIN " . Commentaire::TABLE . " as commentaire ON bibliotheque.idCommentaire = commentaire.id";
        return Model::mdRequeteLister($requete);
    }
    
    public static function mdSelectTable($table, array $clauseWhere, string $zonesOrdre=""){
    //renvoie un select sur une table avec une clause where en zones nommées['zone'=>Valeur]
        $requete = "SELECT * " .
                    " FROM " . $table ;
        if (count($clauseWhere) > 0) {$requete.= Model::mdClauseWhere($clauseWhere);}
        if (strlen($zonesOrdre) > 0) {$requete.= " ORDER BY " . $zonesOrdre;}
        return Model::mdRequeteLister($requete,$clauseWhere);
    }
    
    public static function mdSelectTableUnique($table, array $clauseWhere){
    //renvoie un select sur une table avec une clause where en zones nommées['zone'=>Valeur]
    //et qui renvoie un résultat unique
        $requete = "SELECT * " .
                    " FROM " . $table ;
        $requete.= Model::mdClauseWhere($clauseWhere);
        return Model::mdRequeteListerUnique($requete,$clauseWhere);
    }
    /***************************************************************************
     * Infos technique
     **************************************************************************/
    public static function hasAutoIncrementPK(string $table): bool{
    
        if(!isset ($_SESSION['autoincrement'][$table])){
            $requete = "
                SELECT COUNT(*) as total
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME   = ?
                  AND COLUMN_KEY   = 'PRI'
                  AND EXTRA        LIKE '%auto_increment%'
                  ";
            $_SESSION['autoincrement'][$table] = (bool) Model::mdRequeteListerUnique($requete, [$table])['total'];
        }
        return $_SESSION['autoincrement'][$table];
    }
    /***************************************************************************
     * Générateur de SQL
     **************************************************************************/
    public static function clauseBetween(float $valeur, float $ecart, int $valeur1Pourcentage2Bornes3, bool $arrondi = false ):string{
    //formatte une clause between
        if ($ecart==0){
            $clause = "=" . $valeur;
        }
        else{
            switch ($valeur1Pourcentage2Bornes3){
                case self::BETWEEN_VALEUR:
                    $bmin = $arrondi ? round($valeur - $ecart,0,PHP_ROUND_HALF_UP) : $valeur - $ecart ;
                    $bmax = $arrondi ? round($valeur + $ecart,0,PHP_ROUND_HALF_UP) : $valeur + $ecart ;
                    break;
                case self::BETWEEN_POURCENT:
                    $bmin = $arrondi ? round($valeur *(1- $ecart/100),0,PHP_ROUND_HALF_UP) : $valeur *(1- $ecart/100) ;
                    $bmax = $arrondi ? round($valeur *(1+ $ecart/100),0,PHP_ROUND_HALF_UP) : $valeur *(1+ $ecart/100) ;
                    break;
                case self::BETWEEN_BORNES:
                    $bmin = $arrondi ? round($valeur,0,PHP_ROUND_HALF_UP) :$valeur ;
                    $bmax = $arrondi ? round($ecart,0,PHP_ROUND_HALF_UP) :$ecart ;
            }
            $clause = $bmin==$bmax ? "=" . $bmin : " BETWEEN " . $bmin . " AND " . $bmax;
        }
        return $clause;
    }
}
