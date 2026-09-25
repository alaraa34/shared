<?php 
    declare(strict_types=1);
    namespace shared\php\classes\telechargement;
    /*******************************************************************************
     * Template de chargement des téléchargements d'un domaine
     ******************************************************************************/
    use shared\php\bricks\Brick_accordion as BkAccordion;
    use shared\php\modale\Toolbox_modal as TbModal;
    use shared\php\classes\socle\User as User;
    
    ob_start(); 
    $numero = 0;
    $numeroDomaine=0;
    echo("<p align='center'><h2>Téléchargements du domaine " . $libelleDomaine ."</h2></p>");
    echo (BkAccordion::accordion1Debut());
    $resRepertoire = "";
    foreach ($infos as $info) {
        $numero += 1 ;
        if ($resRepertoire == $info['repertoire']){
            //pas de rupture ligne detail
            echo (ligneDetail($numero,$info,$actions));
        }
        else{
            //si resrepertoire existe fermeture
            if (strlen($resRepertoire) >0){
                echo ("</table>");
               //- fin d'accordéon-->
               echo(BkAccordion::accordion4ItemApresContenu());
            }
            //changement de répertoire, ecriture ligne repertoiredomaine et ligne répertoire
            $numeroDomaine +=1;
            $numero = 1;
            echo (BkAccordion::accordion2ItemAvantTitre($numeroDomaine));
            echo ("<div><strong>" .trim($info['repertoire']) ."</strong>" . badge($info) . "<div>");
            echo(BkAccordion::accordion3ItemEntreTitreEtContenu($numeroDomaine,true));
            echo("<table id='liste'.$numeroDomaine .$numero class='table table-striped' >");
            echo (ligneDetail($numero,$info,$actions));
            $resRepertoire =$info['repertoire'];
        }  
    }
    echo ("</table>");
    echo(BkAccordion::accordion4ItemApresContenu());
    echo (BkAccordion::accordion5Fin());
   //ajout des div modales standard et spécifique
   echo (TbModal::afficherListeDivModales($actions));
   $content = ob_get_clean();
   

function ligneDetail(int $numero, array $info,$actions){
    //si user connecté actions 
    $myhtml = "<tr>";
    if (User::userConnecte()){
        $myhtml .="<td>". TbModal::mettreEnFormeActions($actions,$info['id']) ."</td>";
    }
    $myhtml .= "<td>" .$numero . "</td>".
            "<td>" . $info['type'] ."</td>". 
            "<td>" . $info['raccourci'] ."</td>".
            "<td>". $info['commentaire']. "</td></tr>";
    return $myhtml;
}

function badge(array $info):string {
//renvoie un badge avec le nombre de documents dans le repertoire
    return  '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-info">' .
     Telechargement::getNombreDocuments($info['idDomaine'],$info['repertoire']) .
    '<span class="visually-hidden">unread messages</span></span>';
}