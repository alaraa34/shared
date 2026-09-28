<?php
declare(strict_types=1);
namespace shared\php\bricks;
/*******************************************************************************
 * * FONCTIONS POUR SLIDER
 * $sliders = Model::mdRequeteLister( \"select * from slider where numero = ?\",[$identifiant]);?>
 *******************************************************************************/

use shared\php\toolbox\Toolbox    as Tbx;
use shared\php\database\Model     as Model;

class Brick_slider {
    // Attributs
    private int $id=0;
    private string $nom="carouselCaptions";
    private array $sliders=[];
    private string $myhtml="";
    private bool $crossfade= false;//le slider s'efface au lieu de défiler
    private bool $autoplay = false;
    private int $intervalle=5000; //intervalle de temps entre deux slides si auto play
    private bool $indicators =true ;// indique si il faut afficher la barre de défilement des slides
    private bool $controls = true;//affichage des bouttons de défilement
    private int $nbSlides = 0; //nombre de slides maxi à présenter
    // Constantes
    public const TABLE=  PREFIXE_BDD . "slides"; //table des diapos
    public const TABLES=  PREFIXE_BDD . "slider"; //table du slider

    // Méthodes
    public function __construct(int $idSlider, bool $crossfade = false,bool $autoplay = false, int $nbSlides = 4) {
        //exemple appel new TbSlider(1,crossfade:true);
        $this->id= $idSlider;
        $this->autoplay = $autoplay;
        $this->crossfade = $crossfade;
        $this->mdLister(); //chargement des slides 
        $this->nbSlides = min($nbSlides,count($this->sliders));
    }
    public function addSlider(string $titre, string $url , string $description="",string $action=""){
    //ajout du slider par un intervenant externe
        $this->sliders[] = ['titre'=> $titre,'description'=>$description,'url'=>$url,'action'=>$action];
    }
    
    public function sliderRender():string{
    // retourne le html
        $this->sliderBuildtHtml();
        return $this->myhtml;
    }
    
    private function sliderBuildtHtml():void{
    // retourne la partie nécesaire pour la construction d'un slider
        $this->sliderEntete();
        $this->myhtml .= '<!-- debut de carrousel--> <div class="slider">';
        $this->indicators();
        $this->myhtml .=  "<!-- images -->";
        $this->myhtml .= $this->renderSlides();
        $this->myhtml .='<!-- boutons latéraux -->';
        $this->paveBoutons();
        $this->myhtml .= '</div></div>';
    }
   
    private function sliderEntete() :void {
    // retourne la partie nécesaire pour la construction d'un slider
        $this->myhtml = '<div id="' . $this->nom . '" class="carousel slide';
        //les slides s'effacent au lieu de partir latéralement
        if ($this->crossfade){$this->myhtml .= ' carousel-fade ';}
        $this->myhtml .= '"';
        //autoplay, la durée est au noveau de chaque slide
        if ($this->autoplay){$this->myhtml .= ' data-bs-ride="carousel" ';}
        $this->myhtml .= '>';
    }
    private function indicators() :void{
    /*  Exemple de génération
        <div class="carousel-indicators">
        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2" aria-label="Slide 3"></button>
        </div>
     */    
        if ($this->indicators){
            $this->myhtml .= '<div class="carousel-indicators">';
            foreach ($this->sliders as $numero=>$slider) {
                $this->myhtml .= '<button ';
                $this->myhtml .= ' type="button" 
                            data-bs-target="#' . $this->nom . '" 
                            data-bs-slide-to="' . $numero . '" ';

                //indications pour premier slide
                if ($numero == 0){
                    $this->myhtml .= 'class="active" 
                                    aria-current="true" ' ;
                }
                $this->myhtml .= ' aria-label="Slide ' . $numero + 1 . '">';
                $this->myhtml .= '</button>';
            }
            $this->myhtml .= "</div>";
        }
    }
    
    private  function renderSlides(): void{
    /*Exemple de code généré
     * <div class="carousel-inner">
        <div class="carousel-item active">
          <img src="fichiers/telechargements/Acceuil/slider/I015.jpg" class="d-block w-100" alt="">
          <div class="carousel-caption d-none d-md-block">
            <h5>First slide label</h5>
            <p>Some representative placeholder content for the first slide.</p>
          </div>
        </div>*
     * ...........
     * </div>
     */
        $this->myhtml .= '<div class="carousel-inner">';
        foreach ($this->sliders as $numero=>$slider) {
            //le slide 0 par défaut est actif
            $this->myhtml .= $numero===0 ?'<div class="carousel-item active">': '<div class="carousel-item">';
            //si autoplay indication de la durée d'affichage du slide
            if ($this->autoplay){$this->myhtml .= 'data-bs-interval="'. $this->intervalle . '"';}
            $this->myhtml .= '<img src="'.  htmlspecialchars($slider["url"]). '" class="d-block w-100" alt="">';
            $this->myhtml .= '<div class="carousel-caption d-none d-md-block">';
            $this->myhtml .= '<h2 class="display-4 fw-bold">'. htmlspecialchars(Tbx::afficherdata($slider,'titre')). "</h2>";
            $this->myhtml .= '<p class="lead">'. htmlspecialchars(Tbx::afficherdata($slider,'description')) . "</p>";
            if (Tbx::afficherdata($slider,'action')){
                $this->myhtml .= '<a href="'. $slider['urlAction'] . '" class="btn btn-'. $slider['couleurAction'] . ' btn-lg">'. $slider['action'] . '</a>';
            }
            $this->myhtml .= '</div></div>';
        }
        $this->myhtml .= "</div>";
    }
    
    private function paveBoutons():void{
    /*ajoute lees pavés boutons, exemple
     <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
     */
        if ($this->controls){
            $this->myhtml .= '<button class="carousel-control-prev" type="button" data-bs-target="#' . $this->nom . '" data-bs-slide="prev">';
            $this->myhtml .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
            $this->myhtml .= '<span class="visually-hidden">Previous</span></button>';
            $this->myhtml .='<button class="carousel-control-next" type="button" data-bs-target="#' . $this->nom . '" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                    </button>';
        }
    }
    
    /*****************************************************************************************************************
     * Accès au modele
     ****************************************************************************************************************/
    
    private function mdLister(){
        $requete = "SELECT * from " . self::TABLE . " WHERE idSlider=? ORDER BY ordre";
        $this->sliders =  Model::mdRequeteLister($requete, [$this->id]);
        
    }
    
}