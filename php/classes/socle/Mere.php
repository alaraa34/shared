<?php
declare(strict_types=1);
/*******************************************************************************
 * * Classe qui contient les fonctions génériques de chargement et de mise à jour
 * Toutes les classes en hérite
 *
 * @author Alara
 * 
 *******************************************************************************/
namespace shared\php\classes\socle;

use shared\php\database\Model            as Model;
use shared\php\database\Model_utils      as ModelU;
use shared\php\toolbox\Toolbox_classe    as TbClasse;

abstract class Mere {
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
    protected function add():bool {
    //Ajoute un enregistrement
        //insertion 
            $retour = Model::mdInsert($this::TABLE, $this->classeValeurProprietes());
            //récup dernier Id de la table
            if ($retour){$this->id = ModelU::mdGetMax($this::TABLE);}
            return $retour;
    }

    public function update() : bool {
    //mise à jour de la BDD
    //en transaction : classes liées, table et collections sont enregistrées ensemble ou pas du tout
        return Model::mdTransaction(function () : bool {
            //mise à jour des classes comme commentaires qui sont liées 
            TbClasse::classeUpdateClassesLiees($this);

            //Mise à jour de la table classe
            if ($this->id === 0) {
                //Id = 0 c'est un ajout
               $retour = $this->add();
               if (!$retour){throw new \RuntimeException ("Update " . get_class($this)  . ":  Erreur add 020");}
            } 
            else{
                //id renseigné, retour false si existe pas
                $retour = Model::mdUpdate($this::TABLE,  $this->classeValeurProprietes(), "id=" . $this->id);
                //if (!$retour){throw new \RuntimeException ("Update " . get_class($this)  . ": Erreur mise à jour 010, ID=".$this->id);}
            }

            //mise à jour des collections après car besoin de l'ID de la classe mère
            if($retour){$retour = TbClasse::classeUpdateCollection($this);}

            return $retour;
        });
    }
    
    public function delete() : bool {
    //Suppression   
        if ($this->id > 0) {
            //Suppression de la classe et de ses liaisons include, en transaction
            return Model::mdTransaction(fn() : bool => TbClasse::classeDelete($this));
        }
        else{
            //Il peut y avoir un id à 0, par exemple si pas de commentaire, ce n'est donc pas une erreur
            return true;
        }
    }
    
    protected function classeValeurProprietes():array{
    //pour mise à jour classe 
        $tableau = TbClasse::classeValeurProprietes($this);
        return $tableau;
    }
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function loadFromId(){
    //Chargement par la fonction 
        TbClasse::classeLoadFromId($this);
    }
    
    public function loadFromArray(array $tableau){
        TbClasse::classeLoadFromArray($this, $tableau);
    }
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    public static function buildCollectionFromIds(array $ids):array{
    //Liste la totalité des Id d'une état sous forme d'un tableau d'instances
    // à artir d'une liste d'Id
        $liste=[];
        foreach ($ids as $id){
            //nom de la classe actuelle
            $classe = get_called_class();
            $liste[] = new $classe((int)$id['id']);
        }
        return $liste;
    }
    
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    public function mdInfosDetail():array{
    //infos de la table de la classe
        $requete = "SELECT * FROM " . $this::TABLE. " WHERE id=?";
        return Model::mdRequeteListerUnique($requete, [$this->id]);
    }
    
    public function mdInfosIdCollection(string $collection, string $cle = ""):array{
    //Liste des id d'une collection. Le paramètre $collection est le nom de la classe instanciée avec son namespace
    // exemple "theBand\src\php\etablissement\suivi" ou "theBand\src\php\song\SongLien_ass
        $cleExterne = "id". TbClasse::classeGetClass($this);
        if (str_ends_with($collection, "_ass")){
            //Classe associative c'est l'identifiant lié qu'il faut trouver
            $requete = "SELECT " . $cle . " FROM " . $collection::TABLE . " WHERE " . $cleExterne ."=? " ;
            return Model::mdRequeteListerZoneUnique($requete,$cle ,[$this->id]);
        }
        else{
            //Classe d'association externe, c'est l'identifiant de l'enregistrement qu'il faut trouver
            $requete = "SELECT id FROM " . $collection::TABLE . " WHERE " . $cleExterne ."=? " ;
            return Model::mdRequeteListerZoneUnique($requete,"id" ,[$this->id]);
        }
    }
    
    public static function mdGetListeChoix() {
    // Retourne la requete pour choix dans une liste ou combo
    $requete = "SELECT id as identifiant , libelle as zone
                FROM " . static::TABLE ;
    return Model::mdRequeteLister($requete);
    }
    
}
