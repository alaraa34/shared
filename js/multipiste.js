// Lecteur multipiste (HTML produit par shared\php\classes\lien\Multipiste::lecteurHtml)
// - les pistes (voix, batterie, basse, guitare, piano, autre) sont des MP3 séparés, joués ensemble et resynchronisés
// - les fichiers ne sont chargés qu'au premier appui sur Lecture (rien n'est téléchargé à l'affichage de la liste)
// - un seul lecteur joue à la fois ; décocher une piste la rend muette
// Fonctionne aussi pour une liste rechargée en ajax (événements délégués sur le document)
(function () {
    "use strict";
    // Synchronisation : une piste décalée est rattrapée en douceur (vitesse légèrement modifiée pendant
    // une ou deux secondes) ; elle n'est repositionnée (saut, audible) que si le décalage devient important.
    // Sur smartphone la position des pistes est moins précise : des repositionnements fréquents
    // provoquaient de brèves interruptions.
    const ECART_TOLERE = 0.04;       // décalage ignoré (secondes)
    const ECART_RATTRAPE = 0.01;     // un rattrapage commencé continue jusqu'à ce décalage
    const ECART_SAUT = 0.4;          // au-delà : repositionnement immédiat de la piste
    const CORRECTION_MAX = 0.05;     // vitesse de rattrapage : 0,95 à 1,05
    const DUREE_RATTRAPAGE = 2;      // secondes pour résorber un décalage
    const DELAI_ATTENTE = 400;       // une piste « en attente » moins longtemps que ça ne bloque pas les autres (ms)
    const PERIODE_SYNCHRO = 250;     // vérification du décalage (millisecondes)
    const ECART_MAX = ECART_TOLERE;  // départ et reprise : pistes repositionnées si l'écart dépasse ce seuil
    const lecteurs = new WeakMap();  // élément .mp-lecteur -> état
    let lecteurActif = null;

    function formaterTemps(t) {
        if (!isFinite(t) || t < 0) { t = 0; }
        const minutes = Math.floor(t / 60), secondes = Math.floor(t % 60);
        return minutes + ":" + String(secondes).padStart(2, "0");
    }

    function appliquerPistes(el, etat) {
        // son coupé pour les pistes décochées
        if (!etat) { return; }
        for (const audio of etat.audios) { audio.muted = !pisteCochee(el, audio.dataset.code); }
    }

    function majTout(el) {
        // case « Tout » : cochée si toutes les pistes disponibles le sont, à moitié (tiret) si seulement certaines
        const tout = el.querySelector(".mp-pistes .mp-tout");
        if (!tout) { return; }
        const cases = Array.from(el.querySelectorAll(".mp-pistes input:not(.mp-tout)")).filter(c => !c.disabled);
        const cochees = cases.filter(c => c.checked).length;
        tout.checked = cases.length > 0 && cochees === cases.length;
        tout.indeterminate = cochees > 0 && cochees < cases.length;
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
        vitesseNormale(etat);
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
        vitesseNormale(etat);
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
        // une brève attente de données (fréquente sur smartphone) ne doit pas interrompre les autres pistes :
        // on ne suspend que si la piste manque encore de données après DELAI_ATTENTE
        if (!etat.joue) { return; }
        setTimeout(function () {
            if (!etat.joue || audio.readyState >= 3 || !etat.audios.includes(audio)) { return; }
            etat.attente.add(audio);
            suspendre(etat);
        }, DELAI_ATTENTE);
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
        if (caseACocher) { caseACocher.checked = false; caseACocher.disabled = true; majTout(etat.el); }
        if (etat.audios.length === 0) {
            arreter(etat, false);
            etat.el.classList.add("mp-erreur");
            etat.el.querySelector(".mp-play").disabled = true;
            etat.el.querySelector(".mp-temps").textContent = "fichier introuvable";
        }
        else if (etat.joue && etat.attente.size === 0) { reprendre(etat); }
    }

    function vitesseNormale(etat) {
        for (const audio of etat.audios) { if (audio.playbackRate !== 1) { audio.playbackRate = 1; } }
    }

    function resynchroniser(etat) {
        // lecteur retiré de la page (autre titre affiché, liste rechargée) : on arrête la musique
        if (!etat.el.isConnected) { arreter(etat, false); return; }
        if (!etat.joue || etat.attente.size > 0) { return; }
        const m = maitre(etat);
        if (m.paused || m.seeking) { return; }
        for (const audio of etat.audios) {
            if (audio === m || audio.seeking) { continue; }
            const ecart = audio.currentTime - m.currentTime;   // > 0 : la piste est en avance
            if (Math.abs(ecart) > ECART_SAUT) {
                audio.playbackRate = 1;
                audio.currentTime = m.currentTime;
            }
            else if (Math.abs(ecart) > (audio.playbackRate !== 1 ? ECART_RATTRAPE : ECART_TOLERE)) {
                const correction = Math.max(-CORRECTION_MAX, Math.min(CORRECTION_MAX, ecart / DUREE_RATTRAPAGE));
                // jamais exactement 1 pendant un rattrapage (1 signifie « pas de rattrapage en cours »)
                audio.playbackRate = Math.abs(correction) < 0.002 ? 1 - Math.sign(correction) * 0.002 : 1 - correction;
            }
            else if (audio.playbackRate !== 1) {
                audio.playbackRate = 1;
            }
        }
    }

    function deplacer(etat, temps) {
        vitesseNormale(etat);
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
        else if (cible.matches(".mp-pistes .mp-tout")) {
            // « Tout » : toutes les pistes disponibles prennent son état
            for (const caseACocher of el.querySelectorAll(".mp-pistes input:not(.mp-tout)")) {
                if (!caseACocher.disabled) { caseACocher.checked = cible.checked; }
            }
            cible.indeterminate = false;
            appliquerPistes(el, etat);
        }
        else if (cible.matches(".mp-pistes input")) {
            appliquerPistes(el, etat);
            majTout(el);
        }
    });

    // à appeler avant de remplacer une liste de lecteurs (sinon la musique continue)
    window.lecteurMultipisteToutArreter = function () {
        if (lecteurActif) { arreter(lecteurActif, false); }
    };
})();
