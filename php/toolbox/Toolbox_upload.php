<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/* 
 * 
    $_FILES["photo"]["name"] — Cette valeur du tableau spécifie le nom original du fichier, y compris l’extension du fichier. Il n’inclut pas le chemin d’accès au
    $_FILES["photo"]["type"] — Cette valeur du tableau spécifie le type MIME du fichier.
    $_FILES["photo"]["size"] — Cette valeur du tableau spécifie la taille du fichier, en octets.
    $_FILES["photo"]["tmp_name"] — Cette valeur du tableau spécifie le nom temporaire, y compris le chemin complet qui est assigné au fichier une fois qu’il a été uploadé sur le serveur.
    $_FILES["photo"]["error"] — Cette valeur du tableau spécifie le code d’erreur ou d’état associé à l’upload du fichier, par exemple 0, s’il n’y a pas d’erreur.

 */

use shared\php\classes\lien\TypeLien                    as TypeLien;
use shared\php\classes\socle\User                       as User;
use shared\php\classes\telechargement\Telechargement    as Telechargement;
use shared\php\classes\personalisation\Nomenclature     as Nomenclature;
    
class Toolbox_upload {
    

    public static function  fichierPath (){
        return "fichiers/";
    }
     
    public static function uploadReceptionLien(bool $telechargement = false):bool {
    //Réception des fichiers lors d'un post d'une saisie comportant des liens
    //paramètre si demande sur lien telechargement ou autre
    // erreur 1 fichier dépasse upload_max_filesize = 7M
        $retour = true;
        if((string)filter_input(INPUT_SERVER,"REQUEST_METHOD") == "POST"){
            //recherche des fichiers issus d'input nomméa fileUploadnn avec nn <99
            $fichiers= array_filter($_FILES, function($k) {
                                        return str_starts_with($k,"fileUpload") && substr($k,-2,2)<>"99";
                                    }, ARRAY_FILTER_USE_KEY);
            //si trouvés
            foreach($fichiers as $key =>$valeurs) {
                //attention param upload_max_filesize ds php.ini 
                if($_FILES[$key]["error"] == UPLOAD_ERR_OK){
                    //Recherche des l'instance de lien qui correspond
                    $poste = substr($key,strlen("fileUpload"));
                    $typeLien = new TypeLien(Toolbox_adressage::getPost("I",'idTypeLienLien'.$poste));
                    if ($telechargement){
                        //si téléchargement le répertoire est défini dans les instructions de téléchargement
                        $repertoire = self::repertoireTelechargement();
                    }
                    else{
                       //si lien classique c'est le type de lien qui porte le répertoire
                       $repertoire = self::repertoireHorsTelechargement($typeLien->repertoire,(int)$poste);
                    }
                    //controle existence et création si existe pas);
                    $retour = Toolbox_upload::uploadIsDirOrCreateIt($repertoire);
                    //import du fichier
                    if ($retour){
                        $retour = self::uploadReceptionUnitaire($valeurs,$repertoire . "/",$typeLien->extensions);
                    }
                    unset($typeLien);
                }
            }
        }
        return $retour;
    }
    
    public static function repertoireHorsTelechargement( string $repertoireTypeLien, int $poste){
    //retourne le répertoire si pas un téléchrgement
    //il est porté par le type de liens et si c'est personnel rajout du nom utilisateur sur 2 car
        $url = self::fichierPath() . $repertoireTypeLien ;
        $prive = Toolbox_adressage::getPost("C",'priveLien'.$poste);
        if($prive){
            //si privé ajout du code utilisateur
            $url .= "/" . User::connectUserGetInfo("abrev");
        }
        return $url;
    }

    public static function repertoireTelechargement(){
        $repertoire = self::fichierPath() . Telechargement::REPERTOIRE;
        $repertoire  .= Nomenclature::mdNomenclatureGetDetailFromID(Toolbox_adressage::getPost("I","domaine"),"valeurA") . "/" .Toolbox_adressage::getPost("S","repertoire") ;
        return $repertoire;
    }

    public static function extraireNomFichierFromURL(string $url) :string {
       //extrait le nom du fichier sans chemin et sans extension
        $tableau = explode("/", $url);
        $fichier = $tableau[count($tableau)-1];
        $tableau = explode(".",$fichier);
        return $tableau [0];
    }
    //---------------------------------------------------------------------------------------------------

    public static function uploadReceptionUnitaire(array $file , string $repertoireCopie, string $extensionsPossibles):bool {
    // Copie un fichier dans le bon répertoire
        //$file tableau des infos fichiers issu de $_FILES
        // $repertoire copie = répertoire pour copier le fichier avec / à la fin
        $codeRetour = true;
        // Vérifie si le fichier a été uploadé sans erreur.
  
        $filename = $file["name"];  //nom du fichier
        //$filetype = $file["type"]; //type MIME du fichier
        //$filesize = $file["size"]; //taille en octets

       // Vérifie l'extension du fichier
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(!str_contains($extensionsPossibles,strtolower($ext))) {
            die("TbUpload/uploadReceptionUnitaire ATU001 Erreur : Le type " .$ext . " du fichier " . $filename . 
                    " n'est pas autorisé pour le lien associé. Autorisés :" . $extensionsPossibles);
        }
     
        //test si répertoire existe
        //Copie - CR true si succes - fichier écrasé si existe déjà        
        if($codeRetour) {$codeRetour = move_uploaded_file($file['tmp_name'], $repertoireCopie . basename($file['name']));}
        if(!$codeRetour) {die("ATU004 Le fichier n'a pas pu être téléchargé.");}
      
        return $codeRetour ;
    }

    public static function uploadIsDirOrCreateIt($path) {
    // Teste si répertoire existe et sinon le crée
       if(is_dir($path)) {
         return true;
       } 
       else {
         return mkdir($path, 0777,true);
       }
     }

    public static function uploadDeleteFile(string $url) :bool {
    //Supprime un fichier à partir de son URL
        //$url ss la forme  ./fichiers/mp3d/The Communards - Don't Leave Me This Way (with Sarah Jane Morris) [Official Video].mp3
        return @unlink($url);
    }

    public static function return_bytes($val1) {
    //traite un paramètre qui renvoie une taille de fichier (ex 65M)
        $val = trim($val1);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // Le modifieur 'G' est disponible si k on fait les trois
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    public static function lireFichiersSsDossiers(string $repertoire ): string{
    //liste soddiers et ssous dossiers et fichiers d'un répertoire
        //controle existence
        self::uploadIsDirOrCreateIt($repertoire);
        //liste
        $dossier = new \DirectoryIterator($repertoire);
        if(!$dossier->valid()){
            $myhtml='<div class="bg-alert">Il n\'y a aucun élément à afficher.</div>';
        }
        else{
            $myhtml= '';
            foreach($dossier as $fichier){

                // si c"est pas un "." ni ".."
                if($fichier->isDot())
                {continue;} // "continue" permet de passer à l""itération suivante


                //si c'est  un dossier
                if($fichier->isDir()){
                    $myhtml .= '<br><strong>'. $fichier . '</strong>' . '<br>';
                    $myhtml .= self::lireFichiersSsDossiers ($fichier->getRealPath());
                    continue;
                }
                //si c'est un fichier
                if($fichier->getType() == 'file'){
                    //on affiche l'information du fichier lu et un lien hypertexte
                    //Url fichier = url du site + ce qu'il y a à partir de "fichiers"
                    $nomfichier = str_replace("\\","/",substr($fichier->getPathname(), strpos($fichier->getPathname(),"fichiers")));
                    $myhtml .= Telechargement::genererHtmlTagHref($nomfichier ,$fichier->getFilename(),false,null). '<br>';
                }

            }
        }
        return $myhtml;
    }
}