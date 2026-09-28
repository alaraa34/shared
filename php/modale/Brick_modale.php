<?php
declare(strict_types=1);
namespace shared\php\modale;

/*******************************************************************************
 * Description of Blick_modale
 ******************************************************************************/

use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
use shared\php\modale\Toolbox_modal         as TbModal;

class Brick_modale {
    private array $action=[];
    private string $formModale="";
     
    public function __construct(bool $standard = true) {
    //exemple appel new TbSlider(1,crossfade:true);
        
    }
    public function render(){
        $mythml = '<div class="modal fade" id="'. TbModal::modalGetNomDiv($this->actionModale). '" data-bs-backdrop="static" 
                data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true"> ';
        $mythml .= '<div class="modal-dialog">
                <div class="modal-content">';
        if (isset($this->action['texte'])){ 
            $mythml .= ' <div class="modal-header">
                        <h1 class="modal-title fs-5" id="staticBackdropLabel">' . $$this->action['texte'] . '</h1>
                    </div>';
        }
        $mythml .= '<!-- formulaire modal -->';

        $mythml .= '<!-- fin formulaire modal -->';
        $mythml .=' </div>
            </div>
        </div>';
        return $html;
    }
    private function renderModaleStandard(){
        $html = "";

        $html .= '<form method="POST" action="' . TbAdressage::getURLControleurFromCFPAdress($this->action['modale']). '">';
        $html .=  '<div class="modal-body">';
        $html .=  '<p' . $this->action['message'] . '</p>'; 
        // <!-- Nom de la modale -->
        $html .= '<input type="hidden" name="nomModale" value="' .$this->action['modale']. '"/>';
        // <!-- bouton identifiant -->
        $html .='<input type="hidden" id="'. TbModal::modalNomInputIdentifiant($action['modale']) .
                       ' name="' . TbModal::modalNomInputIdentifiant($action['modale']). '" value="-1"/>';
         $html .= '</div>
            <div class="modal-footer"> 
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non</button>
                <button type="submit" class="btn btn-primary">Oui</button>
            </div>
        </form>';
        return $html;
    }
}