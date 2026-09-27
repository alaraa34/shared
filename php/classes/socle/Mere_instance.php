<?php
declare(strict_types=1);
/*******************************************************************************
 * * Classe qui contient les fonctions génériques de chargement et de mise à jour
 * Toutes les classes en hérite pour des éléments de collection
 *
 * @author Alara
 * 
 *******************************************************************************/
namespace shared\php\classes\socle;

use shared\php\database\Model            as Model;
use shared\php\database\Model_utils      as ModelU;
use shared\php\toolbox\Toolbox_classe    as TbClasse;

abstract class Mere_instance {
    //------------------------------------------------------------------------------------------------
    //VARIABLES
    //-----------------------------------------------------------------------------------------------
    public int $id=0;
    public const INCLUDE_CRUD =["commentaire","coordonnee"];    //Classes à inclure lors d'une mise à jour ajout/suppression/modif
    public const RESTREINT =[];                                 //Classes à ne pas charger lors d'un load by id
    public const SAUF =['id'];                                  //A implémenter zone à exclure pour les mises à jour, minima Id
    
    //------------------------------------------------------------------------------------------------
    //CONSTRUCTEUR  
    //-----------------------------------------------------------------------------------------------
    public function __construct(int $id=0) {
        $this->id = $id;
    }
    //------------------------------------------------------------------------------------------------
    //CRUD  
    //-----------------------------------------------------------------------------------------------
    protected function add(int $idProprietaire):bool {
    //Ajoute un enregistrement
        //insertion 
            $retour = Model::mdInsert($this::TABLE,  TbClasse::classeValeurProprietesAvecCleExterne($this,$idProprietaire));
            //récup dernier Id de la table
            if ($retour){$this->id = ModelU::mdGetMax($this::TABLE);}
            return $retour;
    }

    public function update(int $idProprietaire) : bool {
    //mise à jour de la BDD
        //la classe collection doit avoir une constante CLE_EXTERNE
        //mise à jour des classes comme commentaires qui sont liées 
        TbClasse::classeUpdateClassesLiees($this);
        
        //Mise à jour de la table classe
        if ($this->id === 0) {
            //Id = 0 c'est un ajout
           $retour = $this->add($idProprietaire);
           if (!$retour){die ("Update " . get_class($this)  . ":  Erreur add 020");}
        } 
        else{
            //id renseigné
            $retour = Model::mdUpdate($this::TABLE, TbClasse::classeValeurProprietesAvecCleExterne($this,$idProprietaire), "ID=" . $this->id);
            if (!$retour){die ("Update " . get_class($this)  . ": Erreur mise à jour 010");}
        }
        
        return $retour;
    }
    
    public function delete() : bool {
    //Suppression   
        if ($this->id > 0) {
            //Suppression de la classe et de ses liaisons include
            return TbClasse::classeDelete($this);
        }
        else{
            //Il peut y avoir un id à 0, par exemple si pas de commentaire, ce n'est donc pas une erreur
            return true;
        }
    }
    
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function loadFromId(){
    //Chargement par la fonction 
        TbClasse::classeLoadFromId($this);
    }
    public function loadFromArray(array $infos):void{
    //Charge l'instance via un tableau de données sans alias
        TbClasse::classeLoadFromArray($this,$infos);
    }
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
        
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    public function mdInfosDetail():array{
    //infos de la table de la classe
        $requete = "SELECT * FROM " . $this::TABLE. " WHERE id=?";
        return Model::mdRequeteListerUnique($requete, [$this->id]);
    }
    
       
    public static function mdGetListeChoix() {
    // Retourne la requete pour choix dans une liste ou combo
    $requete = "SELECT id as identifiant , libelle as zone
                FROM " . static::TABLE ;
    return Model::mdRequeteLister($requete);
    }
    
}
