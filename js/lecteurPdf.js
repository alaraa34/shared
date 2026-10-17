//---------Lecteur PDF à défilement automatique (shared\php\classes\lien\LecteurPdf)---------
//chaque élément .lecteur-pdf est initialisé au chargement ; l'instance est dans element.lecteurPdf
//Le défilement complet dure autant que le MP3 (data-audio), sinon data-duree-defaut (3 min).
//Les boutons -/+ appliquent un facteur à cette durée (x1,2), mémorisé par document dans le navigateur ;
//un clic sur la durée affichée revient à la durée du MP3.
(function () {
    'use strict';
    const PAS = 1.2, FACTEUR_MIN = 0.1, FACTEUR_MAX = 10, DUREE_DEFAUT = 180;

    class LecteurPdf {
        constructor(racine) {
            this.racine = racine;
            this.zone = racine.querySelector('.lpdf-zone');
            this.pages = racine.querySelector('.lpdf-pages');
            this.message = racine.querySelector('.lpdf-message');
            this.affichageDuree = racine.querySelector('.lpdf-vitesse');
            this.boutonLecture = racine.querySelector('.lpdf-lecture');
            this.urlSauvegarde = racine.dataset.urlSauvegarde || '';
            this.dureeDefaut = parseFloat(racine.dataset.dureeDefaut) || DUREE_DEFAUT;
            this.duree = this.dureeDefaut;      //durée du MP3 ou durée par défaut (s)
            this.facteur = 1;                   //réglage manuel : >1 plus rapide, <1 plus lent
            this.enCours = false; this.doc = null; this.url = ''; this.audio = ''; this.position = 0;
            this.toucher = false; this.dernierTemps = 0; this.verrouVeille = null; this.chargement = 0;
            this.brancherEvenements();
            this.afficherDuree();
            if (racine.dataset.url) {this.charger(racine.dataset.url, racine.dataset.audio || '');}
        }

        //------------------------------------------------------------------ chargement / rendu
        async charger(url, audio) {
            //url du PDF ; audio = url du MP3 dont la durée fixe le temps total de défilement
            this.arreter();
            this.url = url || '';
            this.audio = audio || '';
            this.duree = this.dureeDefaut;
            this.facteur = this.facteurMemorise(this.url) || 1;
            const dureeAudio = this.audio ? this.dureeAudio(this.audio) : Promise.resolve(null);
            this.pages.innerHTML = '';
            this.zone.scrollTop = 0; this.position = 0;
            this.doc = null;
            this.afficherDuree();
            if (!this.url) {this.afficherMessage(''); return;}
            if (!window.pdfjsLib) {this.afficherMessage('PDF.js n\'est pas chargé (connexion internet ?)'); return;}
            const numero = ++this.chargement;               //un chargement plus récent annule celui-ci
            this.afficherMessage('Chargement…');
            try {
                const doc = await pdfjsLib.getDocument(this.url).promise;
                if (numero !== this.chargement) {return;}
                this.doc = doc;
                await this.rendre(numero);
                if (numero !== this.chargement) {return;}
                this.duree = (await dureeAudio) || this.dureeDefaut;
                if (numero !== this.chargement) {return;}
                this.afficherDuree();
                this.afficherMessage('');
            } catch (erreur) {
                if (numero === this.chargement) {this.afficherMessage('Impossible d\'afficher ce document');}
                console.log('LecteurPdf : ' + erreur);
            }
        }

        async rendre(numero) {
            //dessine toutes les pages à la largeur de la zone (netteté selon la densité de l'écran)
            const largeur = this.pages.clientWidth || this.zone.clientWidth;
            const densite = Math.min(window.devicePixelRatio || 1, 2);
            for (let i = 1; i <= this.doc.numPages; i++) {
                const page = await this.doc.getPage(i);
                if (numero !== this.chargement) {return;}
                const base = page.getViewport({scale: 1});
                const vue = page.getViewport({scale: (largeur / base.width) * densite});
                const canvas = document.createElement('canvas');
                canvas.width = Math.floor(vue.width); canvas.height = Math.floor(vue.height);
                await page.render({canvasContext: canvas.getContext('2d'), viewport: vue}).promise;
                if (i === 1) {this.pages.replaceChildren(canvas);} else {this.pages.appendChild(canvas);}
            }
        }

        async redessiner() {
            //rotation / redimensionnement : on garde la place relative dans le document
            if (!this.doc) {return;}
            const ratio = this.zone.scrollHeight ? this.zone.scrollTop / this.zone.scrollHeight : 0;
            const numero = ++this.chargement;
            await this.rendre(numero);
            this.zone.scrollTop = ratio * this.zone.scrollHeight;
            this.position = this.zone.scrollTop;
        }

        //------------------------------------------------------------------ défilement
        course() {return Math.max(0, this.zone.scrollHeight - this.zone.clientHeight);}

        dureeEffective() {return this.duree / this.facteur;}

        basculer() {this.enCours ? this.arreter() : this.lancer();}

        lancer() {
            if (!this.doc || this.enCours) {return;}
            if (this.zone.scrollTop >= this.course() - 2) {this.zone.scrollTop = 0;}
            this.enCours = true; this.position = this.zone.scrollTop; this.dernierTemps = 0; this.dernierScroll = undefined;
            this.racine.classList.add('lpdf-en-cours');
            this.boutonLecture.innerHTML = '&#10074;&#10074;';
            this.garderEcranAllume(true);
            requestAnimationFrame(t => this.pas(t));
        }

        arreter() {
            this.enCours = false;
            this.racine.classList.remove('lpdf-en-cours');
            if (this.boutonLecture) {this.boutonLecture.innerHTML = '&#9654;';}
            this.garderEcranAllume(false);
        }

        pas(temps) {
            if (!this.enCours) {return;}
            //défilement extérieur (doigt, molette, barre, clavier) : on repart de la nouvelle place
            if (this.toucher || (this.dernierScroll !== undefined && Math.abs(this.zone.scrollTop - this.dernierScroll) > 2)) {
                this.position = this.zone.scrollTop;
            }
            if (this.dernierTemps && !this.toucher) {
                const dt = Math.min((temps - this.dernierTemps) / 1000, 0.1);
                //vitesse = longueur à parcourir / durée : le temps total reste juste même si la largeur change
                this.position += this.course() / this.dureeEffective() * dt;
                this.zone.scrollTop = this.position;
                if (this.zone.scrollTop >= this.course() - 1) {this.arreter(); return;}
            }
            this.dernierScroll = this.zone.scrollTop;
            this.dernierTemps = temps;
            requestAnimationFrame(t => this.pas(t));
        }

        changerVitesse(multiplicateur) {
            this.facteur = Math.min(FACTEUR_MAX, Math.max(FACTEUR_MIN, this.facteur * multiplicateur));
            this.afficherDuree();
            this.memoriser();
        }

        revenirALaDuree() {
            //oublie le réglage manuel : retour à la durée du MP3 (ou 3 min)
            this.facteur = 1;
            try {localStorage.removeItem(this.cleStockage(this.url));} catch (e) {}
            this.afficherDuree();
            this.memoriser();
        }

        //------------------------------------------------------------------ durée du MP3
        dureeAudio(url) {
            //durée lue dans les métadonnées du MP3 (le fichier n'est pas joué), null si illisible
            return new Promise(resolve => {
                const audio = new Audio();
                const fin = d => {clearTimeout(minuterie); audio.removeAttribute('src'); resolve(d);};
                const minuterie = setTimeout(() => fin(null), 8000);
                audio.preload = 'metadata';
                audio.addEventListener('loadedmetadata', () => fin(isFinite(audio.duration) && audio.duration > 0 ? audio.duration : null));
                audio.addEventListener('error', () => fin(null));
                audio.src = url;
            });
        }

        //------------------------------------------------------------------ réglage mémorisé
        cleStockage(url) {return 'lecteurPdf:' + url;}

        facteurMemorise(url) {
            try {return parseFloat(localStorage.getItem(this.cleStockage(url))) || null;} catch (e) {return null;}
        }

        memoriser() {
            if (!this.url) {return;}
            try {
                if (this.facteur === 1) {localStorage.removeItem(this.cleStockage(this.url));}
                else {localStorage.setItem(this.cleStockage(this.url), String(this.facteur));}
            } catch (e) {}
            if (this.urlSauvegarde) {
                const donnees = new FormData();
                donnees.append('url', this.url); donnees.append('facteur', String(this.facteur));
                fetch(this.urlSauvegarde, {method: 'POST', body: donnees}).catch(() => {});
            }
        }

        //------------------------------------------------------------------ affichage / appareil
        formaterDuree(secondes) {
            const s = Math.round(secondes);
            return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        }

        afficherDuree() {
            //temps total du défilement complet au réglage actuel ; * = réglage manuel
            if (!this.affichageDuree) {return;}
            const manuel = Math.abs(this.facteur - 1) > 0.001;
            this.affichageDuree.textContent = '⏱ ' + this.formaterDuree(this.dureeEffective()) + (manuel ? ' *' : '');
            this.affichageDuree.title = 'Durée du défilement complet ('
                + (this.audio ? 'MP3 : ' : 'par défaut : ') + this.formaterDuree(this.duree) + ')'
                + (manuel ? ' — cliquer pour revenir à cette durée' : '');
        }

        afficherMessage(texte) {this.message.textContent = texte;}

        async garderEcranAllume(oui) {
            try {
                if (oui && 'wakeLock' in navigator && !this.verrouVeille) {
                    this.verrouVeille = await navigator.wakeLock.request('screen');
                    this.verrouVeille.addEventListener('release', () => {this.verrouVeille = null;});
                } else if (!oui && this.verrouVeille) {
                    await this.verrouVeille.release(); this.verrouVeille = null;
                }
            } catch (e) {this.verrouVeille = null;}
        }

        pleinEcran() {
            const el = this.racine;
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            } else if (el.requestFullscreen) {el.requestFullscreen();}
            else if (el.webkitRequestFullscreen) {el.webkitRequestFullscreen();}
        }

        brancherEvenements() {
            const clic = (selecteur, action) => {
                const b = this.racine.querySelector(selecteur);
                if (b) {b.addEventListener('click', e => {e.preventDefault(); action();});}
            };
            clic('.lpdf-lecture', () => this.basculer());
            clic('.lpdf-moins', () => this.changerVitesse(1 / PAS));
            clic('.lpdf-plus', () => this.changerVitesse(PAS));
            clic('.lpdf-vitesse', () => this.revenirALaDuree());
            const boutonPE = this.racine.querySelector('.lpdf-plein-ecran');
            if (boutonPE) {
                if (!(this.racine.requestFullscreen || this.racine.webkitRequestFullscreen)) {boutonPE.style.display = 'none';}
                else {boutonPE.addEventListener('click', () => this.pleinEcran());}
            }
            //le doigt (ou la souris sur la barre de défilement) reprend la main
            const debut = () => {this.toucher = true;};
            const fin = () => {this.toucher = false; this.position = this.zone.scrollTop;};
            this.zone.addEventListener('touchstart', debut, {passive: true});
            this.zone.addEventListener('touchend', fin, {passive: true});
            this.zone.addEventListener('touchcancel', fin, {passive: true});
            this.zone.addEventListener('mousedown', debut);
            window.addEventListener('mouseup', fin);
            this.zone.addEventListener('scroll', () => {if (!this.enCours) {this.position = this.zone.scrollTop;}}, {passive: true});
            //clavier (si un seul lecteur dans la page et pas de saisie en cours)
            document.addEventListener('keydown', e => {
                if (document.querySelectorAll('.lecteur-pdf').length > 1) {return;}
                if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target || {}).tagName || '')) {return;}
                if (e.code === 'Space') {e.preventDefault(); this.basculer();}
                else if (e.code === 'ArrowUp') {e.preventDefault(); this.changerVitesse(PAS);}
                else if (e.code === 'ArrowDown') {e.preventDefault(); this.changerVitesse(1 / PAS);}
            });
            //rotation / redimensionnement
            let attente = null, largeur = this.pages.clientWidth;
            window.addEventListener('resize', () => {
                clearTimeout(attente);
                attente = setTimeout(() => {
                    if (Math.abs(this.pages.clientWidth - largeur) > 20) {largeur = this.pages.clientWidth; this.redessiner();}
                }, 300);
            });
            //retour sur l'onglet : on reprend le verrou de veille si la lecture continue
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.enCours) {this.garderEcranAllume(true);}
            });
        }
    }

    window.LecteurPdf = LecteurPdf;
    const initialiser = () => document.querySelectorAll('.lecteur-pdf').forEach(el => {
        if (!el.lecteurPdf) {el.lecteurPdf = new LecteurPdf(el);}
    });
    if (document.readyState === 'loading') {document.addEventListener('DOMContentLoaded', initialiser);} else {initialiser();}
})();
