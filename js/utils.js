function jsModifierValeur(identifiant,valeur){
//Modifie une valeur d'un bouton;
    //Change la valeur du champ value
    $("#" + identifiant).val(valeur);
}
//-------------------------------------------------------------------------------------------
function jsFormaterNom(nom){
    //formatte un nom avec une majeuscule en initiale + minuscule
    return nom.charAt(0).toUpperCase() + nom.substring(1).toLowerCase();
}

function jsAfficherControle(controle){
//affiche un controle 
//pour cacher un lien à afficher avec show il faut utiliser style="display:none" et pas hidden
    $('#' + controle).show();
}

function jsMasquerControle(controle){
//pour cacher un lien à afficher avec show il faut utiliser style="display:none" et pas hidden
    $('#' + controle).hide();
}