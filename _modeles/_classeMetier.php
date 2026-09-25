<?php
declare(strict_types=1);
namespace vals\src\php\tarif;

/*******************************************************************************
 * Description de la classe
 ******************************************************************************/

//Use des classes nécessaires (supprimer cells qui ne servent pas)
use shared\php\database\Model as Model;

use shared\php\classes\socle\Commentaire as Commentaire;
use shared\php\classes\socle\Mere as Mere;
use shared\php\classes\lien\TypeLien as TypeLien;
use shared\src\php\socle\User as User;
use shared\php\classes\socle\Login as Login;

use shared\php\toolbox\Toolbox_classe as TbClasse;
use shared\php\modale\Toolbox_modal as TbModal;
use shared\php\toolbox\Toolbox as Tbx;
use shared\php\toolbox\Toolbox_liste as TbListe;
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

use shared\php\bricks\Brick_table as BkTable;


class _classeMetier extends Mere 
{
    // Attributs : id dans la classe mère
    // Tous les attributs sauf classe sont initialisés, les collections sont à []
    // quand c'est une classe la variable est le nom de la classe en minuscule
    // constantes de la classe mère à surcharger si différent
  
    public Commentaire $commentaire;
    public array $saisons=[];                           //collection de saisons
    public array $prestations=[];                       //collection de prestations
  
    // Constantes
    public const TABLE  =  PREFIXE_BDD . "ma_table";               //nom de la table
    //public const INCLUDE_CRUD =["commentaire","coordonnee"];    //Classes à inclure lors d'une mise à jour
    //public const RESTREINT =[];                                 //Classes à ne pas charger lors d'un load by id
    
    //------------------------------------------------------------------------------------------------
    //CONSTRUCTEUR
    //-----------------------------------------------------------------------------------------------
    public function __construct(int $id = 0) {
        
    }
    //------------------------------------------------------------------------------------------------
    //FONCTIONS STATIQUES
    //-----------------------------------------------------------------------------------------------
    
    //------------------------------------------------------------------------------------------------
    //CRUD une fois les classes mises à jour
    //-----------------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    
    //------------------------------------------------------------------------------------------------
    //TECHNIQUE
    //-----------------------------------------------------------------------------------------------
    public function getNamespaceCollection(string $nomClasseCollection):string{
    //appelé par classeLpoadID pour trouver la liste des ID de la collection 
    //renvoie le namespace completde la classe de collection 
    //par défaut celui de la classe si dans le même name space
    //NAMESPACE dans la classe mere renvoi shared et pas la classe fille
        switch ($nomClasseCollection) {
            case "collection1":
                return "namespece";
            default:
                return  __NAMESPACE__ .'\\' . $nomClasseCollection; 
        }
    }
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
       
    
    //------------------------------------------------------------------------------------------------
    //ACCES AU MODELE
    //-----------------------------------------------------------------------------------------------
   
    
}