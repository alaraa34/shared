//Gestion des listes de liens sur un écran de détail
//Ma liste contient un input modif + ID de ligne avec 4 valeur
// rien pas touché, supp (supprimé), modif(modifié), creation(créé)

function jsLienModificationType(ligne){
//Indique que çà a été modifié sauf si on est en création
//lienTelechargeable indique les types de liens qui sont alimentés avec téléchargement
//types de liens externes est un string avec des ; comme séparateur exemple jsLienModificationType(0, 4;10)
    //traitement standard
    jsLienModification(ligne);
    //alert($("#listeExtensions").val());
    //alert($("#listeExternes").val());
    //recherche du type de lien et activation saisie URL si externe
    lienID  = $("#idTypeLien" + ligne.toString()).val();
    //tableau = typesLienExternes.toString().split(";");
    tableau = $("#listeExternes").val().toString().split(";");
    //si lien ID existe dans tableau, c'est que c'est un fichier externe
    interne = true ;
    if (tableau.includes(lienID)){
        interne = false ;
    }
      
    jsLienActiver (ligne,interne,lienID);
   
}
function jsLienActiver(ligne, interne,lienID){
//lienTelechargeable indique les types de liens qui sont alimentés avec téléchargement
//pour cacher un lien à afficher avec show il faut utiliser style="display:none" et pas hidden
    //si interne alors bouton de téléchargement affiché et url masquée
    //si non interne alors bouton de téléchargement (fileUpload) masque et url(url) ouvert à la saisie
    if (interne){
        // bouton téléchargement actif
        $("#url" + ligne.toString()).hide(); 
        $("#url" + ligne.toString()).prop('required',false);
        $("#fileUpload" + ligne.toString()).prop('required',true);
        $("#fileUpload" + ligne.toString()).prop('disabled',false);
        $("#fileUpload" + ligne.toString()).show();
        // la commande sera sos la forme accept="image/png, image/jpeg" />
        mesExtensions = jsExtensionsAutorisees(lienID);
        $("#fileUpload" + ligne.toString()).attr("accept",mesExtensions);
        }
    else{
        //bouton saisie url valide
        $("#fileUpload" + ligne.toString()).hide();
        $("#url" + ligne.toString()).prop('required',true);
        $("#fileUpload" + ligne.toString()).prop('required',false);
        $("#url" + ligne.toString()).show(); 
        $("#url" + ligne.toString()).prop("disabled", false ); 
    }
    //dans les deux cas description obligatoire
    $("#description" + ligne.toString()).prop('required',true);
    $("#description" + ligne.toString()).prop('disabled',false);
}

function jsExtensionsAutorisees(idTypeLien){
    autorisations = JSON.parse($("#listeExtensions").val());
    //inventaire.find((fruit) => fruit.nom === "cerises");
    lien = autorisations.find((poste) => poste.id === parseInt(idTypeLien));
    // la commande sera sos la forme accept="image/png, image/jpeg" />
    return lien['extensions'];
}


function jsLienModification(ligne){

    //traitement standard
    jsListeModification(ligne,"lien");
    
}   

function jsLienModificationURL(ligne){
//blocage du type 

    //traitement standard
    jsLienModification(ligne);
    
    //Désactivation de la saisie du type de lien
    //$("#typeLien" + ligne.toString()).prop("disabled", true ); 
    $("#typeLien" + ligne.toString()).prop('readonly', true);
   
}

function jsListeAjoutLien(){
    numero = jsListeAjout('lien');
    $("#fileUpload" + numero.toString()).hide();
}

function jsListeAjout(sujet){
//ajoute une ligne de lien dans la tableau;
//sujet = sujet concerné, exemple lien, contact.....
    //recherche de l'indice du dernier lien 
    const indiceModele = 99;
    var indice = 0 ;
    var indiceLigne = 0;
    var indicePrecedent = indiceModele;
    //'Traite tous les id commençant par lien
    $('[id^=' + sujet ).each(function() { 
             //si tr concerne une lien
            element = $(this).attr("id");
            //extraction de 2 carcactères après le sujet
            indiceLigne= parseInt(element.substr(sujet.length,2));
            if (indice <  indiceLigne && indiceLigne < indiceModele){
                indice =  indiceLigne;
            }
            
    });
    //tag à insérer avec l'indice suivant
    indice += 1 ;
    if (indice>1){indicePrecedent = indice -1;}
    var modele = $("#" + sujet + indiceModele).html(); 
    modele = modele.replaceAll(indiceModele, indice);
  
    modele = "<tr id=\"" + sujet  + indice.toString() + "\">" + modele + "</tr>" ;

    // insertion en dernière ligne
    $("#" + sujet + indicePrecedent.toString()).after(modele);
    //alert ($("#liens").html());
    $("#action" + jsFormaterNom(sujet) + indice.toString()).val("C"); //action à créer
    // rendre le type de lien  et la description obligatoire
    $("#idTypeLien" + indice.toString()).prop('required',true);
    $("#descriptionLien" + indice.toString()).prop('required',true);
    $("#" + sujet + indice.toString()).show(); //affichage du lien créé
    
    return indice;
}
