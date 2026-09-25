<?php
declare(strict_types=1);
namespace shared\php\classes\socle;
/*******************************************************************************
 * 
 *******************************************************************************/
class Commentaire extends Mere
{
    // Attributs
    public string $texte = "";
    
    // Constantes
    public const TABLE  = PREFIXE_BDD . "commentaire";
    public const INCLUDE_CRUD =[];
    
    // Méthodes
     
    //------------------------------------------------------------------------------------------------
    //CRUD
    //-----------------------------------------------------------------------------------------------
    #[\Override]
    public function update() : bool {
    //teste la configuration du commentaire
        $retour = parent::update();
        //si erreur lors de la mise à jour on force une création
        if(!$retour && $this->id > 0){
            $this->id=0;
            $retour = parent::update();
        }
        return $retour;
    }
    
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function setInfos(int $idCommentaire=0, string $texte="") :void{
        if ($idCommentaire > 0) {$this->id = $idCommentaire;}
        $this->texte = $texte;        
    }
       
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    
}



