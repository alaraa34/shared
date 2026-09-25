//==============================================================================
//gestion des éléments dans une liste sur un détail d'éléments
//==============================================================================

function jsListeAddNew(sujet,zonesObligatoires=""){
//ajoute une ligne de lien dans la tableau;
//sujet = identifiant générique de l'élément à dupliquer
//obligatoires : nom des éléments qui sont obligatoires
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
    // rendre certains éléments obligatoires
    if (zonesObligatoires.length() > 0){
        obligatoires = zonesObligatoires.split(";");
        obligatoires.foreach(obligatoire =>{
            $("#" + obligatoire + indice.toString()).prop('required',true);
        });
    }
    $("#" + sujet + indice.toString()).show(); //affichage du lien créé
    
    return indice;
}


function jsListeSuppression(ligne,sujet){
//cache la ligne et position le tag actionLigne à "supprimer";
    //alert("Supp " + ligne );
    lien = "#" + sujet + ligne.toString();
    id = $("#id" + jsFormaterNom(sujet) + ligne.toString()).val();
    if (id===0){
        $(lien).remove();}
    else{
        $(lien).hide();    // cache la ligne et met le code action à "supprimer"
        $("#action" + jsFormaterNom(sujet) + ligne.toString()).val("S");}
}

function jsListeModification(ligne,sujet){
//Indique que çà a été modifié sauf si on est en création
    controle = "#action" + jsFormaterNom(sujet) + ligne.toString();
    //alert("Modif" + controle + " Valeur avant " + $(controle).val());
    if ($(controle).val()!=="C"){
        $(controle).val("M");} //action à Modif
    //alert("Modif" + controle + " Valeur après " + $(controle).val());
}

function jsCodeActionChanger(ligne,codeAction){
//Indique que çà a été modifié sauf si on est en création
    controle = "#action" + ligne.toString();
    //alert("Modif" + controle + " Valeur avant " + $(controle).val());
    $(controle).val(codeAction); //action à Modif
    //alert("Modif" + controle + " Valeur après " + $(controle).val());
}
