//---------JS player---------------------------------
function jsAffichagePlayer(){
//En modification  de la demande d'affichage d'un player    
    //récupération des infos des box
    idContexte = $('#idContexte').val();
    typeMP3 = $('#typeMP3').val();
    idSetlist = $('#idSetlist').val();
    //constitution d'un objet data destiné au post
    let str = formatterDatas("idContexte;typeMP3;idSetlist" ,  idContexte + ";" + typeMP3 +" ;" + idSetlist);
    //requete d'envoi avec action du retour sur l'objet ayant pour id "player"
    Get_Form_requete($('#url').val(),"player",str,'json');
}

function jsPlayerPrecedent(){
//en affichage de document, affiche le précédent 
    indice = parseInt($('#indice').val());
    if(indice === 0){
        alert('Le premier titre de la liste est déjà affiché');
    }
    else{
        //affichage du doc
       songsJS = $("#songs").val();
       songs = JSON.parse(songsJS);
       jsPlayerAfficherIndice(songs,indice -1);
    }
}
function jsPlayerSuivant(){
//en affichage de document, affiche le précédent 
    indice = parseInt($('#indice').val());
    songsJS = $("#songs").val();
    songs = JSON.parse(songsJS);
    if(indice === songs.length -1){
        alert('Le dernier titre de la liste est déjà affiché');
    }
    else{
       //affichage du doc
       jsPlayerAfficherIndice(songs, indice + 1);
    }
}

function jsPlayerAfficherIndice(songs,indice){
    //affichage du doc
    //mise à jour indice
    $('#indice').val(indice);
    
    //affichage du document
    song = songs[indice];
    $('#lecteur').attr('src',song['lienPDF']);
    if (song['lienPDF'] === null){
        $('#lecteur').hide();
        $('#message').show();
        $('#message').html("<h4>Pas de document disponible pour le titre <br>" + song['titre'] + "</h4>");
    }
    else{
        $('#message').html("");
        $('#lecteur').show();
        $('#message').hide();
    }
    
    //affichage du titre précédent à venir sous le bouton précédent
    if (indice === 0){
        $('#titrePrecedent').html("");
    }
    else{
        song = songs[indice-1];
         $('#titrePrecedent').html(song['titre']);
    }

    //affichage du titre précédent à venir sous le bouton précédent
    if (indice === songs.length -1){
        $('#titreSuivant').html("");
    }
    else{
        song = songs[indice+1];
        $('#titreSuivant').html(song['titre']);
    }
}
