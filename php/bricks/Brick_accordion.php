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
    public static function accordion3ItemEntreTitreEtContenu(int $numero, $expanded = true){
    // retourne la partie nécesaire pour la construction d'un accordiaon
    //$collapse = false, l'accordeéon reste ouvert  des le départ   

        $myhtml = "</button></h2>";
        $myhtml .= "<div id=\"flush-collapse" . $numero . "\" " .
                    "class=\"accordion-collapse collapse " ;
        if ($expanded){$myhtml.= "show";}
        $myhtml .=  " \" " .
                    "aria-labelledby=\"flush-heading" . $numero . "\" " ;
                if ($expanded){$myhtml.= "data-bs-parent=\"#accordionFlushExample\"";}
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
