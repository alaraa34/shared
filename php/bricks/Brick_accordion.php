<?php
declare(strict_types=1);
namespace shared\php\bricks;
/*******************************************************************************
 * Brique accordeon
 * 
 * 
    echo (BkAccordion::accordion1Debut());
    foreach ($prospects as $prospect) {
        $numero += 1 ;
    ?>
        <?= BkAccordion::accordion2ItemAvantTitre($numero,false);?>
        Affichages entête item
        <?= BkAccordion::accordion3ItemEntreTitreEtContenu($numero,$expandAccordeon);?>
        affichage détail d'un item
        <!-- fin d'accordéon-->
        <?= BkAccordion::accordion4ItemApresContenu();?>
    <!-- fin de boucle php-->
    <?php } 
     
    //fin
    echo (BkAccordion::accordion5Fin());
 *******************************************************************************/
use shared\php\toolbox\Toolbox_liste    as TbListe;
use shared\php\modale\Toolbox_modal     as TbModal;

class Brick_accordion {
    
    public static function accordion1Debut(string $identifiant = "accordionFlushExample"){
    // retourne la partie nécesaire pour la construction d'un accordiaon
        return "<div class=\"accordion accordion-flush\" id=\"".$identifiant ."\">";
    }
    public static function accordion2ItemAvantTitre(int $numero,  string $identifiant ="flush-heading"){
    // retourne la partie nécesaire pour la construction d'un accordioon
    // aria-expanded expended à true affiche le contenu de l'accordéon
    //un item
        $myhtml = "<div class=\"accordion-item\">";
        $myhtml .= "<h2 class=\"accordion-header\" id=\"". $identifiant . $numero . "\">";

        $myhtml .= "<button class=\"accordion-button collapsed\" type=\"button\" "
            . "data-bs-toggle=\"collapse\" data-bs-target=\"#flush-collapse" . $numero 
            . "\" aria-expanded=\"false\" aria-controls=\"flush-collapse" . $numero . "\">";

        return $myhtml;
    }
    public static function accordion3ItemEntreTitreEtContenu(int $numero, $expanded = true,
                            bool $exclusif = false, string $identifiant = "accordionFlushExample"){
    // retourne la partie nécesaire pour la construction d'un accordiaon
    //$expanded = true, l'accordéon est ouvert dès le départ
    //$exclusif = true, ouvrir un item referme les autres (identifiant = celui passé à accordion1Debut)

        $myhtml = "</button></h2>";
        $myhtml .= "<div id=\"flush-collapse" . $numero . "\" " .
                    "class=\"accordion-collapse collapse " ;
        if ($expanded){$myhtml.= "show";}
        $myhtml .=  " \" " .
                    "aria-labelledby=\"flush-heading" . $numero . "\" " ;
                if ($expanded || $exclusif){$myhtml.= "data-bs-parent=\"#" . $identifiant . "\"";}
        $myhtml .= "><div class=\"accordion-body\">";
        return $myhtml;
     }

    public static function accordion4ItemApresContenu(){
    // retourne la partie nécesaire pour la construction d'un accordiaon
        return "</div></div></div>";
    }
    public static function accordion5Fin(){
    // retourne la partie nécesaire pour la construction d'un accordiaon
     return "</div>";
    }
  
    public static function premiereLettre(string $texte) :string{
    //première lettre en majuscule sans accent, "#" si ce n'est pas une lettre (chiffre, symbole)
        $lettre = mb_strtoupper(mb_substr(trim($texte), 0, 1, 'UTF-8'), 'UTF-8');
        $lettre = self::sansAccent($lettre);
        return preg_match('/^[A-Z]$/', $lettre) ? $lettre : '#';
    }

    private static function sansAccent(string $texte) :string{
    //remplace les majuscules accentuées et les minuscules accentuées par leur lettre de base
        $majuscules = ['À'=>'A','Â'=>'A','Ä'=>'A','Á'=>'A','Ã'=>'A','Ç'=>'C',
                       'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Î'=>'I','Ï'=>'I','Í'=>'I',
                       'Ô'=>'O','Ö'=>'O','Ó'=>'O','Õ'=>'O','Ù'=>'U','Û'=>'U','Ü'=>'U','Ú'=>'U',
                       'Ÿ'=>'Y','Ñ'=>'N','Œ'=>'O','Æ'=>'A'];
        $minuscules = [];
        foreach ($majuscules as $accent=>$base){$minuscules[mb_strtolower($accent, 'UTF-8')] = strtolower($base);}
        return strtr($texte, $majuscules + $minuscules);
    }

    public static function grouperParLettre(array $infos, string $zone) :array{
    //regroupe les lignes par première lettre de $zone, triées par $zone dans chaque groupe
    //seules les lettres présentes sont retournées, "#" (chiffres, symboles) en premier
    //retour : [['titre'=>'A', 'lignes'=>[...]], ...]
        usort($infos, fn($a, $b) => strcasecmp(self::sansAccent(trim((string)$a[$zone])), self::sansAccent(trim((string)$b[$zone]))));
        $parLettre = [];
        foreach ($infos as $info){
            $parLettre[self::premiereLettre((string)$info[$zone])][] = $info;
        }
        ksort($parLettre, SORT_STRING);
        $groupes = [];
        foreach ($parLettre as $lettre=>$lignes){
            $groupes[] = ['titre'=>$lettre, 'lignes'=>$lignes];
        }
        return $groupes;
    }

    public static function accordionAfficherComplet(array $infos,array $actions,
                            string $cleEntete="id",
                            string $cleDetail="id", 
                            bool $expandAccordeon= false):string{
    /**
     * affiche un accordeon complet 
     * le tableau d'info doit comporter les infos de chanque enteête + un poste détail
     */
        ob_start();
        $numero = 0;
        echo (self::accordion1Debut());
        foreach ($infos as $info) {
            //entete
            $numero += 1 ;
            $entete[] = $info;
            unset($entete[0]['detail']);
            echo(self::accordion2ItemAvantTitre($numero));
                //liste pour chaque entête d'accordéon
                echo (TbListe::constituerListe(infos: $entete, actions: $actions, cleId: $cleEntete, afficherModales: false));
            echo(self::accordion3ItemEntreTitreEtContenu($numero,$expandAccordeon));
                //détail à déplier de chaque accordéeon
                echo( TbListe::constituerListe(infos: $info['detail'], cleId: $cleDetail));
        
            //<!-- fin pavé d'accordéon-->
            echo(self::accordion4ItemApresContenu());
            unset($entete);
        }
        //fin accordéeon
        echo (self::accordion5Fin());

        //ajout des div modales standard et spécifique
        echo TbModal::afficherListeDivModales($actions);

        return ob_get_clean(); 
    }
}
