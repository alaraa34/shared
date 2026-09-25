<?php
declare(strict_types=1);
namespace shared\php\bricks;

/*******************************************************************************
 * Description of Brick_textbox
 * Classe qui encapsule une textbox avec mise en forme HTML possible
 *> le champ contenant le texte est "contenu" à récupérer en post
 *> l'id du formulaire contenant le textbox doit être "editorForm" ex <form method="post" id="editorForm" action="<?= TbAdressage::getURLstatic('concertMAJ','evenement')?>">

 * Dans le controlleur 
    $textbox = new BkTextbox(); +  
    $script = Tbx::includeJS(array('textbox')); et $css = Tbx::includeCSS('textbox');
 * Dans le template 
    <div class ="row justify-content">
        <div class="col-lg-11 mx-auto">
          <input type="hidden"  name="idCommentaire" id="idCommentaire" value="<?= $exercice->commentaire->id ?>" />
          <!-- DEBUT classe textbox --> 
          <?= $textbox->render();?>
          <!-- FIN classe textbox --> 
        </div>
    </div>
 * Utiliser setTexte pour initialiser avec un texte existant
 ******************************************************************************/

use shared\php\toolbox\Toolbox_adressage as TbAdressage;

class Brick_textbox {
    private bool $infos = false;
    private string $html="";
    private string $texte="";
    private array $toolbars=[]; //palette des outils demandés
    private string $texteDefaut = self::TEXTE_DEFAUT; 
    private bool $afficherToolbar=true;
        
    //constantes
    public const ELEMENT_HEADINGS =1;
    public const ELEMENT_FONT_FAMILY =2;
    public const ELEMENT_FONT_SIZE =3;
    public const ELEMENT_TOGGLE =4;
    public const ELEMENT_TYPE_POLICE =5;
    public const ELEMENT_ALIGNEMENT =6;
    public const ELEMENT_BULLET_POINT =7;
    public const ELEMENT_CARD_BODY =8;
    public const ELEMENT_LIENS =9;
    public const ELEMENT_ICON =10;
    public const ELEMENT_UNDO =11;
    public const ELEMENT_COPY_PASTE =12;
    public const ELEMENT_COLOR =13;
    public const string TEXTE_DEFAUT ="<p>Commencez à écrire ici...</p>"; 
    
      
    
     // Méthodes
    public function __construct(bool $infos = false, bool $standard=true) {
    //exemple appel new TbSlider(1,crossfade:true);
        $this->infos = $infos;
        if ($standard){
            $this->addtoolbar(self::ELEMENT_HEADINGS);
            $this->addtoolbar(self::ELEMENT_ALIGNEMENT);
            $this->addToolbar(self::ELEMENT_TYPE_POLICE);
            $this->addToolbar(self::ELEMENT_BULLET_POINT);
            $this->addToolbar(self::ELEMENT_LIENS);
            $this->addToolbar(self::ELEMENT_UNDO);
       }
    }
    
    public function setTexte(string $texte){
    //initialise avec un contenu si bessoin
        if (strlen($texte) > 0){
            $this->texte = $texte;
        }
    }
    
    public function setTexteDefaut(string $texte){
    //initialise le texte par défaut avec  un contenu différent du standard si besoin
        $this->texteDefaut = $texte;
    }
    
    public static function getTexte(string $nom="contenu"){
    //initialise avec un contenu si bessoin
        $texte = TbAdressage::getPost("S", $nom);
        return $texte== self::TEXTE_DEFAUT ? "" : $texte;
    }
    
        
    public function addToolbar(int $outil){
    //ajout d'un outil dans une palette
        $this->toolbars[] = $outil;
    }
   
    public function renderComplete(int $idCommentaire, string $texte="",bool $toolbars=true){
    //renvoie un texte complet avec un row
        return  '  <div class ="row ">
                        <div class="col-lg-12 mx-auto">' .
                            $this->renderCompleteSansRow($idCommentaire,$texte,$toolbars).
                        '</div>
                    </div>';
    }
    public function renderCompleteSansRow(int $idCommentaire, string $texte="",bool $toolbars=true){
    //renvoie un texte complet avec un row
        return  '<!-- DEBUT classe textbox -->
                <input type="hidden"  name="idCommentaire" id="idCommentaire" value="' . $idCommentaire . '" />' .
                $this->render($texte, $toolbars ) .
                '<!-- FIN classe textbox -->';
    }
    public function render(string $texte,bool $toolbars=true){
    //restitue le code html
        $this->afficherToolbar = $toolbars;
        $this->setTexte($texte);
        $this->html = '<div class="w-100">';
        $this->html .= $this->afficherToolbar ? $this->renderToolbars():"";
        $this->html .= $this->renderZoneEdition();
        $this->html .= '</div>';
        return $this->html;
    }
    
    private function renderColor():string{
        return ' <div class="color-picker-wrapper">
                    <button type="button" class="btn toolbar-btn" title="Couleur du texte">
                        <i class="bi bi-palette"></i>
                    </button>
                    <input type="color" id="textColor" value="#000000">
                </div>';
    }
    private function renderZoneEdition(){
        $variable = $this->afficherToolbar ? "true" : "false" ;
        $html = '<!-- Champ caché pour le contenu -->
                <div id="editor" contenteditable="'. $variable. '"  spellcheck="true">';
        $html .= strlen($this->texte)===0 ? $this->texteDefaut : $this->texte;
        $html .= '</div>
                <!-- Champ caché pour le contenu -->
                <input type="hidden" name="contenu" id="contenu">';
        return $html;
    }
    private function renderInfos() :string{
        return '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Raccourcis : <kbd>Ctrl+B</kbd> Gras, <kbd>Ctrl+I</kbd> Italique, <kbd>Ctrl+U</kbd> Souligné
                    </small>
                </div>';
    }
   
    private function renderToolbars(){
        $this->html .= '<div class="border rounded-top p-2 bg-light d-flex flex-wrap align-items-center gap-1">';
        foreach ($this->toolbars as $index=>$toolbar){
            $this->html .= $this->renderToolBar($toolbar);
            if($index < count($this->toolbars)-1){
            $this->html .= '<div class="toolbar-separator"></div>';}
        }
        if ($this->infos){$this->html .= $this->renderInfos();}
        $this->html .= '</div>';
    }
    private function renderHeadings(){
        return '<select class="form-select form-select-sm" style="width: auto;" id="formatBlock">
                    <option value="">Paragraph</option>
                    <option value="h1">Heading 1</option>
                    <option value="h2">Heading 2</option>
                    <option value="h3">Heading 3</option>
                    <option value="h4">Heading 4</option>
                    <option value="h5">Heading 5</option>
                    <option value="h6">Heading 6</option>
                </select>';
    }
    private function renderToggle(){
        return '<button class="btn btn-outline-secondary" data-command="toggleSource" title="Toggle Source">
                    <i class="bi bi-code-slash"></i>
                </button>';
    }
    
    private function renderFontFamily(){
        return '<select id="fontFamilySelect" class="form-select" style="width:auto;">
                    <option value="" disabled>Font Family</option>
		</select>';
    }
    
    private function renderFontSize(){
        return '<select id="fontSizeSelect" class="form-select" style="width:auto;">
			<option value="" disabled>Font Size</option>
                </select>';
    }
    
    private function renderTypePolice():string{
        return '<div class="btn-group">
                    <button type="button" class="btn toolbar-btn" data-command="bold" title="Gras (Ctrl+B)">
			<i class="bi bi-type-bold"></i>
                    </button>
		
                    <button type="button" class="btn toolbar-btn" data-command="italic" title="Italique (Ctrl+I)">
			<i class="bi bi-type-italic"></i>
                    </button>
		
                    <button type="button" class="btn toolbar-btn" data-command="underline" title="Souligné (Ctrl+U)">
			<i class="bi bi-type-underline"></i>
                    </button>
                    
                    <button type="button" class="btn toolbar-btn" data-command="strikeThrough" title="Barré">
			<i class="bi bi-type-strikethrough"></i>
                    </button>
                </div>';
    }
    private function renderAlignement(){
        return '<div class="btn-group">
                    <button type="button" class="btn toolbar-btn" data-command="justifyLeft" title="Aligner à gauche">
			<i class="bi bi-text-left"></i>
                    </button>
                    <button type="button" class="btn toolbar-btn" data-command="justifyCenter" title="Centrer">
			<i class="bi bi-text-center"></i>
                    </button>
                    <button type="button" class="btn toolbar-btn" data-command="justifyRight" title="Aligner à droite">
			<i class="bi bi-text-right"></i>
                    </button>
                </div>';
    }
    private function renderBulletPoint(){
        return '<div class="btn-group">
                    <button type="button" class="btn toolbar-btn" data-command="insertUnorderedList" title="Liste à puces">
			<i class="bi bi-list-ul"></i>
                    </button>
                    <button type="button" class="btn toolbar-btn" data-command="insertOrderedList" title="Liste numérotée">
			<i class="bi bi-list-ol"></i>
                    </button>
                </div>';
    }
    private function renderCard(){
        return '<div class="card-body">
                    <div id="editor" class="form-control" contenteditable="true" style="min-height: 100px; max-height:700px; overflow-y: auto;">
                    </div>
                    <textarea id="sourceView" class="form-control d-none" style=" font-family: monospace;"></textarea>
                </div>';
    }
    Private function renderLiens(){
        return	'<div class="btn-group">
                    <button type="button" class="btn toolbar-btn" id="createLink" title="Insérer un lien">
                            <i class="bi bi-link-45deg"></i>
                    </button>
                </div>';
    }
    
    private function renderIcon(){
        return '<div class="btn-group">
                    <button class="btn btn-outline-secondary bi bi-emoji-smile" data-command="insertEmoji" title="Insert Emoji"></button>
                    <button class="btn btn-outline-secondary bi bi-hash" data-command="insertSymbol" title="Insert Symbol"></button>
                    <button class="btn btn-outline-secondary bi bi-palette" data-command="insertColor" title="Text Color"></button>
                </div>';
    }
    
    private function renderUndo(){
    //bouton de suppression du contenu
        $myhtml=  '<div class="btn-group">';
        //$myhtml .= '<button type="button" class="btn toolbar-btn" data-command="removeFormat" title="Supprimer le formatage">
	//		<i class="bi bi-eraser"></i>
        //            </button>';
        $myhtml .= ' <button type="button" class="btn toolbar-btn" id="clearEditor" title="Tout effacer">
                        <i class="bi bi-trash"></i>
                    </button>';
        $myhtml .= '</div>';
        return $myhtml;
    }
    
    private function renderCopyPaste(){
        return '<div class="btn-group">
                    <button class="btn btn-outline-secondary" data-command="cut" title="Cut"><i class="bi bi-scissors"></i></button>
                    <button class="btn btn-outline-secondary" data-command="copy" title="Copy"><i class="bi bi-files"></i></button>
                    <button class="btn btn-outline-secondary" data-command="paste" title="Paste"><i class="bi bi-clipboard"></i></button>
                </div>';
    }
    
    private function renderToolbar(int $element){
    
            switch ($element){
                case self::ELEMENT_HEADINGS :
                    $this->html .= $this->renderHeadings();
                    break;
                case self::ELEMENT_FONT_SIZE :
                    $this->html .= $this->renderFontSize();
                    break;
                case self::ELEMENT_FONT_FAMILY :
                    $this->html .= $this->renderFontFamily();
                    break;
                case self::ELEMENT_TYPE_POLICE :
                    $this->html .= $this->renderTypePolice();
                    break;
                case self::ELEMENT_ALIGNEMENT :
                    $this->html .= $this->renderAlignement();
                    break;
                case self::ELEMENT_BULLET_POINT :
                    $this->html .= $this->renderBulletPoint();
                    break;
                case self::ELEMENT_CARD_BODY :
                    $this->html .= $this->renderCard();
                    break;
                case self::ELEMENT_LIENS :
                    $this->html .= $this->renderLiens();
                    break;
                case self::ELEMENT_ICON :
                   $this->html .= $this->renderIcon();
                   break;
                case self::ELEMENT_UNDO :
                   $this->html .= $this->renderUndo();
                   break;
                case self::ELEMENT_COPY_PASTE :
                   $this->html .= $this->renderCopyPaste();
                   break;
                case self::ELEMENT_TOGGLE :
                   $this->html .= $this->renderToggle();
                   break;
                case self::ELEMENT_COLOR :
                   $this->html .= $this->renderColor();
                   break;
        }
    }
}
