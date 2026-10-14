// Confirmation d'un changement de tona de scène sur un titre existant
// (attributs et champ produits par shared\php\classes\lien\MultipisteControle::confirmationTonaHtml)
// - <select data-tona-initiale="+2" data-tona-message="..."> : la tona enregistrée et le message à afficher
// - <input type="hidden" name="confirmationTona"> : passé à 1 si l'utilisateur répond Oui
// A l'enregistrement, si la tona a changé : fenêtre Oui / Non
//   Oui : enregistrement avec la nouvelle tona (le serveur supprime les pistes devenues inutiles)
//   Non : la tona d'origine est remise et le reste du titre est enregistré
//   fermeture de la fenêtre (croix, Échap) : rien n'est enregistré, retour à la saisie
(function () {
    "use strict";

    function valeur(texte) {
        const nombre = parseInt(String(texte || "").trim(), 10);
        return isNaN(nombre) ? 0 : nombre;
    }

    function fenetre(message, surOui, surNon) {
        const div = document.createElement("div");
        div.className = "modal fade";
        div.tabIndex = -1;
        div.innerHTML =
            '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Tona de scène</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>' +
            '<div class="modal-body"></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-reponse="non">Non</button>' +
            '<button type="button" class="btn btn-danger" data-reponse="oui">Oui</button>' +
            '</div></div></div>';
        div.querySelector(".modal-body").textContent = message;
        document.body.appendChild(div);
        const modale = new bootstrap.Modal(div);
        let reponse = null;
        div.addEventListener("click", function (evenement) {
            const bouton = evenement.target.closest("[data-reponse]");
            if (!bouton) { return; }
            reponse = bouton.dataset.reponse;
            modale.hide();
        });
        div.addEventListener("hidden.bs.modal", function () {
            div.remove();
            if (reponse === "oui") { surOui(); }
            else if (reponse === "non") { surNon(); }
        });
        modale.show();
    }

    document.addEventListener("submit", function (evenement) {
        const formulaire = evenement.target;
        const liste = formulaire.querySelector("select[data-tona-initiale]");
        const confirmation = formulaire.querySelector('input[name="confirmationTona"]');
        if (!liste || !confirmation || confirmation.value === "1") { return; }
        if (valeur(liste.value) === valeur(liste.dataset.tonaInitiale)) { return; }
        evenement.preventDefault();
        fenetre(liste.dataset.tonaMessage,
            function () {
                confirmation.value = "1";
                formulaire.submit();
            },
            function () {
                // remise de la tona d'origine (option de même valeur numérique) puis enregistrement du reste
                const initiale = valeur(liste.dataset.tonaInitiale);
                const option = Array.from(liste.options).find(o => valeur(o.value) === initiale);
                if (option) { liste.value = option.value; }
                formulaire.submit();
            });
    });
})();
