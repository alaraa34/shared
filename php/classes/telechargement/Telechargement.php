<?php
declare(strict_types=1);
namespace shared\php\classes\telechargement;
/*******************************************************************************
 * Classe sur identifications
 ******************************************************************************/
use shared\php\toolbox\Toolbox                         as Tbx;
use shared\php\toolbox\Toolbox_classe                  as TbClasse;
use shared\php\toolbox\Toolbox_upload                  as TbUpload;
use shared\php\toolbox\Toolbox_adressage               as TbAdressage;
use shared\php\toolbox\Toolbox_liste                   as TbListe;
use shared\php\database\Model                          as Model;
use shared\php\classes\lien\Lien                       as Lien;
use shared\php\classes\lien\TypeLien                   as TypeLien;
use shared\php\classes\socle\Login                     as Login;
use shared\php\classes\socle\Commentaire               as Commentaire;
use shared\php\classes\personalisation\Nomenclature    as Nomenclature;
use shared\php\classes\personalisation\Parametre       as Parametre;
use shared\php\classes\socle\Mere                      as Mere;

class Telechargement extends Mere
{
    // Attributs
    public int $idDomaine=0;
    public string $repertoire="";
    public int $ordre=0;
    public Commentaire $commentaire;
    public Lien $lien;
    public string $wrkLibelleDomaine=""; // N'est pas une zone de la table
    
    // Constantes
    public const string TABLE=  PREFIXE_BDD . "telechargement";
    public const array INCLUDE_CRUD =["commentaire","lien"];
    public const int USAGE =77 ;  //usage pour le type de lien possible
    public const int LIEN_SUJET = 77;
    public const string REPERTOIRE = "telechargements/";
    public const string PARAMETRE_GROUPE = "TELECHARMT";  //groupe des paramèrtres téléchargement
    public const string PARAMETRE_NOM_DOMAINE = "DOMAINE"; //autorisation de créer des domaines
    


    // Méthodes
    public function __construct(int $id=0) {
       parent::__construct($id);
        $this->commentaire = new Commentaire();
        $this->lien = new Lien();
        if ($id > 0){TbClasse::classeLoadFromId($this);}
    }
    //------------------------------------------------------------------------------------------------
    //STATIC
    //---------------------------------------------------------------------;--------------------------
    public static function listeDomaines(){
        if (self::creationDomaineAutorisee()){
            //domaine en paramètre ouverts
            return Nomenclature::mdParametreGetListe(self::PARAMETRE_NOM_DOMAINE,"valeurA");
        }
        else{
            //domaine en nomenclature fermée
            return Nomenclature::mdNomenclatureGetListe(self::PARAMETRE_NOM_DOMAINE,"valeurA");
        }
    }
        
    public static function listeRepertoires(int $idDomaine =0){
    //liste les répertoires d'un domaine
        $repertoires=[];
        if ($idDomaine==0){
            $repertoires[]=['identifiant'=>0,'zone'=>"Selectionner un domaine"];
        }
        else{
            $repertoire = TbUpload::fichierPath() . self::REPERTOIRE . Nomenclature::mdNomenclatureGetDetailFromID($idDomaine);
            //création du répertoire associé au domaine
            $retour = TbUpload::uploadIsDirOrCreateIt($repertoire);
            if ($retour){
                $tableau = scandir(TbUpload::fichierPath() . self::REPERTOIRE . Nomenclature::mdNomenclatureGetDetailFromID($idDomaine) .'/');
                //mise en forme du tableau
                $repertoires[] = ['identifiant'=>'Nouveau','zone'=>'Nouveau'];
                foreach($tableau as $repertoire){
                    if ($repertoire != "."  && $repertoire != ".."){
                        $repertoires[] = ['identifiant'=>$repertoire,'zone'=>$repertoire];
                    }
                }
            }
            else {
                die("Telechargement Impossible de créer le répertoire " .$repertoire);
            }
        }
        return $repertoires;
    }
    
    private static function creationDomaineAutorisee():bool{
    //controle si la création de domaines aus autorisée  via la paramètres. Pardéfaut pas autorisé
        $valeur = Parametre::mdParametreGetDetail(self::PARAMETRE_GROUPE, self::PARAMETRE_NOM_DOMAINE, "valeurB",0);
        return $valeur===0 ? false:true;
    }
    
    public static function listeTeleDomaine(int $idDomaine){
    //renvoie une liste de tableaux de téléchargement d'un domaine
    //cette liste contient le repertoire, le raccourci le type (fichier ou URL) et le commentaire du téléchargement
        $teles = self::mdListerIDDomaine($idDomaine);
        $listes = [];
        foreach ($teles as $tele){
            $liste=[];
            $liste['id'] =  $tele['id'];
            $liste['idDomaine'] =  $idDomaine;
            $liste['repertoire'] =  $tele['repertoire'];
            //reccourci affiché pour cliquer
            $fichier = "";
            if (!$tele['externe']){$fichier= TbUpload::extraireNomFichierFromURL($tele['url']);}
            $liste['raccourci']= self::genererHtmlTagHref($tele['url'], $tele['description'],(bool)$tele['externe'], $fichier);
            
            $liste['type']= $tele['typeFichier'];//Fichier ou URL
            $liste['commentaire']= $tele['texteCommentaire'];
            $listes[]=$liste;
        }
        
        return $listes;
    }
    public static function listeTeleUnRepertoire(string $repertoire){
    //retourne les telechargements d'un répertoire
        return self::mdListerTeleUnRepertoire($repertoire);
    }
    //------------------------------------------------------------------------------------------------
    //CRUD une fois les classes mises à jour
    //-----------------------------------------------------------------------------------------------
  

    #[\Override]
    public function update() : bool {
        //Création du répertoire si existe pas , même pour les raccourcis 
        if($this->lien->typeLien->externe){$retour = $this->repertoireControle();}
        $retour = parent::update();
         
        return $retour;
    }

    #[\Override]
    public function delete():bool {
    //Delete d'après l'Id, chargement des classes liées avant
        $retour = true;
        if (is_file($this->lien->url)) {
            //suppression fichier physique
            $retour = unlink($this->lien->url);
        }
        //suppression instance
        if ($retour){
            $retour= parent::delete();
        }
        return $retour;
    }
    
    //------------------------------------------------------------------------------------------------
    //CHARGEMENT
    //-----------------------------------------------------------------------------------------------
    public function chargerLiensParMouvements(array $mvtsLiens):void{
    //Mets à jour la collection des liens sur la base des mouvements
    //ne traite pas la mise à jour physique sauf pour la suppression
        $this->lien = TbClasse::classeChargerCollectionParMouvements($this,$mvtsLiens, "shared\php\classes\lien\Lien")[0];
    }
 
    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    public static function selectBoutonRepertoire(bool $actif, array $repertoires){
    //compose le html après saisie du domaine 
    //GetTeleExistants est la fonction appelée pour constituer la liste des téléchargements exstants dans le répertoire
        $myhtml = "<select "; 
        if (!$actif) { $myhtml .= " disabled ";}
        $myhtml .= ' name="repertoire" required class="form-select" id="repertoire"';
        $myhtml .= " onchange=\"jsSaisieNouveauRepertoire('" . TbAdressage::urlControleur("telechargement","GetTeleExistants"). "')\">";
        $myhtml .= TbListe::valeursChoixListe($repertoires,true);
        $myhtml .=  "</select>";
        return $myhtml;
    }
    
    public function mettreEnFormeLiens(){
        $html="";
        foreach ($this->liens as $lien){
            $html.= $lien->genererLienHref();
        }
        return $html;
    }
    public function getOrdre():int{
    //cette fonction calcul l'ordre pour que le dernier saisi soit le dernier affiché    
        return 10;
    }
    
    private function repertoireControle(): bool{
    //calcule le répertoire complet du téléchargement  et le créée si existe pas
        $repertoire = TbUpload::fichierPath() . self::REPERTOIRE . Nomenclature::mdNomenclatureGetDetailFromID($this->idDomaine) . "/". $this->repertoire;
        return TbUpload::uploadIsDirOrCreateIt($repertoire);
    }
    
    public static function genererHtmlTagHref(string $URL, null|string $nomAffiche, bool $externe, null|string $fichier){
    //retourne <a class="$classe" href="$lien">$texte</a>
    //affiche l'a case' URL du tableau des URL pour le lien instancié
    //le texte affiche est celui saisi dans la description du lien (commentaire du lien)
    //l'URL est ce lui du champ URL du lien
         $myhtml = "<a ";
        //Affichage du tooltip
        $myhtml .= " href=\"" . $URL . "\" target='_blank' rel='noopener'>" ;
        //si texte c'est le texte qui est affiché, sinon par défaut c'est le lien en remplaçant les blancs par "_"
        if (is_null($nomAffiche) or strlen($nomAffiche)==0){
            if ($externe) {
                //url externe
                $myhtml .= $URL;
            }
            else{
                //fichier interne
                $myhtml .= $fichier;
            }
        }
        else {
            $myhtml .= $nomAffiche;
        }

        //fin
        $myhtml .= "</a>";    
        return $myhtml;
    }
    public static function getNombreDocuments(int $domaine,string$repertoire):int{
    //retourne le nombrede documents pour un domaine et un repertoire
        $requete = "select count(*) as total from " . self::TABLE . " where idDomaine = ? and repertoire = ?;";
        $tableau = Model::mdRequeteListerZoneUnique($requete, "total", array($domaine,$repertoire));
        return (int)$tableau[0];
    }
    
    //------------------------------------------------------------------------------------------------
    //RECEPTION DES CONTROLEURS
    //-----------------------------------------------------------------------------------------------
    public static function teleEditer(string $namespace,int $id = 0) {
    //edition $namespace est le NS de l'appli qui fait la demande
        $typesLien = TypeLien::listePourUnSujet(Telechargement::LIEN_SUJET); // Types de liens admissible au téléchargement
        $domaines = self::listeDomaines();
        $repertoires = self::listeRepertoires();
        $urlControleur = TbAdressage::urlControleur("telechargement","teleGetRepertoires");
        $script = Tbx::includeJS(array("ajax","lien","liste","telechargement","utils"));
        //chargement
        $tele = new Telechargement($id);
        if ($id==0){
            $messageBarreMenu = "Nouveau téléchargement";
            $afficherPlus = true;
        }
        else{
            $messageBarreMenu = "Modifier un téléchargement";
            $afficherPlus = false;
        }
        if (Login::loginControl($namespace)){
            require(ROOT_PATH . 'shared/php/classes/telechargement/tpTelechargementDetail.php');
            require(TbAdressage::projetGetLayout($namespace));
        }
    }
    
    public static function getTeleExistants(){
    //fonction qui restitue les téléchargements effectués pour un répetoire
    //parametre le nom du répertoire
        $repertoire = TbAdressage::getPost("S", "repertoire");
        $teles = Telechargement::listeTeleUnRepertoire($repertoire);
        if (count($teles)==0){
            $myhtml = "<p> Aucun téléchargement n'a été déjà fait dans ce répertoire.</p>";
        }
        else{
            //table type, URL, Raccouri
            $myhtml = "<p><strong>Téléchargement déjà faits dans ce dossier</strong></p>";
            $myhtml .= "<table width='60%' class='table table-bordered w-75 p-3' >";
            $myhtml.= "<tr>" . TbListe::formaterEntete(["Type","URL","Nom raccourci"],true);
            foreach($teles as $tele){
                $myhtml .= "<tr><td>" . $tele['typeFichier']. "</td><td>" . $tele['url']. 
                        "</td><td>" .$tele['texteCommentaire']."</td></tr>";
            }
            $myhtml .= "</table>";
        }
       return $myhtml;
    }
    
    public static function teleMAJ() {
    //mise à jour suite à ecran de saisie nouveau
        //réception des liens 
        $mvtLiens = TbAdressage::getValeurPostTableau(Lien::ZONES_MOUVEMENTS);
        //pour chaque lien création d'un telechargement
        $idDomaine = TbAdressage::getPost("I","domaine");
        foreach ($mvtLiens as $mvtLien){
            $tele = new Telechargement(TbAdressage::getPost("I","idTele"));
            if ($tele->id===0){
                //nouveau téléchargement, il peut y avoir plusieur liens
                $tele->repertoire = TbAdressage::getPost("S","repertoire");
                $tele->idDomaine = TbAdressage::getPost("I","domaine");
                $tele->chargerLiensParMouvements([$mvtLien]);
            }
            else{
                //telechargement exietant , un seul et modification libellé raccourci et privé
                $tele->lien->description = TbAdressage::getPost("S","descriptionLien1");
                $tele->lien->prive =  TbAdressage::getPost("B","priveLien1");
                $idDomaine = $tele->idDomaine;//si disabed en modif donc ne remonte pas dans post
            }
            $tele->update();
            unset($tele);
        }
        $retour = TbUpload::uploadReceptionLien(true); //ds Toolbox_upload, import des fichiers
        if (!$retour){die ("Erreur lors du téléchargemment des fichiers");}
        return $idDomaine;
    }    
    public static function teleLister(int $domaine, string $namespace) {
    //Liste une fois le répertoire sélectionné la listedes téléchargements faits sur ce domaine
        $infos = self::listeTeleDomaine($domaine);
        if (count($infos)==0){
           $content = Tbx::messageColorer(false,"","Aucun téléchargement n'a été fait pour ce domaine");
           require(TbAdressage::projetGetLayout($namespace));
        }
        else{
            $libelleDomaine = strtoupper(Nomenclature::mdNomenclatureGetDetailFromID($domaine));
            $messageBarreMenu = "Fichiers téléchargés";
            $actions = [
                ['texte'=> 'Modifier','logoClass' =>'bi bi-pencil-fill','href'=>'telechargement;Editer;tele'],
                ['texte' => 'Supprimer','logoClass' => 'bi bi-trash3-fill','modale'=>'telechargement;Supprimer;tele', 
                   'message' => 'Supprimer définitivement?'],
                   ];
            require('tpTelechargementListe.php'); 
            require(TbAdressage::projetGetLayout($namespace));
        }
      }
    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    public static function mdListerIDDomaine(int $domaine){
    // liste mles téléchargements d'un domaine   
        $requete = "select te.id ,te.repertoire ,  co.texte as texteCommentaire, li.url, li.description,
                    lt.externe,lt.nomAffiche as typeFichier
                from " . self::TABLE . " as te   INNER JOIN (" . Lien::TABLE . " as li INNER JOIN " . TypeLien::TABLE . "  as lt ON li.idTypeLien=lt.id ) 
                                            ON te.idLien  = li.id
                                            LEFT  JOIN " . Commentaire::TABLE . " as co ON te.idCommentaire=co.id
                where idDomaine=?
                order by te.repertoire,li.url";
        return Model::mdRequeteLister($requete,[$domaine]);
    }
    
    private static function mdListerTeleUnRepertoire(string $repertoire){
    // liste mles téléchargements d'un répetoire  
        $requete = "select te.id ,te.repertoire ,  co.texte as texteCommentaire, li.url, li.description,
                    lt.externe,lt.nomAffiche as typeFichier
                from " . self::TABLE . " as te   INNER JOIN (" . Lien::TABLE . " as li INNER JOIN lien_type as lt ON li.idTypeLien=lt.id ) 
                                            ON te.idLien  = li.id
                                            LEFT  JOIN " . Commentaire::TABLE . " as co ON te.idCommentaire=co.id
                where te.Repertoire=?
                order by li.url";
        return Model::mdRequeteLister($requete,[$repertoire]);
    }
   
}



