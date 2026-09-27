<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/**
 * Description of Toolbox_html
 *
 * @author Alara
 */

use shared\php\classes\personalisation\Nomenclature    as Nomenclature;

class Toolbox_html {
        
    public static function htmlBoutonValidation(string $texte="Valider"){
        return '<div class="row justify-content-end custom-line mt-2">
                <div class="text-end">
                    <!-- validation du bouton -->
                   <button type="submit" class="btn btn-primary ">' . $texte . '</button>                
                </div>
            </div>';
    }
    
    public static function htmlTexteDefilant(string $texte){
    // le conteneur fenêtre <script src="prism/prism.js"></script> -->
        return '<div class="marquee-rtl">
                <!-- le contenu défilant -->
                <div class="msg">' . $texte . '</div>
                </div>';
    }
    
    public static function htmlMentionVolumeTelechargement(string $complement = ""){
        return '<p><strong>Note : </strong>La taille maximale des fichiers téléchargés est de ' . ini_get("upload_max_filesize"). ".". $complement. '</p>';
    }
    
    public static function htmlGenererRadio(string $groupeNomenclature, int $choix=0) :string{
    //Génère du code Html pour afficher les boustons d'une nomenclature
    //$choix si il y a un choix exprimé
        $html = '<div class="input-group justify-content-left mb-3">';
        //boucle
        $tableau = Nomenclature::mdNomenclatureGetListe($groupeNomenclature,"*");
        foreach ($tableau as $poste){
            //si choix non exprimé, choix par défaut, sinon choix exprimé
            if ($choix===0){
                 $checked = $poste['valeurB']=== 1  ? "checked" : "";
            }
            else{
                $checked = $poste['id']=== $choix ? "checked" : "";
            }
            $html .= '<div class="form-check px-5">';
            $html .= '<input class="form-check-input " type="radio" name="'. $poste['nom'] . '" id="'. $poste['nom'] . '" '. $checked . '>';
            $html .= '<label class="form-check-label " for="'. $poste['nom'] . '">';
            $html .= $poste['valeurA'] ;
            $html .= '</label></input></div>';
        }
        $html .='</div>';
        return $html;
    }
    
    public static function htmlTexteLong(string $texte,int $nbLettres = 50) {
    //affiche un nombre de caractères avec un bouton 
    }
    
    public static function mentionTailleFichiers():string {
        return '<p class="mt-5"><strong>Note:</strong> Taille maximale fichier '. ini_get("upload_max_filesize") . '</p>';
    }
    
    public static function htmlFieldset(string $texte,string $html, string $classe="", int $police =6 ){
    //formatte un champs filedset
    //$classe permet d'ajouer des classes particulières comme  mb-3
        return '<fieldset class="border rounded ' . $classe . '">' .
                    '<legend><h' . $police . '>&nbsp;' .$texte . ' </h' . $police . '></legend>' .   
                    $html .
                    '</fieldset>';
    }
    /*******************************************************************************
    * affichage multimédia
    *******************************************************************************/
    public static function htmlAfficherVideoYouTube(string $lien){
        return '<div class="ratio ratio-16by9">
                    <iframe 
                      src="' . $lien. '" 
                      title="Vidéo YouTube" 
                      allowfullscreen>
                    </iframe>
                </div>';
    }
    
    public static function htmlAfficherVideo(string $source):string{
    //affiche un fichier mp4 exemple chemin/vers/votre-video.mp4
        return     '<div class="ratio ratio-16by9">
                        <video controls preload="metadata" poster="miniature.jpg">
                          <source src="' . $source. '" type="video/mp4">
                          Votre navigateur ne prend pas en charge la lecture de cette vidéo.
                        </video>
                    </div>';
    }
    
    public static function htmlAfficherLecteurAudio(string $source, $numero = 1, $titre=""):string{
    //renvoie le code pour afficher un mecteur audio sur un mp3
    //numero à incrémenter pour différencier les players 
        $tooltip = strlen($titre) ===0 ? $source : $titre;
        $html =  '<div>';
        $html .= '<audio  id="myAudio' . $numero .'" controls title="' . $tooltip . '">';
        $html .= '<source src="' . $source . '" type="audio/mp3">';
        $html .= '       Le navigateur ne peut pas afficher le player.';
        $html .= '</audio></div>';
        return $html;
    }
}
