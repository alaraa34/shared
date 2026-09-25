<?php
declare(strict_types=1);
namespace shared\php\bricks;
/*******************************************************************************
 * Description of Brick_table
// Utilisation de la classe
$table = new BTable();
$table->setHeaders(['Nom', 'Âge', 'Ville']);
$table->addRow(['Alice', 30, 'Paris']);
// Afficher la table
echo $table->render();
 * @author araib
 * table-hover table-bordered table-sm
 *  <td class="align-baseline">haut gauche</td>
      <td class="align-top">haut</td>
      <td class="align-middle">millieu</td>
      <td class="align-bottom">bas</td>
      <td class="align-text-top">text-top</td>
      <td class="align-text-bottom">text-bottom</td>
class="text-start">Start aligned text on all viewport sizes.</p>
<p class="text-center">Center aligned text on all viewport sizes.</p>
<p class="text-end
 * Pour désigner la colonne qui recevra les numérotations il faut mettre COLONNE_NUMERO dans l'intitulé du header
 * Par défaut toutes les lignes sont numérotés, sauf voir dessous
 * c'est la présente de colonne_numéro dans les entetes qui déclenche la présence de la numérotation, la numérotation est mise dans cette colonne
 ******************************************************************************/

class Brick_table {
    private string $id="";
    private array $headers = [];
    private array $rows = [];
    private $tableClass;  //classe d'apparence
    private bool $responsive; //oui ou non afficher un N° de ligne
    private array $numerotation=[]; //[]vide pas de numerotation,[0] numerotation, [1,-2,] pas de numero pour la première et les deux dernièresdernière
    private int $colonneNumerotation=0; //alimenté par lecture header
    private const SEPARATOR = "££";
    public const COLONNE_NUMERO = "#";
   
  
      

    public function __construct(string $id = 'liste' ,string $tableClass = 'table-striped', bool $responsive = true) {
    //__construct($tableClass) : Constructeur qui initialise la classe avec une classe CSS pour la table.
        $this->tableClass = 'table  ' .  $tableClass;
        $this->id = $id;
        $this->responsive=$responsive;
    }

      
    public function addHeader(string $texte, string $classe='',string $alignement = 'MC',
                                        int $colspan=1,int $rowspan=1, int $rang=1) {
    //addHeaders(array $headers) : Méthode pour ajouter une colonne d'entête
    //le rang est le niveu de ligne de titres , géré directement au niveau de l'affectattion, pas besoin de le créer
        //analyse colonne numerotation
        
        $element = $this->codeCell($texte,$classe,$alignement,$colspan,$rowspan,false);
        if(str_contains($texte, self::COLONNE_NUMERO)){
            $this->headers[$rang][] = self::COLONNE_NUMERO;
            //formatte un tableau avec le séparateur ";" en remplaçant COLONNE_NUMERO par 0 pour alimenter numerotation
            //enregistrement du mode de numerotation 
            $this->numerotation = explode(";", str_replace(self::COLONNE_NUMERO,"0",$texte)); //remplece # par 0 et explode
            //Enregistrement de la colonne qui contiendra la numerotation
            $this->colonneNumerotation = count($this->headers[$rang])- 1;
        }
        else{
            $this->headers[$rang][] = $element;
        }
    }
    
    public function addHeaders(array $tableau, int $rang=1): void {
    //ajoute un tableau d'entête dans sa globalité
    //appel à la classe unitaire poura les options par défaut
    //alignement milieu centé par défaut
        foreach ($tableau as $poste){	
            $this->addHeader((string)$poste,rang:$rang);
        }
    }

    public function addRow(array $row) :void {
    //ajoute un rang entier
        $this->rows[] = $row;
    }
    
    public function addCell($texte, string $classe="", string $alignement = "M", bool $nouveau = false,int $colspan=1,int $rowspan=1, bool $hidden = false) :void{
    //ajoute un rang entierune valeur au dernier rang créé ou en rajoute un
        $contenu = $this->codeCell($texte,$classe,$alignement,$colspan,$rowspan,$hidden);
        if ($nouveau){
            //ajout d'un nouveau row avec cette valeur en premier
            $this->addrow([$contenu]);
        }
        else{
            //complement du dernier row créé
            $indice = count($this->rows) -1;
            $this->rows[$indice][]= $contenu;
        }
    }
    
    private function codeCell($texte,string $classe, string $alignement, int $colspan, int $rowspan, bool $hidden){
    //encode un contenu de cellule avec la class et l'alignement
    // exmple montexte££clMaclasse££alMonAlignement
        //traitement de l'alignement horizontal
        $alignement1 = strtoupper($alignement);
        $classe1 = str_contains($alignement1, "C") ?  "text-center":"";
        $classe1 .= str_contains($alignement1, "D") ?  "text-right":"";
        $classe1 .= str_contains($alignement1, "G") ?  "text-left":"";
        //alignement vertical
        $classe1.=" "; //un blanc au cas où double positionnement exprimé
        $classe1 .= str_contains($alignement1, "H") ?  "align-top":"";
        $classe1 .= str_contains($alignement1, "M") ?  "align-middle":"";
        $classe1 .= str_contains($alignement1, "B") ?  "align-bottom":"";
        
        $retour = $texte ;
        //si une des zones est renseignée
        $retour .= strlen(trim($classe)) + strlen(trim($classe1)) === 0 ? "": self::SEPARATOR . "cl" . trim($classe) . " " . trim($classe1);
        $retour .= $hidden ? self::SEPARATOR . "hd":""  ;
        $retour .= $colspan===1 ? "" : self::SEPARATOR . "cs" . $colspan ;
        $retour .= $rowspan===1 ? "" : self::SEPARATOR . "rs" . $rowspan ;
        return $retour;
    }
    
    private function decodeCell(string $texte, int $rangHeader = 0){
    //entête est soit le rang du headr, soit 0
    //assemble un row exprimé sous la forme texte££classe
    //un cellule est codee par montexte££clMaclasse££alMonAlignement
        //tag td ou th selon si entête ou ligne
        $tag = $rangHeader ===1 ? "th" : "td";
        
        $element = "<" . $tag . ' ';
        if (str_contains($texte,self::SEPARATOR)){
            $tableau = explode(self::SEPARATOR, $texte);
            for ($i=1;$i<count($tableau);$i++){
                $element .= $this->decodeCell1($tableau[$i]);
            }
            //affichage du texte
            $element .= '>' . $tableau[0];
        }
        else{
            //si pas de tags complémentaires alors affichage texte direct
            $element .= '>' . $texte;
        }
        return $element . "</" . $tag . '> ';
    }
    
     private function decodeCell1(string $texte){
    //assemble un row exprimé sous la forme texte££classe
    //un cellule est codee par montexte££clMaclasse££alMonAlignement
        $prefixe = substr($texte, 0, 2);
        $element = "";
        if ($prefixe == "hd"){
            return " hidden ";
        }
        else{
            switch ($prefixe){
                case "cl":
                    //traitement classe
                    $element .= 'class="' . substr($texte, 2) . '" ' ;
                    break;
                case "cs":
                    //traitement colspan, si un c'est ecarté
                    $element .=  ((int) substr($texte, 2)) > 1 ? 'colspan="' . substr($texte, 2) . '" ' : "";
                    break;
                case "rs":
                    //traitement classe
                    $element .=  ((int) substr($texte, 2)) > 1 ? 'rowspan="' . substr($texte, 2) . '" ' : "";
                    break;
            }
            //retour tag et sa valeur
            return $element ;
        }
    }
    public function render() {
    //render() : Méthode qui génère le code HTML complet de la table.
        $html="";
        if ($this->responsive){$html .= '<div class="table-responsive" id="listeAuto">';}
        $html .= '<table class="' . htmlspecialchars($this->tableClass) . '">';
        $html .= $this->renderHeaders();
        $html .= $this->renderRows();
        $html .= '</table>';
        if ($this->responsive){$html .= '</div>';}
        return $html;
    }

    private function renderHeaders() {
    //renderHeaders() : Méthode qui génère le code HTML pour les en-têtes de la table.
        $html = '<thead>';
        for ($i=1;$i<=count($this->headers);$i++){
            $html .= '<tr>';
            foreach ($this->headers[$i] as $header) {
                //remplacement des _ par des blancs
                $html .= str_replace("_"," ",$this->decodeCell((string)$header,$i));
            }
            $html .= '</tr>';
        }
        $html .= '</thead>';
        return $html;
    }
    
    
    private function renderRows() {
    //renderRows() : Méthode qui génère le code HTML pour les lignes de données de la table.
        $html = '<tbody>';
        //pour chaque ligne
        foreach ($this->rows as $ligne=>$row) {
            $html .= '<tr>';
            foreach ($row as $colonne=>$cell) {
                //traitement de la numérotation
                if (!empty($this->numerotation) && $colonne == $this->colonneNumerotation){$html .= $this->afficherNumero($ligne);}
                //pour chaque colonne
                $html .= $this->decodeCell($cell);
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        return $html;
    }
    
    private function afficherNumero(int $ligne){
    //constitue la cellule d'affichage du numero ..ou pas
    //Numerotation vaut [] pas de n°, [0] si toutes les lignes 
    //sinon [1,-1] pas la première et pas la dernière, [2,-2] pas sur 2 premières et deux dernières
        $ecrire = true;
        $min = - min($this->numerotation);
        $max = max($this->numerotation);
  
        //si le nombre de lignes restantes est < au nombre de lignes à ne pas afficger
        $reste = count($this->rows)- $ligne -1;
        if($reste < $min){$ecrire = false;}
        
        //affiché comptage positif
        elseif($ligne < $max){$ecrire = false;}

        //ecriture si controle ok
        $element = "";
        if ($ecrire){
            //mise en forme n° aligné
            $valeur = $this->codeCell($ligne + 1 - $max, "", "CM",  1,  1, false,false);
            $element= $this->decodeCell($valeur);
        }
        return $element;
    }
}