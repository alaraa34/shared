// Lecteur multipiste (HTML produit par shared\php\classes\lien\Multipiste::lecteurHtml)
// - les pistes (voix, batterie, basse, guitare, piano, autre) sont des MP3 séparés, joués ensemble et resynchronisés
// - les fichiers ne sont chargés qu'au premier appui sur Lecture (rien n'est téléchargé à l'affichage de la liste)
// - un seul lecteur joue à la fois ; décocher une piste la rend muette
// Fonctionne aussi pour une liste rechargée en ajax (événements délégués sur le document)
(function () {
    "use strict";
    const ECART_MAX = 0.08;          // décalage toléré entre pistes (secondes) avant resynchronisation
    const PERIODE_SYNCHRO = 250;     // vérification du décalage (millisecondes)
    const lecteurs = new WeakMap();  // élément .mp-lecteur -> état
    let lecteurActif = null;

    function formaterTemps(t) {
        if (!isFinite(t) || t < 0) { t = 0; }
        const minutes = Math.floor(t / 60), secondes = Math.floor(t % 60);
        return minutes + ":" + String(secondes).padStart(2, "0");
    }

    function pisteCochee(el, code) {
        const caseACocher = el.querySelector('.mp-pistes input[value="' + code + '"]');
        return caseACocher ? caseACocher.checked : true;
    }

    function icone(etat, nom) {
        const bouton = etat.el.querySelector(".mp-play");
        bouton.innerHTML = nom === "attente"
            ? '<span class="spinner-border spinner-border-sm" role="status"></span>'
            : '<i class="bi bi-' + nom + '-fill"></i>';
        bouton.setAttribute("aria-label", nom === "pause" ? "Pause" : "Lecture");
    }

    function maitre(etat) { return etat.audios[0]; }

    function initialiser(el) {
        let etat = lecteurs.get(el);
        if (etat) { return etat; }
        const pistes = el.dataset.pistes ? el.dataset.pistes.split(",") : [];
        const sources = pistes.length
            ? pistes.map(code => ({ code: code, url: el.dataset.base + code + ".mp3" }))
            : [{ code: "mp3", url: el.dataset.mp3 }];
        etat = { el: el, audios: [], joue: false, attente: new Set(), minuteur: null };
        for (const source of sources) {
            const audio = new Audio();
            audio.preload = "auto";
            audio.dataset.code = source.code;
            audio.muted = !pisteCochee(el, source.code);
            audio.addEventListener("waiting", () => surAttente(etat, audio));
            audio.addEventListener("canplay", () => surPret(etat, audio));
            audio.addEventListener("error", () => surErreur(etat, audio));
            audio.addEventListener("loadedmetadata", () => { if (audio === maitre(etat)) { majPosition(etat, true); } });
            audio.addEventListener("timeupdate", () => { if (audio === maitre(etat)) { majPosition(etat, false); } });
            audio.addEventListener("ended", () => { if (audio === maitre(etat)) { arreter(etat, true); } });
            audio.src = source.url;
            etat.audios.push(audio);
        }
        lecteurs.set(el, etat);
        return etat;
    }

    function majPosition(etat, duree) {
        const m = maitre(etat);
        if (!m) { return; }
        const curseur = etat.el.querySelector(".mp-position");
        if (duree || Number(curseur.max) === 0) { curseur.max = isFinite(m.duration) ? m.duration : 0; }
        if (!etat.deplacement) { curseur.value = m.currentTime; }
        etat.el.querySelector(".mp-temps").textContent = formaterTemps(m.currentTime);
    }

    //--- lecture -------------------------------------------------------------------------------
    function lancer(etat) {
        if (lecteurActif && lecteurActif !== etat) { arreter(lecteurActif, false); }
        lecteurActif = etat;
        etat.joue = true;
        etat.el.classList.add("mp-en-lecture");
        // play() dans le clic : nécessaire sur smartphone pour autoriser la lecture de chaque piste
        const reference = maitre(etat).currentTime;
        for (const audio of etat.audios) {
            if (Math.abs(audio.currentTime - reference) > ECART_MAX) { audio.currentTime = reference; }
            if (audio.readyState < 3) { etat.attente.add(audio); }
            const promesse = audio.play();
            if (promesse) { promesse.catch(() => {}); }
        }
        if (etat.attente.size > 0) { suspendre(etat); }
        icone(etat, etat.attente.size > 0 ? "attente" : "pause");
        clearInterval(etat.minuteur);
        etat.minuteur = setInterval(() => resynchroniser(etat), PERIODE_SYNCHRO);
    }

    function arreter(etat, retourDebut) {
        etat.joue = false;
        etat.attente.clear();
        clearInterval(etat.minuteur);
        for (const audio of etat.audios) {
            audio.pause();
            if (retourDebut) { audio.currentTime = 0; }
        }
        etat.el.classList.remove("mp-en-lecture");
        icone(etat, "play");
        if (retourDebut) { majPosition(etat, false); }
        if (lecteurActif === etat) { lecteurActif = null; }
    }

    function suspendre(etat) {
        // une piste n'a pas assez de données : toutes attendent pour rester ensemble
        for (const audio of etat.audios) { if (!audio.paused) { audio.pause(); } }
        icone(etat, "attente");
    }

    function reprendre(etat) {
        const reference = maitre(etat).currentTime;
        for (const audio of etat.audios) {
            if (Math.abs(audio.currentTime - reference) > ECART_MAX) { audio.currentTime = reference; }
            const promesse = audio.play();
            if (promesse) { promesse.catch(() => {}); }
        }
        icone(etat, "pause");
    }

    function surAttente(etat, audio) {
        if (!etat.joue) { return; }
        etat.attente.add(audio);
        suspendre(etat);
    }

    function surPret(etat, audio) {
        if (!etat.attente.delete(audio)) { return; }
        if (etat.joue && etat.attente.size === 0) { reprendre(etat); }
    }

    function surErreur(etat, audio) {
        // piste introuvable : on continue sans elle ; si plus rien, message dans le lecteur
        etat.audios = etat.audios.filter(a => a !== audio);
        etat.attente.delete(audio);
        const caseACocher = etat.el.querySelector('.mp-pistes input[value="' + audio.dataset.code + '"]');
        if (caseACocher) { caseACocher.checked = false; caseACocher.disabled = true; }
        if (etat.audios.length === 0) {
            arreter(etat, false);
            etat.el.classList.add("mp-erreur");
            etat.el.querySelector(".mp-play").disabled = true;
            etat.el.querySelector(".mp-temps").textContent = "fichier introuvable";
        }
        else if (etat.joue && etat.attente.size === 0) { reprendre(etat); }
    }

    function resynchroniser(etat) {
        // lecteur retiré de la page (autre titre affiché, liste rechargée) : on arrête la musique
        if (!etat.el.isConnected) { arreter(etat, false); return; }
        if (!etat.joue || etat.attente.size > 0) { return; }
        const m = maitre(etat);
        for (const audio of etat.audios) {
            if (audio !== m && Math.abs(audio.currentTime - m.currentTime) > ECART_MAX) {
                audio.currentTime = m.currentTime;
            }
        }
    }

    function deplacer(etat, temps) {
        for (const audio of etat.audios) { audio.currentTime = temps; }
        majPosition(etat, false);
    }

    //--- événements délégués --------------------------------------------------------------------
    document.addEventListener("click", function (evenement) {
        const bouton = evenement.target.closest(".mp-lecteur .mp-play, .mp-lecteur .mp-deplier");
        if (!bouton || bouton.disabled) { return; }
        const el = bouton.closest(".mp-lecteur");
        if (bouton.classList.contains("mp-deplier")) {
            const pistes = el.querySelector(".mp-pistes");
            pistes.hidden = !pistes.hidden;
            bouton.setAttribute("aria-expanded", String(!pistes.hidden));
            return;
        }
        const etat = initialiser(el);
        if (etat.joue) { arreter(etat, false); } else { lancer(etat); }
    });

    document.addEventListener("input", function (evenement) {
        if (!evenement.target.matches(".mp-lecteur .mp-position")) { return; }
        const etat = lecteurs.get(evenement.target.closest(".mp-lecteur"));
        if (!etat) { return; }
        etat.deplacement = true;
        etat.el.querySelector(".mp-temps").textContent = formaterTemps(Number(evenement.target.value));
    });

    document.addEventListener("change", function (evenement) {
        const cible = evenement.target;
        const el = cible.closest(".mp-lecteur");
        if (!el) { return; }
        const etat = lecteurs.get(el);
        if (cible.matches(".mp-position")) {
            if (etat) { etat.deplacement = false; deplacer(etat, Number(cible.value)); }
        }
        else if (cible.matches(".mp-pistes input") && etat) {
            for (const audio of etat.audios) {
                if (audio.dataset.code === cible.value) { audio.muted = !cible.checked; }
            }
        }
    });

    // à appeler avant de remplacer une liste de lecteurs (sinon la musique continue)
    window.lecteurMultipisteToutArreter = function () {
        if (lecteurActif) { arreter(lecteurActif, false); }
    };
})();
