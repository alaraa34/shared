//références https://analyse-innovation-solution.fr/publication/fr/jquery/les-requetes-ajax-avec-jquery
//https://www.pierre-giraud.com/jquery-apprendre-cours/creation-requete-ajax/
//  
//---------------------------------------------------------------------------------------------------------
function ajaxAfficherPage(urlControleur, dataNom,dataValeur){
// parametres strings tableau des nom passés et de leur valeur 
// url controler url qui va traiter  ex "http://localhost/testAjax/controlleur.php"
//exemple appela jaxAfficherPage($('#url').val(),["changements"],[evts_prec]);
//cette fonction sollicite une URL sans traiter un retour
//
  try{
        let Datas = formatterDatas(dataNom,dataValeur);
	let request =
            $.ajax({
              type: "POST", 
              data:Datas,
              url: urlControleur
            });

        request.done(function(resultat) {
          //Code à jouer en cas d'exécution sans erreur du script du PHP
          ajaxReponseLog("ajaxAfficherPage","Succes",resultat);
        });
        
        request.fail(function (resultat) {
        //Code à jouer en cas d'éxécution en erreur du script du PHP
           ajaxReponseLog("ajaxAfficherPage","Erreur",resultat);
        });
  }
  catch(e){
    alert(e + "bis");
  }
}
function ajaxReponseLog(source,resultat,http_error){
//logge à la console, resultat = Succes ou Erreur
        let server_msg = http_error.responseText;
        let code = http_error.status;
        let code_label = http_error.statusText;
        console.log(source + " :" + resultat + code + " (" + code_label + ") : "  + server_msg);
}

function Set_Form(urlControleur,dataNom,dataValeur){
// parametres strings tableau des nom passés et de leur valeur 
// url controler url qui va traiter  ex "http://localhost/testAjax/controlleur.php"
//exemple appel Set_Form($('#url').val(),["changements"],[evts_prec]);
//cette fonction sollicite une URL sans traiter un retour
  try{
        //transformation des tableaux de depatr en tableau
	let Datas = formatterDatas(dataNom,dataValeur);

	let request =
            $.ajax({
              type: "POST", 
              url: urlControleur,
              data:Datas,
              dataType: 'json',
              timeout: 120000, //2 Minutes
              cache: false,
              contentType: false,
              processData: false,
              beforeSend: function () {
                //Code à jouer avant l'appel ajax en lui même
              }
            });

        request.done(function(output_success) {
          //Code à jouer en cas d'exécution sans erreur du script du PHP
          //alert(output_success.output);
            let server_msg = http_error.responseText;
            let code = http_error.status;
            let code_label = http_error.statusText;
            //alert("Success "+code+" ("+code_label+") : "  + server_msg);
        });
        
        request.fail(function (http_error) {
        //Code à jouer en cas d'éxécution en erreur du script du PHP

            let server_msg = http_error.responseText;
            let code = http_error.status;
            let code_label = http_error.statusText;
                  
            //alert("Erreur "+code+" ("+code_label+") : "  + server_msg);
        });

      request.always(function () {
         //Code à jouer après done OU fail dans tous les cas 
            let server_msg = http_error.responseText;
            let code = http_error.status;
            let code_label = http_error.statusText;
            //alert("always "+code+" ("+code_label+") : "  + server_msg);
      });

  }
  catch(e){
    alert(e + "bis");
  }
}
function  formatterDatas(dataNom,dataValeur){
// mets sous forme de tableau nommés les datas passées
//exemple data nom = nom  et datavaleur = raibaut devient['nom','raibaut']
//si c'est la même donnée retoure plusieurs instances de tableaux nominateifs (ex [0]['nom','raibaut'],[1]['nom','durant']
    var noms = dataNom.toString().split(';');
    var valeurs = dataValeur.toString().split(';');
    let myDatas = new FormData();
      for (i=0;i<noms.length;i++){
        myDatas.append(noms[i], valeurs[i]);
    }
    return myDatas;
}

function Get_Form_zone(urlControleur, nomComposant, nomZoneDonnee){
//envoi un formulaire au serveur : ref https://www.tutos.eu/3730
//declaration bouton exemple <!--onchange="<?= "Get_Form_zone('" . $urlControleur ."', 'detailPlanning','dateDebutPlanning')"?>"> -->
//paramètres :
//urlControleur : url qui va traiter l'action ex http://localhost/testAjax/index.php?action=traiter 
//nomComposant : composant dont le HTML est à rafraichir
//Nom zone, nom de la zone qui sert de donnée
   
    //transformation des tableaux de données
    var zones = nomZoneDonnee.toString().split(';');
    let valeurs="";
    for (i = 0; i < zones.length; i++){
        valeurs += $("#" + zones[i]).val();
        if (i < zones.length -1){valeurs+=";";}
    }
    //alert(valeurs);
    let str = formatterDatas(nomZoneDonnee,valeurs);
    Get_Form_requete(urlControleur,nomComposant,str);
}
function Get_Form_formulaire(urlControleur, nomFormulaire, nomComposant){
//envoi un formulaire au serveur : ref https://www.tutos.eu/3730
//declaration formulaire <form method="POST" onsubmit="return Get_Form(urlControleur, nomFormulaire, nomComposant);">
//paramètres :
//urlControleur : url qui va traiter l'action ex http://localhost/testAjax/index.php?action=traiter  
//nomformulaire : nom du formulaire à récupérer
//nomComposant : composant dont le HTML est à raffraichir
        //serialisation du formulaires
        var str = $('#' + nomFormulaire).serialize();
        Get_Form_requete1(urlControleur,nomComposant,str);
}

function Get_Form_formulaireModal(urlControleur, nomFenetreModale, nomComposant){
//envoi un formulaire au serveur : ref https://www.tutos.eu/3730
//declaration formulaire <form method="POST" onsubmit="return Get_Form(urlControleur, nomFormulaire, nomComposant);">
//paramètres :
//urlControleur : url qui va traiter l'action ex http://localhost/testAjax/index.php?action=traiter  
//nomformulaire : nom du formulaire à récupérer
//nomComposant : composant dont le HTML est à raffraichir
        //par convention le formulaire est form + id de la fenêtre modale
        var str = $('#' + "form" + nomFenetreModale).serialize();
        Get_Form_requete1(urlControleur,nomComposant,str);
        bootstrap.Modal.getInstance(document.getElementById(nomFenetreModale)).hide()
}

function Get_Form_requete(urlControleur, nomComposant, datas, datasType){
    try{
	let request =
            $.ajax({
              type: "POST", 
              url: urlControleur,
              data:datas,
              dataType: datasType,
              timeout: 120000, //2 Minutes
              cache: false,
              contentType: false,
              processData: false,
              beforeSend: function () {
                //Code à jouer avant l'appel ajax en lui même
                console.log('code before');
              }
            });

            request.done(function (output_success) {
                //Code à jouer en cas d'éxécution sans erreur du script du PHP
                //alert('réponse service ' + output_success);
                 $('#' + nomComposant).html(output_success);
                 console.log('Retour requete OK');
            });
            
            request.fail(function (http_error) {
                //Code à jouer en cas d'éxécution en erreur du script du PHP

                 let server_msg = http_error.responseText;
                 let code = http_error.status;
                 let code_label = http_error.statusText;
                console.log("Erreur "+code+" ("+code_label+") : "  + server_msg);
            });

            request.always(function () {
                //Code à jouer après done OU fail dans tous les cas 
                console.log('always');
            });

        }
    catch(e){
        alert(e);
    }
}
function Get_Form_requete1(urlControleur, nomComposant , str){
//
    $.ajax( {
            type: "POST",
            url: urlControleur,
            data: str,
             //Le format de réponse attendu
            dataType : "html",
            success: function(response) {
                //alert('répose service ' + response);
                //alert ('HTML composant avant '+ $('#'+ nomComposant).html());
                //extraction de l'évènement
                evenement = "";
                //console.log(response);
                if (response.includes("££ID££")){
                    evenement = response.split("££ID££")[0] ;
                    response = response.split("££ID££")[1];
                }
                //Changement du html
                $('#'+ nomComposant).html(response); //Affichage la réponse dans le composant
                //alert ('HTML de ' + nomComposant + ' après '+ $('#'+ nomComposant).html());

                //envoi de l'évènement si demande  sur le composant event click, change..
                if (evenement.length >0){
                    const event = new MouseEvent("click");
                    document.getElementById(nomComposant).dispatchEvent(event);
                }
            },
            error: function(response ) {
                    texte = 'Get_Form_requete1 - Erreur pour poster le formulaire : ' + response.status + " " + response.statusText;
                    console.log( texte );
            }						
    } );
}
