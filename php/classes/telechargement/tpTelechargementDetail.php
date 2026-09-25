<?php 
declare(strict_types=1);
namespace shared\php\classes\telechargement;
/*Page de saisie des téléchargements
NOUVEAU > la liste des domaines est alimentée par tous les domaines. 
    Sur le change on appelle le controleur ctGetRepertoires qui alimente le controle (id=)listeRepertoires 
    par la liste des répertoires du domaine,
    Sur le change de ce dernier,  le js jsSaisieNouveauRepertoire est appelé pour soit mettre in input texte si saisie de nouveau soit 
    afficher la liste des téléchargements faits sur le répertoire choisi (action GetTeleExistants définie dans telechargement::selectBoutonRepertoire
ANCIEN seules valeurs libellé du raccourci et pr
 * */
use shared\php\toolbox\Toolbox_liste as TbListe;
use shared\php\toolbox\Toolbox_html as TbHtml;
use shared\php\classes\lien\Lienhtml as Lienhtml;
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

ob_start()
?>
<br><br>
<form enctype="multipart/form-data" method="post" action="<?= TbAdressage::getURLstatic('telechargement','teleMAJ')?>">
    <section class="py-5">
        <div class="container">
            <!--Identifiants  -->   
            <input type="hidden"  name="idTele" id="idTele" value="<?= $tele->id?>"/>
            <h2>Téléchargement de fichiers sur le site </h2>
            <br>
            <!--Dossier de rangement -->  
            <div class="row">
                <div class = "col-4">
                    <div class="input-group mb-3">
                          <label class="input-group-text" for="domaine">Domaine*</label>
                          <select name="domaine" required class="form-select" id="domaine" <?php if($tele->id>0){echo(" disabled (");} ?>
                                  onchange="<?= "Get_Form_zone('" . $urlControleur ."','listeRepertoires','domaine')"?>">
                                <?= TbListe::valeursChoixListe($domaines,true, selected:$tele->idDomaine); ?>
                          </select>
                    </div>
                </div>
                <div class = "col-4">
                    <div class="input-group mb-4">
                          <label class="input-group-text" for="typeLien">Répertoire*</label>
                          <div id="listeRepertoires"><?= selectBoutonRepertoire(false,$repertoires,$tele);?></div>
                    </div>
                </div>
            </div>
          
            <!-- Liens ds p--> 
            <?= afficherBlocLienUnique ($typesLien,$tele,$afficherPlus);?>
        </section>
   
    <!-- Soumission formulaire -->
    <?= TbHtml::htmlBoutonValidation()?>
    
    <!-- liste des téléchargements existants mis à jour au choix du répertoire-->
    <div id ="listeTelechargementRepertoire" /></div>
    <?= TbHtml::htmlMentionVolumeTelechargement(" Ils sont tous placés dans le même répertoire.");?>
</form>
<?php
    $content = ob_get_clean(); 
 
function selectBoutonRepertoire(bool $actif, array $repertoires,  $tele){
//cree la liste de selection des répertoires sur la fenêtre de saisie
//Crée une selection non modifiable en affoichage d'un téléchargement existant
    if ($tele->id==0){
        //Composition si identifiant repertoire connu
        $myhtml = $tele::selectBoutonRepertoire($actif, $repertoires);
    }
    else{
        //Grisé si telechargement existant avec le nom du répertoire
        $myhtml = '<input type="text" class="form-control" disabled  value="' . $tele->repertoire . '">';
    }
    return $myhtml;
}

function afficherBlocLienUnique($typesLien,$tele,$afficherPlus){
//gère le fait que le lien de telechargement n'est pas une collection
    if (strlen($tele->lien->url)==0){
        $liens = [];
    }
    else{
        $liens = [$tele->lien];
    }
    return Lienhtml::afficherBlocLien ($typesLien,$liens,$afficherPlus);
}