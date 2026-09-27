<!--Modale standard -->
<?php 
      use shared\php\modale\Toolbox_modal         as TbModal;
      use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
?>
<div class="modal fade" id="<?= TbModal::modalGetNomDiv($action['modale'])?>" data-bs-backdrop="static" 
        data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true"> 
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="staticBackdropLabel"><?=$action['texte'];?></h1>
            </div>
            <!-- formulaire modal -->
            <form method="POST" action="<?= TbAdressage::getURLControleurFromCFPAdress($action['modale'])?>"> 
                <div class="modal-body">
                    <p><?=$action['message']?> </p> 
                    <!-- Nom de la modale -->
                    <input type="hidden" name="nomModale" value="<?=$action['modale']?>"/>
                     <!-- bouton identifiant -->
                     <input type="hidden" id="<?= TbModal::modalNomInputIdentifiant($action['modale'])?>" 
                           name="<?=TbModal::modalNomInputIdentifiant($action['modale'])?>" value="-1"/>
                </div>
                <div class="modal-footer"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non</button>
                    <button type="submit" class="btn btn-primary">Oui</button>
                </div>
            </form>
            <!-- fin formulaire modal -->
        </div>
    </div>
</div>


