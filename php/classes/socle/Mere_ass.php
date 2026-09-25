<?php
declare(strict_types=1);
/**
 * Description classe mere pour associations
 *
 * @author Alara
 */
namespace shared\php\classes\socle;

use shared\php\toolbox\Toolbox_classe as tbClasse;
use shared\php\database\Model as Model;
use shared\php\database\Model_utils as ModelU;


abstract class Mere_ass {
    // Attributs
    private int $id=0;
        
    //------------------------------------------------------------------------------------------------
    //STATIC
    //-----------------------------------------------------------------------------------------------
     public static function getListeId(int $identifiant, array $liste =[]){
    //retourne une liste de liens de l'objet en Id
        return Lien::mdGetInfoLiensIdentifiantsSeuls($identifiant, $liste,self::TABLE,self::CLE);
    }
    
    public static function getListe(int $identifiant, array $liste =[]){
    //paramètre identifiant de l'objet (song ou étab) et array la liste des types de lien...ou rien
        return Lien::mdGetInfoLiensIdentifiantsSeuls($identifiant, $liste,self::TABLE,self::CLE);
    }
    //------------------------------------------------------------------------------------------------
    //CRUD
    //-----------------------------------------------------------------------------------------------
    private function add():bool {
    //Ajoute une instance
        $retour = Model::mdInsert(self::TABLE , tbClasse::classeAssociativeValeurProprietes($this));
        //récup Id commentaire
        if ($retour){$this->id= ModelU::mdGetMax(self::TABLE);}
        
        return $retour;
    }

    public function update() : bool {
    //met à jour 
        //si pas d'id connu 
        if ($this->id === 0) {
           //sipas d'id détecté ajout
           $retour = $this->add();
        } 
        else{
            //id renseigné, pas besoin de modifier l'instance de lien
            $retour = true;
        }
        return $retour;
    }
   
    public function delete():bool {
    //supprime l'association 
        if ($this->id > 0) {
            return Model::mdDelete(self::TABLE, ['ID' => $this->id]);
        }
    }
    //
    
   //Fin de la classe
}
