//---------fonctions pour saisie telechargement---------------------------------
function jsSaisieNouveauRepertoire(urlControleur){
//utilisé par la saisie dteail téléchargement
//teste si la textbox est affichée, si oui on affiche la liste et réciproquement
//si le répertoire choisi est nouveau alors on remplece la liste par un champ de saisie
    var repertoire = $('#repertoire').val().trim();
    var largeur = $('#repertoire').width();
    //alert("textBox visible " + textBoxIsVisible );
    if (repertoire ==="Nouveau"){
        //alert("remplacement");
        //remplace la combo par une textbox 
        myhtml = "<input width=\"" + largeur + "\" required type=\"text\" name=\"repertoire\"  class=\"form-control\"  placeholder=\"Nom du nouveau répertoire\" />";
        $('#listeRepertoires').html(myhtml);
        $('#listeTelechargementRepertoire').html("");//init de la liste des tele chargés ds répertoire
    }
    else {
        //chargement dans la div listeTelechargementRepertoire du contenu du répertoire
        Get_Form_zone(urlControleur,"listeTelechargementRepertoire","repertoire");
    }
}