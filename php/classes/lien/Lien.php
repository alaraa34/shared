<?php
declare(strict_types=1);
/*******************************************************************************
 * lien interne (fichier) ou externe(url du net)
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\database\Model               as Model;
use shared\php\database\Model_utils         as ModelU;
use shared\php\classes\socle\User           as User;
use shared\php\toolbox\Toolbox_classe       as TbClasse;
use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
use shared\php\toolbox\Toolbox_upload       as TbUpload;

class Lien
{
    // Attributs
    public int $id=0;
    public string $url="";
    public string $description="";
    public TypeLien $typeLien;
    public User $user;
    public bool $prive = false;    
    public int $wrkPoste =0;
    
    public const TABLE = PREFIXE_BDD . "lien";
    public const MODELE_LIGNE = 99; //identifiant du modele de ligne sur html lien
    public const ZONES_MOUVEMENTS = ['idLien&I','actionLien&S','idTypeLienLien&I' ,'urlLien&S',
                                            'descriptionLien&S','priveLien&C'];
    public const SAUF =['id'];                                  //A implémenter zone à exclure pour les mises à jour, minima Id
           
    // Méthodes
    public function __construct(int $id=0) {
        $this->typeLien = new TypeLien();
        $this->user = new User();
        if ($id > 0){
            $this->id = $id;
            TbClasse::classeLoadFromId($this);
        }
    }
    
    //------------------------------------------------------------------------------------------------
    //Méthodes statiques publiques
    //-----------------------------------------------------------------------------------------------
    public static function chargerLiensParMouvements(object $classeMere):array{
    //Mets à jour la collection des liens sur la base des mouvements
    
        $mvtsLiens = TbAdressage::getValeurPostTableau(self::ZONES_MOUVEMENTS);
        return self::chargerCollectionAvecUrl($classeMere, $mvtsLiens);
    }

    public static function chargerCollectionAvecUrl(object $classeMere, array $mvtsLiens):array{
    //charge la collection des liens d'après les mouvements et calcule l'URL des fichiers envoyés
    //(dossier du type de lien + nom du fichier) : à utiliser par toutes les classes qui ont des liens
        $liens = TbClasse::classeChargerCollectionParMouvements($classeMere, $mvtsLiens, self::class);
        foreach($liens as $index=>$lien){
            if (strlen($lien->url)==0){
                //lien vers un fichier : l'URL n'est pas saisie, elle est calculée
                $liens[$index]->lienExterne();
            }
        }
        return $liens;
    }

    //------------------------------------------------------------------------------------------------
    //CRUD
    //-----------------------------------------------------------------------------------------------
    private function add():bool {
    //Ajoute un lien
        $tableau = TbClasse::classeValeurProprietes($this);
        $tableau['idUser'] = User::connectUserGetInfo('id');
        $retour = Model::mdInsert(self::TABLE, $tableau);
        //récup Id 
        if ($retour){$this->id = ModelU::mdGetMax(self::TABLE);}
        return $retour;
    }

    public function update() : bool {
    //met à jour ou ajoute  un lien
        //id renseigné
        if ($this->id ===0){
            $retour = $this->add();
        }
        else{
            //Mise à jour seulement sur prive et description 
            $retour = Model::mdUpdate(self::TABLE, ['description'=>$this->description,'prive'=>$this->prive], "id=" . $this->id);
         }
        return $retour;
        
    }

    public function delete():bool {
        if ($this->id == 0) {
            $retour = true;
        }
        else{
            $retour = true;
            //Suppression fichier si lien externe
            if (!$this->typeLien->externe && $retour){
                $retour = TbUpload::uploadDeleteFile($this->url);
            }
            //suppression du lien
            if ($retour){$retour = Model::mdDelete(self::TABLE, ['id' => $this->id]);}
    
        }
        return $retour;
    }
    
    public function addAssociation(int $idMere):bool{
        //Créer l'enregistrement association
        return Model::mdInsert( static::TABLE, [static::CLE=>$idMere,'idLien'=>$this->id]);
    }
    
      
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function lienExterne(): void{
    //traite le cas où fichier externe. 
        //Controle préalable du chargement des données du lien pas seulement de l'ID
        if($this->typeLien->id >0 && strlen($this->typeLien->nom)==0){
            $this->typeLien->loadFromId();
        }
        //Affectation de l'URL si externe
        if (!$this->typeLien->externe){
            //si issu d'un telechargement
            if (TbAdressage::getPost("I","domaine") >0){
                //ds Toolbox_upload, à partir domaine et repertoire du téléchagement
                $repertoire = TbUpload::repertoireTelechargement();
            }
            else{
                //sinon répertoire lié au type de lien,ds Toolbox_upload, à partir domaine et repertoire du téléchagement
                $repertoire = TbUpload::repertoireHorsTelechargement($this->typeLien->repertoire,$this->wrkPoste);
            }
            $this->url =  $repertoire . "/" . $this->lienGetNomFichier($this->wrkPoste); //URL
        }
    }
    
    private function lienGetNomFichier(int $poste): string {
        // Retourne le nom du fichier importé correspondant au poste
        if (isset($_FILES["fileUpload" . $poste])) {
            return $_FILES["fileUpload" . $poste]['name'];
        } else {
            return "fichier non trouvé";
        }
    }
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
              
    public function getTooltip():string{
    //Texte qui apparait en tooltip des liens
        $retour = strlen($this->typeLien->nomAffiche)===0 ? "": "(" .$this->typeLien->nomAffiche .")";
     
        if (strlen($this->description) > 0){
            $retour.= " " . $this->description;
        }
        return $retour;
    }
    
    public function genererLienHref(){
    //retourne <a class="$classe" href="$lien">$texte</a>
    //affiche la case URL du tableau des URL pour le lien uinstancié
        $myhtml = '<a  ';
         //si classe 
        if (TbClasse::classeAttributValorise($this->typeLien,"couleur")) {
            $myhtml.= 'class="' . $this->typeLien->couleur . '"';
        } 
        
        //Affichage du tooltip
        $myhtml .= " title=\"" . $this->getTooltip() . "\" " ;

        $myhtml .= " href=\"" . $this->url . "\" target='_blank' rel='noopener'>" ;
        
        //si texte c'est le texte qui est affiché, sinon par défaut c'est le lien en remplaçant les blancs par "_"
        $myhtml .= '<h6>&nbsp;' . $this->typeLien->icone . '&nbsp;</h6>';
  
        //fin
        $myhtml .= "</a>";    
        return $myhtml;
    }
    
    public function genererLienHrefExistant(){
    //retourne <a class="$classe" href="$lien">$texte</a>
    //affiche la case URL du tableau des URL pour le lien uinstancié
         $myhtml = "<a ";
        //Affichage du tooltip
        $myhtml .= " title=\"" . $this->getTooltip() . "\" " ;

        $myhtml .= " href=\"" . $this->url . "\" target='_blank' rel='noopener'>" ;
        //si texte c'est le texte qui est affiché, sinon par défaut c'est le lien en remplaçant les blancs par "_"
        $myhtml .= $this->url;

        //fin
        $myhtml .= "</a>";    
        return $myhtml;
    }
    
    private static function wherePrive(){
    // regle de gestion pour les liens privés, par défaut restitution du privé, sinon le public
    // user non connecté seulement le public
        if (User::connectUserGetInfo('id',0)===0){
            return " prive = false ";
        }
        else{
            return "(prive = false OR (prive=true AND idUser=". User::connectUserGetInfo('id') . "))";
        }
    }
    
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    
        
    public static function mdGetInfoLiensIdentifiantsSeuls(int $identifiant, array $liste, string $tableAss, string $cle):array{
    //Retourne les liens issus d'une  d'après l'identifiant
    //soit retourne tous les liens (par défaut), soit ceux cités dans le tableau $liste qui contient les ID
    //ex tableAss = song_lien $cle = idSong
        $requete = "SELECT lien.id as id
                        FROM " . self::TABLE . " as lien
                                INNER join " . $tableAss . " as tableAss ON lien.id = tableAss.idLien" .
                        " WHERE tableAss." . $cle . "=? AND " . self::wherePrive();
        //Ajout du test sur la liste
        if (count($liste) > 0){$requete .= " AND lien.idTypeLien " . Model::mdClauseIn($liste);}
        return Model::mdRequeteLister($requete,[$identifiant]);
    }
       
    public function mdInfosDetail():array{
    //infos de la table de la classe
        $requete = "SELECT * FROM " . static::TABLE. " WHERE id=?";
        return Model::mdRequeteListerUnique($requete, [$this->id]);
    }
}