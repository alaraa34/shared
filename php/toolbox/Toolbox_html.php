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
    
    public static function htmlGenererRadio(string $groupeNomenclature, int $choix=0, string $nomGroupe="") :string{
    //Génère du code Html pour afficher les boutons d'une nomenclature
    //$choix si il y a un choix exprimé (id du poste)
    //$nomGroupe : attribut name commun à tous les radios du groupe (par défaut le groupe de nomenclature)
    //tous les radios partagent le même name pour être mutuellement exclusifs ;
    //la valeur postée est l'id du poste : (int)$_POST[$nomGroupe] peut être repassé en $choix
        $name = $nomGroupe === "" ? $groupeNomenclature : $nomGroupe;
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
            $html .= '<input class="form-check-input" type="radio" name="'. $name . '" id="'. $poste['nom'] . '" value="'. $poste['id'] . '" '. $checked . '>';
            $html .= '<label class="form-check-label" for="'. $poste['nom'] . '">';
            $html .= $poste['valeurA'] ;
            $html .= '</label></div>';
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
    public static function htmlMenuActions(array $actions, string $titre = "Actions", string $couleur = "text-secondary"):string{
    //bouton rond "3 points" ouvrant un menu d'actions aligné à droite (dropdown Bootstrap 5)
    //$actions : [['texte'=>'Clore', 'href'=>'index.php?...', 'icone'=>'bi bi-check2-circle'], ...] (icone facultative)
    //placé dans un conteneur d-flex, la classe ms-auto le pousse à droite
        $html = '<div class="dropdown ms-auto">
                    <button type="button" class="btn btn-light border rounded-circle d-inline-flex align-items-center justify-content-center p-0 ' . $couleur . '"
                            style="width:2.2rem;height:2.2rem;" data-bs-toggle="dropdown" aria-expanded="false"
                            title="' . htmlspecialchars($titre) . '" aria-label="' . htmlspecialchars($titre) . '">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header">' . htmlspecialchars($titre) . '</h6></li>';
        foreach ($actions as $action){
            $icone = isset($action['icone']) ? '<i class="' . $action['icone'] . ' me-2"></i>' : '';
            $html .= '<li><a class="dropdown-item" href="' . htmlspecialchars($action['href']) . '">' . $icone . htmlspecialchars($action['texte']) . '</a></li>';
        }
        return $html . '</ul></div>';
    }

    /*******************************************************************************
    * affichage multimédia
    *******************************************************************************/
    public static function htmlAfficherLecteurVideo(string $url):string{
    //lecteur adapté à l'url : YouTube (iframe) ou fichier vidéo (balise video)
    //le lecteur prend toute la largeur de son conteneur (format 16/9 Bootstrap 5)
        return self::youTubeId($url) !== "" ? self::htmlAfficherVideoYouTube($url) : self::htmlAfficherVideo($url);
    }

    public static function youTubeId(string $url):string{
    //identifiant de la vidéo pour une url YouTube, "" si ce n'est pas une url YouTube
    //formats : youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID, youtube.com/embed/ID, youtube.com/live/ID
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $trouve)){
            return $trouve[1];
        }
        return "";
    }

    public static function htmlAfficherVideoYouTube(string $lien):string{
    //lecteur YouTube : l'url (watch, youtu.be, shorts...) est convertie en url "embed", seule acceptée dans une iframe
        $id = self::youTubeId($lien);
        $src = $id === "" ? $lien : "https://www.youtube.com/embed/" . $id;
        return '<div class="ratio ratio-16x9">
                    <iframe
                      src="' . htmlspecialchars($src) . '"
                      title="Vidéo YouTube"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      referrerpolicy="strict-origin-when-cross-origin"
                      allowfullscreen>
                    </iframe>
                </div>';
    }

    public static function htmlAfficherVideo(string $source):string{
    //affiche un fichier vidéo (mp4, webm...) exemple chemin/vers/votre-video.mp4
    //pas de type imposé : le navigateur détecte le format du fichier
        return     '<div class="ratio ratio-16x9">
                        <video controls preload="metadata" src="' . htmlspecialchars($source) . '">
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
