<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * fonctions pour dates
 *******************************************************************************/
class Toolbox_date {
    public static function dateFormat($mydate, string $format) : string{
    //met en forma une date en format objet ou string
        if (is_string($mydate)){
            return  date($format, strtotime($mydate));
        }
        else{
            return date_format($mydate,$format);
        }
    }
    
    public static function dateSecondesFormatMMSS(int $secondes) : string{
    //renvoie en format 
        $minutes = floor($secondes / 60);
        $secondesRestantes = $secondes % 60;

        return sprintf('%02d:%02d', $minutes, $secondesRestantes);
    }
    
    public static function dateFormatJJMMAAAA($mydate) : string{
    //sate format usuelle JJMMAA
        return self::dateFormat($mydate,"d/m/Y");
    }

    public static function dateDuJour(string $format = "Y/m/d"):string {
        return self::dateFormat(date_create(),$format);
    }

    public static function dateAjouter(string $dateInitiale, int $duree, string $typeDuree = "d", string $formatRetour = "Y-m-d" ){
    //ajoute une durée à une date et retourne le résultat sur un format donné

        $dateObject = \DateTimeImmutable::createFromFormat('Y-m-d',$dateInitiale);
        if ($duree > 0){
            $interval = \DateInterval::createFromDateString($duree . self::conversionTypeDuree($typeDuree) );
            $dateObject = $dateObject->add($interval);
        }
        return $dateObject->format($formatRetour);
    }
    public static function dateHeure(){
    //renvoie l'heure actuelle
       return (new \DateTimeImmutable())->format('H:i:s');
    }
    
    public static function conversionTypeDuree(string $typeDuree):string{
        switch ($typeDuree) {
            case "d":
                return " days" ;
            case "m":
                return " months" ;
            case "y":
                return " years" ;
            case "w":
                return " weeks" ;
        }
    }
    public static function jourDeLaSemaine(int $jour, int $nbLettres=2, int $majuscule = 1){
        if ($jour==0){$jour=7;} //si manche est jour 7 
        $liste = array("lundi","mardi","mercredi","jeudi","vendredi","samedi","dimanche");
        return self::traiterMoisJour($liste[$jour -1],  $nbLettres, $majuscule);
    }
    
    public static function moisEnLettres(int $mois, int $nbLettres=3, int $majuscule = 1){
        $liste = array("janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre");
        return self::traiterMoisJour($liste[$mois -1],$nbLettres, $majuscule);
    }
    
    public static function traiterMoisJour(string $mot,  int $nbLettres, int $majuscule){
    //formatte le mois jour en extrayant le bon poste

        //Adaptation du nombre de caractères 
        $nbExtract = strlen($mot);
        if ($nbLettres < $nbExtract) {$nbExtract = $nbLettres;}
        //extraction du nombre de caractères désirés
        $lettre = mb_substr($mot,0,$nbExtract,"UTF-8");
        if ($majuscule > 0){
            $lettre = mb_strtoupper(mb_substr($lettre,0,$majuscule,"UTF-8"),"UTF-8");
            // si nombre majuscule plus petit
            if($majuscule < $nbExtract){
                //ajout du reste
                $lettre .= mb_substr($mot,$majuscule,($nbExtract - $majuscule),"UTF-8");
            }
        }
        return $lettre;
    }
    
    public static function dateValiderAMJ(string $dateString){
        // Crée l'objet DateTime selon le format AAAA-MM-JJ (Y-m-d)
        $d = \DateTime::createFromFormat('Y-m-d', $dateString);
        // Vérifie que la date est valide ET correspond exactement au format (exclut le 31 février)
        return  $d && $d->format('Y-m-d') === $dateString;
    }
    public static function dateValiderJMA(string $dateString){
        // Crée l'objet DateTime selon le format JJ-MM-AAAA (Y-m-d)
        $d = \DateTime::createFromFormat('d-m-Y', $dateString);
        // Vérifie que la date est valide ET correspond exactement au format (exclut le 31 février)
        return  $d && $d->format('d-m-Y') === $dateString;
    }
}