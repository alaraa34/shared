<?php
declare(strict_types=1);
namespace shared\php\bricks;

/*******************************************************************************
 * Description of Brick_popover
 *
 * @author Alara
 */
use shared\php\toolbox\Toolbox    as Tbx;

class Brick_popover {
    private string $titre="";
    private string $texte="";
    private string $position = "";
    
     // Méthodes
    public function __construct(string $texte, string $titre = "", string $position = "right") {
    //exemple appel new TbSlider(1,crossfade:true);
        $this->titre = $titre;
        $this->texte = $texte;
        $this->position = $position;
    }
    
    public static function INCLUDE_JS(){
        Tbx::includeJS('popover');
    }
    
     
    //put your code here
    public function renderLink( string $texteLink){
    //affiche un pop over sous forme de lien
        return '<a href="#" data-bs-toggle="popover" 
                   title="' . $this->titre . '" 
                   data-bs-content="' . $this->texte . 
                    '">' . $texteLink . 
                '</a>';
    }
    
     
    public function renderButton(string $titreBouton, string $taille="", string $bsColor =""){
        return '<button type="button" class="btn '. $taille . ' ' . $bsColor . '"' .
                'data-bs-toggle="popover" 
                data-bs-placement="' . $this->position . '"
                title="' . $this->titre . '" 
                data-bs-content="' . $this->texte . '">' . 
                $titreBouton . 
                '</button>';
    }
}
