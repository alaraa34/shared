<?php
declare(strict_types=1);
/*********************************************************************************
 * Classe destinée à traiter l'affichage des liens 
 *********************************************************************************/
namespace shared\php\classes\lien;

use shared\php\classes\socle\User       as User;
use shared\php\toolbox\Toolbox_liste    as TbListe;
use shared\php\modale\Toolbox_modal     as TbModal;

class Lienhtml 
{
//==============================================================================
//                   LIENS AFFICHAGE DANS LISTE
//==============================================================================
    
     public static function mettreEnFormeCollection(array $liens) {
    //transforme une collection de liens exprimés en tableau en texte html
        foreach ($liens as $index=>$lien){
            $lienObj = new Lien((int)$lien['id']);
            $liens[$index] = $lienObj->genererLienHref();
            unset ($lienObj);
        }
        return $liens;
    }
    
    public  static function afficherLiensCellule(array $tableau, bool $afficherTD=true, bool $groupe = true) : string {
    //affiche tous les liens du même objet dans une seule cellule 
        if ($afficherTD){$texte = '<td align="center">' ;}
        else{$texte='';}
        if ($groupe){$texte.='<div class="input-group d-flex justify-content-center align-items-center">';}
        foreach ($tableau as $item) {
                $texte .=  $item  ;
            }
        if ($groupe){$texte.='</div>';}
        if ($afficherTD){$texte .=  "</td>";}
        return $texte;
    }
    
    public  static function afficherLiensCelluleObjet(array $liens, bool $afficherTD=true, bool $groupe = true) : string {
    //affiche tous les liens du même objet dans une seule cellule 
        $tableau = [];
        foreach ($liens as $lien) {
            $tableau []=  $lien->genererLienHref() ;
        }
        return self::afficherLiensCellule($tableau,$afficherTD,$groupe);
    }
    
//==============================================================================
//                   LIENS TEMPLATE DE SAISIE
//==============================================================================
    public static function afficherBlocLien(array $typesLien, array $liens, bool $afficherPlus = true){
    //affiche un bloc complet de lien sur une page  de saisie détail  
    //paramètres    $typesLien = types de lien possibles pour l'objet détail
    //              $liens = liste des classes de lien pour cette occurrence
    //              $description = besoin d'une colonne description

        //Entête des colonnes
        $myhtml = '<div class="row">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table  class="table table-striped table-bordered">';
        $myhtml .= self::afficherBlocLienHead($afficherPlus);
        
        $myhtml.= ' <tbody id="liens"> ';
        //  Ligne modèle genererLigne dans toolbox 
        $myhtml.=  self::genererLigneLien(Lien::MODELE_LIGNE,$typesLien,null); 

        // Lignes existantes --> 
        $myhtml.= self::afficherLiensExistants($liens,$typesLien);
        $myhtml.= "</tbody></table></div></div></div>";

        return $myhtml;
    }
    private static function afficherBlocLienHead(bool $afficherPlus) :string{
    //initialise le début de la div avec une colonne description ou pas
        $myhtml = '<thead class="table-light table align-middle">
                    <tr hidden>
                        <td><button type="hidden" id="listeExtensions" value="' . typelien::extensionsAutoriseesParTypes() . '"></td> 
                        <td><button type="hidden" id="listeExternes" value="' . typelien::externes(). '"/><td>
                    </tr>
                    <tr>
                        <th class="col-md-1 p-0 text-center no-gutters">';
        $myhtml .=  $afficherPlus   ? '<h5 class="text-success"><strong>
                                            <i class="bi bi-file-earmark-plus-fill" alt="Ajouter une ligne de liens" onclick="jsListeAjoutLien()"></i>
                                      </strong></h5>'
                                    :
                                    'Liens';
        $myhtml .= '</th>
                        <th class="col-md-2 text-center">Type *</th>
                        <th class="col-md-4 text-center">URL *</th>
                        <th class="col-md-4 text-center">Description *</th>
                        <th class="col-md-1 text-center">Privé</th>
                    </tr>
                 </thead>';
        return $myhtml;
    }
    private static function genererLigneLien(int $indice, array $typesLien,  $lien ): string{
    //Génère une ligne de lien sur une liste de liens ds song, contact ou téléchargeùent
    //En cas de modification du type de lien ou du contenu du lien, le js lienModification(ligne) est appelé 
    // en cas de suppression (clic sur poubelle) le lien lienSuppression  est appelé
        $myhtml = '<tr id="lien' . $indice .'"' ; 
        if ($indice==lien::MODELE_LIGNE){$myhtml.= " hidden ";}
        $myhtml .= ">";

        // colonnes cachées avec Id du lien et action faite dessus apportée
        $myhtml .= self::genererLigneLienHiddenZones($indice,$lien);

        //colonne suivante action suppresssion de la ligne 
        $myhtml .= self::genererLigneLienActions($indice);

        //Liste des types de liens possibles
        $myhtml .=  self::genererLigneLienTypesLiens($indice, $typesLien, $lien);

        //URL
        $myhtml .=  self::genererLigneLienURL($indice,$lien);

        //Description
        $myhtml .= self::genererLigneLienDescription($indice,$lien);

        //privatisation
        $myhtml .= self::genererLigneLienPrivatisation($indice,$lien);

        return $myhtml . "</tr>";
    }

    private static function genererLigneLienHiddenZones(int $indice, $lien){
    // colonne caché avec Id du lien et action faite dessus apportée
    //lien est une instance de lien
        $html =  '<td hidden>';
        $html .= '<input type="text" id="idLien' . $indice . '"  '
                            . ' name="idLien' . $indice . '" ' 
                            . ' value="' . self::initValueIdLien($indice,$lien) . '">' ;
        $html .= '<input type="text" id="actionLien' . $indice . '" '
                            . ' name="actionLien' . $indice . '" ' 
                            . ' value="">';
        $html .=         ' </td>';
        return $html;
    }

    private static function genererLigneLienActions(int $indice){
    // actions avec leurs icones , Supprimer et télécharge
        $myhtml = '<td class="text-center">';
        $actions[] = ['texte' => 'Supprimer','logoClass' => 'bi bi-trash3-fill',
                        'onclick'=> 'jsListeSuppression(' . $indice . ',\'lien\')'];

        $myhtml .= TbModal::mettreEnFormeActions($actions,0); // ds Toolbox_modal
        $myhtml .= "</td>";
        return $myhtml;
    }
    
  
    private static function genererLigneLienTypesLiens(int $indice, array $typesLien,  $lien){
    // Affiche la combo des types de liens possibles
    // idTypeLienLien car il faut les zones + Lien à remonter dans les zones
        $myhtml= "<td>" . "<select name=\"idTypeLienLien" . $indice . "\" ";
        // si lien exprimé il est non modifiable
        if (isset($lien->id) and $lien->id > 0){$myhtml.= " disabled ";}
        $myhtml .= " id=\"idTypeLien" . $indice . "\"  class=\"form-select\" " . 
            " onchange=\"jsLienModificationType(" . $indice .")\">" .
            self::genererLigneLienInitValueListe($indice,$typesLien,$lien) .
            "</select></td>";
        return $myhtml;
    }

    private static function genererLigneLienURL(int $indice, $lien){
    // colonne caché avec Id du lien et action faite dessus apportée
    // deux input, un pour télécharger l'autre pour la saisie de l'URL
    // en mode affichage c'est l'URL qui est affichée sous forme href
        $myHtml = "<td>";

        //si url exprimée (lien existant,clicable, affiché non modifiable)
        if(isset($lien->url) && strlen($lien->url) >0){
            $myHtml .= $lien->genererLienHrefExistant();
        }
        else{
            //jsLienModificationURL ds lien.js
            //----------------- lien sous forme de texte    à saisir    
            $myHtml .= "<input disabled type=\"text\" name=\"urlLien" . $indice . "\" class=\"form-control\"" .
                    " id=\"url" . $indice. "\" value=\"\"" .
                    " oninput=\"jsLienModificationURL(" . $indice . ")\">";

            //--------------------lien choix de fichier

            $myHtml .="<input disabled type=\"file\"" .
                " name=\"fileUpload" . $indice . "\" class=\"form-control\" style=\"display:none\"" .
                " id=\"fileUpload" . $indice . "\"".
                " accept=\"video/*\" oninput=\"jsLienModificationURL(" . $indice . ")\">";

        }   
        $myHtml .= "</td>";

        return $myHtml ;
    }
    private  static function genererLigneLienDescription(int $indice,  $lien){
    // colonne description en saisie libre. Attention ne pas laisser required sinon bloque la saisie 
    // si lien existant modifiable
        $myHtml = '<td>';
        //zone de saisie de texte ou affichage du texte existant
        $myHtml .= '<input type="text" name="descriptionLien' . $indice . '" class="form-control" 
                    id="descriptionLien' . $indice. '"' ;
        if (!is_null($lien)){$myHtml.= "value=\"" . $lien->description . "\"";}
        $myHtml .= ' oninput="jsLienModification(' . $indice . ')">';
        $myHtml .= '</td>';

        return $myHtml ;
    }
    private static function genererLigneLienPrivatisation(int $indice,  $lien){
    // colonne description en saisie libre
    // pour les liens publis, seul celui qui l'a déposé peut le privatiser
        $myHtml = "<td class='text-center'>";
        //Check box
        $myHtml .= "<input class=\"form-check-input\" name=\"priveLien" . $indice . "\" type=\"checkbox\" value=\"\" id=\"priveLien" . $indice . "\"";
        if (!is_null($lien)){
            //Pour un lien existant
            if($lien->prive){$myHtml.=  " checked ";} //check si lien privé
            if(User::connectUserGetInfo('id') != $lien->user->id){$myHtml.=  " disable ";} // seul le propriétaire peut modifier le lien privé
        }
        $myHtml .= " onchange=\"jsLienModification(" . $indice . ")\">";
        $myHtml .= "</td>";

        return $myHtml ;
    }

    private static function initValueIdLien(int $indice,  $lien){
    //$lien est une instance de la classe lien
        if ($indice == lien::MODELE_LIGNE){return 0;}
        else {return $lien->id;}
    }

    private static function initValueDescriptionLien(int $indice,  $lien){
    //$lien est une instance de la classe lien
        if ($indice == lien::MODELE_LIGNE){return "";}
        else {return $lien->description;}
    }

    private static function genererLigneLienInitValueListe(int $indice , array $typesLien, $lien){
    //affiche la liste des types de liens possibles ou la liste et le lien existant
        if ($indice == lien::MODELE_LIGNE){return TbListe::valeursChoixListe($typesLien,true,"ID","nomAffiche");}
        else {return TbListe::valeursChoixListe($typesLien,true,"ID","nomAffiche", selected:$lien->typeLien->id);}
    }

    private static function afficherLiensExistants(array $infosLiens, array $typesLiens) :string {
    // type lien succession de tableau igGenre, Genre, lien
        $myHtml="";
        $indice=0;
        foreach($infosLiens as $lien){
            $indice += 1;
            $myHtml .= self::genererLigneLien($indice,$typesLiens,$lien); 
        }
        return $myHtml ;
    }
}
