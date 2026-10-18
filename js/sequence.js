/*******************************************************************************
 * Séquences de notes (clé de sol ou de fa) : éditeur, rendu SVG, lecture audio
 * (classe PHP shared\php\classes\lien\Sequence)
 *
 * Stockage : fichier JSON *.seq.json envoyé comme un fichier de lien ordinaire
 *   {"version":1,"cle":"fa","tempo":90,"evenements":[
 *      {"type":"note","pitch":"F#3","nom":"Fa#3","midi":54,"duree":"q"},
 *      {"type":"silence","duree":"h"}, {"type":"barre"} ]}
 *   duree : w ronde, h blanche, q noire, e croche
 *
 * Restitution : <div class="sequence-vue" data-url="..."></div> est dessiné automatiquement
 *   au chargement ; contenu ajouté plus tard (ajax) : SequenceNotes.initialiser(element)
 * Saisie dans une ligne de lien : choisir le type "Séquence" (window.SEQUENCE_TYPE_LIEN)
 *   ouvre l'éditeur ; à la validation le fichier est placé dans l'input fileUpload de la ligne.
 ******************************************************************************/
(function () {
    "use strict";
    const NS = "http://www.w3.org/2000/svg";
    const FR = ["Do", "Ré", "Mi", "Fa", "Sol", "La", "Si"], EN = ["C", "D", "E", "F", "G", "A", "B"];
    const SEM = [0, 2, 4, 5, 7, 9, 11];
    const BASE = {fa: 18, sol: 30};          //rang diatonique de la 1re ligne : Sol2 (fa), Mi4 (sol)
    const LEN = {w: 4, h: 2, q: 1, e: 0.5};  //durées en temps
    const NOM_SILENCE = {w: "pause", h: "demi-pause", q: "soupir", e: "demi-soupir"};
    const B = 120, H = 6, HAUTEUR = 180;     //ligne du bas, demi-interligne, hauteur d'une portée
    const X_DEBUT = 95, PAS_NOTE = 38, PAS_BARRE = 24, MARGE = 20;
    const MARGE_VUE = 24;                    //marge au-dessus et au-dessous de chaque portée en lecture
    const POS_MIN = -4, POS_MAX = 12;        //positions saisissables (2 lignes supplémentaires)
    const TREBLE = "M12,28 L13,4 C13,1 17,1 16,5 C15,9 7,12 7,18 C7,22 10,24 13,24 C16,24 18,22 18,19.5 "
                 + "C18,17 16,15.5 13.5,15.5 C11,15.5 9.5,17 10,19 M12,28 c0,2.5 -3.5,3 -4,0.5";

    //--------------------------------------------------------------------------
    // MODELE
    //--------------------------------------------------------------------------
    function infoNote(rang, alt) {
    //rang diatonique (octave*7 + degré) + altération -> pitch, nom français, midi
        const L = ((rang % 7) + 7) % 7, o = Math.floor(rang / 7);
        const decal = alt === "#" ? 1 : alt === "b" ? -1 : 0;
        return {pitch: EN[L] + alt + o, nom: FR[L] + alt + o, midi: (o + 1) * 12 + SEM[L] + decal};
    }

    function rangDepuisPitch(pitch) {
    //"F#3" -> {rang, alt}
        const m = /^([A-G])(#|b)?(-?\d)$/.exec(String(pitch || ""));
        if (!m) {return null;}
        return {rang: parseInt(m[3], 10) * 7 + EN.indexOf(m[1]), alt: m[2] || ""};
    }

    function normaliser(data) {
    //contrôle un JSON lu : événements reconnus seulement
        const cle = data && data.cle === "sol" ? "sol" : "fa";
        const tempo = Math.min(240, Math.max(30, parseInt(data && data.tempo, 10) || 90));
        const evenements = [];
        ((data && data.evenements) || []).forEach(function (e) {
            if (!e) {return;}
            if (e.type === "barre") {evenements.push({type: "barre"}); return;}
            const duree = LEN[e.duree] ? e.duree : "q";
            if (e.type === "silence") {evenements.push({type: "silence", duree: duree}); return;}
            const r = rangDepuisPitch(e.pitch);
            if (e.type === "note" && r) {
                evenements.push(Object.assign({type: "note", duree: duree}, infoNote(r.rang, r.alt)));
            }
        });
        return {version: 1, cle: cle, tempo: tempo, evenements: evenements};
    }

    //--------------------------------------------------------------------------
    // RENDU SVG
    //--------------------------------------------------------------------------
    function el(tag, attrs, parent) {
        const e = document.createElementNS(NS, tag);
        Object.keys(attrs).forEach(function (k) {e.setAttribute(k, attrs[k]);});
        parent.appendChild(e);
        return e;
    }
    const y = function (p, dy) {return dy + B - p * H;};

    function dessinerPortee(svg, largeur, dy, cle) {
        for (let l = 0; l <= 8; l += 2) {
            el("line", {x1: 12, x2: largeur - 12, y1: y(l, dy), y2: y(l, dy), stroke: "currentColor"}, svg);
        }
        if (cle === "fa") {
            const yf = y(6, dy);
            el("circle", {cx: 30, cy: yf, r: 4.5, fill: "currentColor"}, svg);
            el("path", {d: "M30," + yf + " c0,-16 30,-16 30,4 c0,20 -16,32 -36,40", fill: "none",
                        stroke: "currentColor", "stroke-width": 3, "stroke-linecap": "round"}, svg);
            el("circle", {cx: 70, cy: y(7, dy), r: 2.5, fill: "currentColor"}, svg);
            el("circle", {cx: 70, cy: y(5, dy), r: 2.5, fill: "currentColor"}, svg);
        } else {
            el("path", {d: TREBLE, transform: "translate(9.4 " + (dy + 65.1) + ") scale(2.2)", fill: "none",
                        stroke: "currentColor", "stroke-width": 1.4, "stroke-linecap": "round"}, svg);
        }
    }

    function dessinerNote(g, x, p, duree, alt, dy) {
        for (let l = -2; l >= p; l -= 2) {
            el("line", {x1: x - 11, x2: x + 11, y1: y(l, dy), y2: y(l, dy), stroke: "currentColor"}, g);
        }
        for (let l = 10; l <= p; l += 2) {
            el("line", {x1: x - 11, x2: x + 11, y1: y(l, dy), y2: y(l, dy), stroke: "currentColor"}, g);
        }
        const creuse = duree === "w" || duree === "h", cy = y(p, dy);
        el("ellipse", {cx: x, cy: cy, rx: 7, ry: 5, transform: "rotate(-20 " + x + " " + cy + ")",
                       fill: creuse ? "none" : "currentColor", stroke: "currentColor",
                       "stroke-width": creuse ? 1.8 : 1}, g);
        if (alt) {
            const t = el("text", {x: x - 21, y: cy + 5, "font-size": 16, fill: "currentColor"}, g);
            t.textContent = alt;
        }
        if (duree !== "w") {
            const haut = p < 4, sx = haut ? x + 6.5 : x - 6.5, ey = haut ? cy - 34 : cy + 34;
            el("line", {x1: sx, x2: sx, y1: cy, y2: ey, stroke: "currentColor", "stroke-width": 1.4}, g);
            if (duree === "e") {
                el("path", {d: haut ? "M" + sx + "," + ey + " q4,10 12,16" : "M" + sx + "," + ey + " q4,-10 12,-16",
                            fill: "none", stroke: "currentColor", "stroke-width": 2}, g);
            }
        }
    }

    function dessinerSilence(g, x, duree, dy) {
        if (duree === "w") {
            el("rect", {x: x - 7, y: y(6, dy), width: 14, height: 5, fill: "currentColor"}, g);
        } else if (duree === "h") {
            el("rect", {x: x - 7, y: y(4, dy) - 5, width: 14, height: 5, fill: "currentColor"}, g);
        } else if (duree === "q") {
            el("path", {d: "M" + (x - 3) + "," + y(7, dy) + " l6,8 l-6,7 l6,8 q-9,-3 -4,7", fill: "none",
                        stroke: "currentColor", "stroke-width": 2.4, "stroke-linejoin": "round"}, g);
        } else {
            el("circle", {cx: x - 3, cy: y(5, dy), r: 2.6, fill: "currentColor"}, g);
            el("path", {d: "M" + (x - 3) + "," + (y(5, dy) + 1) + " q5,1 8,-4 l-6,17", fill: "none",
                        stroke: "currentColor", "stroke-width": 1.6}, g);
        }
    }

    function dessinerBarre(g, x, dy) {
        el("line", {x1: x - 6, x2: x - 6, y1: y(8, dy), y2: y(0, dy), stroke: "currentColor", "stroke-width": 1.4}, g);
    }

    function dessinerEvenement(g, e, x, cle, dy) {
        if (e.type === "note") {
            const r = rangDepuisPitch(e.pitch);
            dessinerNote(g, x, r.rang - BASE[cle], e.duree, r.alt, dy);
        } else if (e.type === "silence") {
            dessinerSilence(g, x, e.duree, dy);
        } else {
            dessinerBarre(g, x, dy);
        }
    }

    const pas = function (e) {return e.type === "barre" ? PAS_BARRE : PAS_NOTE;};

    function lignes(evenements, largeur) {
    //répartit les événements en portées : on revient à la ligne entre deux mesures,
    //ou dans une mesure trop longue pour tenir sur une portée
        const mesures = [[]];
        evenements.forEach(function (e) {
            mesures[mesures.length - 1].push(e);
            if (e.type === "barre") {mesures.push([]);}
        });
        const resultat = [[]];
        let x = X_DEBUT;
        mesures.forEach(function (mesure) {
            const l = mesure.reduce(function (s, e) {return s + pas(e);}, 0);
            if (x + l > largeur - MARGE && resultat[resultat.length - 1].length > 0) {
                resultat.push([]);
                x = X_DEBUT;
            }
            mesure.forEach(function (e) {
                if (x + pas(e) > largeur - MARGE && resultat[resultat.length - 1].length > 0) {
                    resultat.push([]);
                    x = X_DEBUT;
                }
                resultat[resultat.length - 1].push(e);
                x += pas(e);
            });
        });
        return resultat;
    }

    function dessiner(svg, data, largeur) {
    //rendu en lecture : plusieurs portées si besoin
        largeur = Math.max(300, largeur || 640);
        const portees = lignes(data.evenements, largeur);
        svg.innerHTML = "";
        let dy = 0;
        portees.forEach(function (evts) {
            //hauteur de chaque portée ajustée aux notes les plus hautes et les plus basses
            let haut = 8, bas = 0;
            evts.forEach(function (e) {
                if (e.type !== "note") {return;}
                const p = rangDepuisPitch(e.pitch).rang - BASE[data.cle];
                haut = Math.max(haut, p); bas = Math.min(bas, p);
            });
            dy += MARGE_VUE - (B - haut * H);     //marge au-dessus du point le plus haut
            dessinerPortee(svg, largeur, dy, data.cle);
            let x = X_DEBUT;
            evts.forEach(function (e) {
                dessinerEvenement(el("g", {}, svg), e, x, data.cle, dy);
                x += pas(e);
            });
            dy = y(bas, dy) + MARGE_VUE;
        });
        svg.setAttribute("viewBox", "0 0 " + largeur + " " + Math.ceil(dy));
    }

    function svgTexte(data, largeur) {
    //SVG autonome sous forme de texte (téléchargement, export)
        const svg = document.createElementNS(NS, "svg");
        svg.setAttribute("xmlns", NS);
        svg.setAttribute("color", "#000");
        dessiner(svg, data, largeur);
        return new XMLSerializer().serializeToString(svg);
    }

    //--------------------------------------------------------------------------
    // AUDIO
    //--------------------------------------------------------------------------
    let audio = null, enCours = [];
    function jouer(data, surFin) {
        arreter();
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === "suspended") {audio.resume();}
        const temps = 60 / data.tempo;
        let t = audio.currentTime + 0.05;
        data.evenements.forEach(function (e) {
            if (e.type === "barre") {return;}
            const d = LEN[e.duree] * temps;
            if (e.type === "note") {
                const o = audio.createOscillator(), g = audio.createGain();
                o.type = "triangle";
                o.frequency.value = 440 * Math.pow(2, (e.midi - 69) / 12);
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(0.3, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + d * 0.95);
                o.connect(g).connect(audio.destination);
                o.start(t);
                o.stop(t + d);
                enCours.push(o);
            }
            t += d;
        });
        const fin = setTimeout(function () {enCours = []; if (surFin) {surFin();}}, (t - audio.currentTime) * 1000);
        enCours.fin = fin;
    }
    function arreter() {
        if (enCours.fin) {clearTimeout(enCours.fin);}
        enCours.forEach(function (o) {try {o.stop();} catch (x) { /* déjà arrêté */ }});
        enCours = [];
    }

    //--------------------------------------------------------------------------
    // RESTITUTION : <div class="sequence-vue" data-url="...">
    //--------------------------------------------------------------------------
    function vue(racine) {
        if (racine.dataset.sequenceInit) {return;}
        racine.dataset.sequenceInit = "1";
        //bouton à gauche de la portée, sans texte (clé et tempo dans l'infobulle)
        racine.innerHTML = '<div class="seq-vue-ligne">'
            + '<button type="button" class="seq-bouton seq-jouer" title="Écouter" aria-label="Écouter">&#9654;</button>'
            + '<svg class="seq-svg"></svg></div><div class="seq-message"></div>';
        const svg = racine.querySelector(".seq-svg"), bouton = racine.querySelector(".seq-jouer");
        const message = racine.querySelector(".seq-message");
        let data = null;
        const redessiner = function () {if (data) {dessiner(svg, data, Math.round(svg.clientWidth) || 600);}};
        fetch(racine.dataset.url, {cache: "no-cache"})
            .then(function (r) {if (!r.ok) {throw new Error(r.status);} return r.json();})
            .then(function (json) {
                data = normaliser(json);
                //tempo imposé par la page (ex. palier en cours d'un exercice) : prioritaire sur celui du fichier
                if (parseInt(racine.dataset.tempo, 10) > 0) {data.tempo = normaliser({tempo: racine.dataset.tempo}).tempo;}
                bouton.title = "Écouter · " + (data.cle === "sol" ? "clé de sol" : "clé de fa") + " · " + data.tempo + " bpm";
                redessiner();
                if (window.ResizeObserver) {new ResizeObserver(redessiner).observe(racine);}
            })
            .catch(function () {message.textContent = "Séquence introuvable ou illisible.";});
        bouton.addEventListener("click", function () {
            if (!data) {return;}
            if (bouton.classList.contains("on")) {arreter(); remettreBouton(bouton); return;}
            if (boutonActif) {remettreBouton(boutonActif);}   //une seule séquence jouée à la fois
            boutonActif = bouton;
            bouton.classList.add("on");
            bouton.innerHTML = "&#9632;";
            jouer(data, function () {remettreBouton(bouton);});
        });
    }

    let boutonActif = null;
    function remettreBouton(bouton) {
        bouton.classList.remove("on");
        bouton.innerHTML = "&#9654;";
        if (boutonActif === bouton) {boutonActif = null;}
    }

    function observer() {
    //contenu ajouté plus tard (détail chargé en ajax, ex. player de séance) : séquences dessinées automatiquement ;
    //la séquence en cours de lecture qui disparaît de la page est arrêtée
        if (!window.MutationObserver) {return;}
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) {return;}
                    if (n.matches(".sequence-vue[data-url]")) {vue(n);} else {initialiser(n);}
                });
            });
            if (boutonActif && !document.body.contains(boutonActif)) {arreter(); boutonActif = null;}
        }).observe(document.body, {childList: true, subtree: true});
    }

    function initialiser(contexte) {
        (contexte || document).querySelectorAll(".sequence-vue[data-url]").forEach(vue);
    }

    //--------------------------------------------------------------------------
    // EDITEUR (modale #sequenceModale, générée par Sequence::modaleEditeur)
    //--------------------------------------------------------------------------
    const editeur = {
        data: null, outil: "note", duree: "q", alt: "", survol: null, surValider: null,

        racine: function () {return document.getElementById("sequenceModale");},
        q: function (sel) {return this.racine().querySelectorAll(sel);},

        ouvrir: function (data, surValider) {
            const r = this.racine();
            if (!r) {return;}
            this.data = normaliser(data || {cle: "sol", tempo: lireTempoPage(), evenements: []});
            this.surValider = surValider;
            this.outil = "note"; this.duree = "q"; this.alt = ""; this.survol = null;
            r.querySelector(".seq-tempo").value = this.data.tempo;
            this.message("");
            this.synchroniser();
            this.rendre();
            if (window.bootstrap && bootstrap.Modal) {bootstrap.Modal.getOrCreateInstance(r).show();}
            else {r.style.display = "block";}
        },

        fermer: function () {
            arreter();
            const r = this.racine();
            if (window.bootstrap && bootstrap.Modal) {bootstrap.Modal.getOrCreateInstance(r).hide();}
            else {r.style.display = "none";}
        },

        message: function (texte) {this.racine().querySelector(".seq-erreur").textContent = texte || "";},

        synchroniser: function () {
            const s = this;
            this.q("[data-cle]").forEach(function (b) {b.classList.toggle("on", b.dataset.cle === s.data.cle);});
            this.q("[data-outil]").forEach(function (b) {
                b.classList.toggle("on", b.dataset.outil === s.outil && (s.outil === "barre" || b.dataset.duree === s.duree));
            });
            this.q("[data-alt]").forEach(function (b) {b.classList.toggle("on", b.dataset.alt === s.alt);});
        },

        largeur: function () {
            return Math.max(640, this.data.evenements.reduce(function (x, e) {return x + pas(e);}, X_DEBUT) + PAS_NOTE + 40);
        },

        rendre: function () {
        //éditeur : une seule portée qui s'allonge (défilement horizontal)
            const svg = this.racine().querySelector(".seq-svg"), largeur = this.largeur(), cle = this.data.cle;
            svg.innerHTML = "";
            svg.setAttribute("viewBox", "0 0 " + largeur + " " + HAUTEUR);
            svg.style.width = largeur + "px";
            dessinerPortee(svg, largeur, 0, cle);
            let x = X_DEBUT;
            this.data.evenements.forEach(function (e) {dessinerEvenement(el("g", {}, svg), e, x, cle, 0); x += pas(e);});
            if (this.survol !== null) {
                const g = el("g", {opacity: 0.35}, svg);
                if (this.outil === "note") {dessinerNote(g, x, this.survol, this.duree, this.alt, 0);}
                else if (this.outil === "silence") {dessinerSilence(g, x, this.duree, 0);}
                else {dessinerBarre(g, x, 0);}
            }
            this.racine().querySelector(".seq-noms").textContent = this.data.evenements.length === 0
                ? "Cliquez sur la portée pour ajouter un élément"
                : this.data.evenements.map(function (e) {
                    return e.type === "note" ? e.nom : e.type === "silence" ? NOM_SILENCE[e.duree] : "|";
                }).join("  ");
            const zone = this.racine().querySelector(".seq-zone");
            zone.scrollLeft = zone.scrollWidth;
        },

        position: function (evt) {
            const svg = this.racine().querySelector(".seq-svg"), r = svg.getBoundingClientRect();
            const sy = (evt.clientY - r.top) * HAUTEUR / r.height;
            return Math.max(POS_MIN, Math.min(POS_MAX, Math.round((B - sy) / H)));
        },

        ajouter: function (evt) {
            this.message("");
            const ev = this.data.evenements;
            if (this.outil === "note") {
                ev.push(Object.assign({type: "note", duree: this.duree},
                                      infoNote(this.position(evt) + BASE[this.data.cle], this.alt)));
            } else if (this.outil === "silence") {
                ev.push({type: "silence", duree: this.duree});
            } else {
                if (ev.length === 0 || ev[ev.length - 1].type === "barre") {
                    this.message("Placez une note ou un silence avant la barre de mesure.");
                    return;
                }
                ev.push({type: "barre"});
            }
            this.rendre();
        },

        valider: function () {
            const tempo = parseInt(this.racine().querySelector(".seq-tempo").value, 10);
            if (!(tempo >= 30 && tempo <= 240)) {this.message("Le tempo doit être compris entre 30 et 240."); return;}
            if (!this.data.evenements.some(function (e) {return e.type !== "barre";})) {
                this.message("Saisissez au moins une note ou un silence.");
                return;
            }
            this.data.tempo = tempo;
            const data = normaliser(this.data);
            this.fermer();
            if (this.surValider) {this.surValider(data);}
        },

        brancher: function () {
            const r = this.racine(), s = this;
            if (!r || r.dataset.sequenceInit) {return;}
            r.dataset.sequenceInit = "1";
            r.querySelectorAll("[data-cle]").forEach(function (b) {
                b.addEventListener("click", function () {s.data.cle = b.dataset.cle; s.synchroniser(); s.rendre();});
            });
            r.querySelectorAll("[data-outil]").forEach(function (b) {
                b.addEventListener("click", function () {
                    s.outil = b.dataset.outil;
                    if (b.dataset.duree) {s.duree = b.dataset.duree;}
                    s.synchroniser(); s.rendre();
                });
            });
            r.querySelectorAll("[data-alt]").forEach(function (b) {
                b.addEventListener("click", function () {s.alt = s.alt === b.dataset.alt ? "" : b.dataset.alt; s.synchroniser(); s.rendre();});
            });
            const svg = r.querySelector(".seq-svg");
            svg.addEventListener("mousemove", function (e) {s.survol = s.position(e); s.rendre();});
            svg.addEventListener("mouseleave", function () {s.survol = null; s.rendre();});
            svg.addEventListener("click", function (e) {s.ajouter(e);});
            r.querySelector(".seq-annuler").addEventListener("click", function () {s.message(""); s.data.evenements.pop(); s.rendre();});
            r.querySelector(".seq-effacer").addEventListener("click", function () {s.message(""); s.data.evenements = []; s.rendre();});
            r.querySelector(".seq-ecouter").addEventListener("click", function () {
                if (!s.data.evenements.some(function (e) {return e.type === "note";})) {s.message("Ajoutez des notes d'abord."); return;}
                s.message("");
                s.data.tempo = parseInt(r.querySelector(".seq-tempo").value, 10) || s.data.tempo;
                jouer(s.data);
            });
            r.querySelector(".seq-valider").addEventListener("click", function () {s.valider();});
            r.querySelector(".seq-tempo").addEventListener("input", function () {s.message("");});
            r.addEventListener("keydown", function (e) {
                if (e.target.tagName === "INPUT") {return;}
                if ((e.ctrlKey && e.key === "z") || e.key === "Backspace") {
                    e.preventDefault(); s.data.evenements.pop(); s.rendre();
                }
            });
            r.addEventListener("hidden.bs.modal", arreter);
        }
    };

    function lireTempoPage() {
    //tempo par défaut : champ BPM de la page s'il existe (titre : tempo, exercice : tempoInitial)
        const champ = document.getElementById("tempo") || document.getElementById("tempoInitial");
        return champ ? parseInt(champ.value, 10) || 90 : 90;
    }

    //--------------------------------------------------------------------------
    // LIGNE DE LIEN DE TYPE "SEQUENCE" (Lienhtml + lien.js)
    //--------------------------------------------------------------------------
    function nomFichier(ligne) {
        const desc = (document.getElementById("descriptionLien" + ligne) || {}).value || "sequence";
        const base = desc.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase()
                         .replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").substring(0, 40) || "sequence";
        return base + "-" + Date.now() + ".seq.json";
    }

    function ligneTypeChange(select) {
        const ligne = select.id.substring("idTypeLien".length);
        const fichier = document.getElementById("fileUpload" + ligne);
        if (!fichier) {return;}
        const cellule = fichier.parentNode;
        let bouton = cellule.querySelector(".seq-saisir");
        if (String(select.value) !== String(window.SEQUENCE_TYPE_LIEN)) {
            //autre type : on rend la main à lien.js
            if (bouton) {bouton.remove(); fichier.value = ""; fichier.dataset.sequence = "";}
            return;
        }
        fichier.style.display = "none";
        fichier.required = false;          //contrôle fait à la validation du formulaire (input caché)
        fichier.disabled = false;
        if (!bouton) {
            bouton = document.createElement("button");
            bouton.type = "button";
            bouton.className = "btn btn-outline-primary btn-sm seq-saisir";
            bouton.innerHTML = '<i class="bi bi-music-note-beamed"></i> Saisir la séquence';
            cellule.appendChild(bouton);
            bouton.addEventListener("click", function () {
                const existant = fichier.dataset.sequence ? JSON.parse(fichier.dataset.sequence) : null;
                editeur.ouvrir(existant, function (data) {
                    const texte = JSON.stringify(data);
                    const dt = new DataTransfer();
                    dt.items.add(new File([texte], nomFichier(ligne), {type: "application/json"}));
                    fichier.files = dt.files;
                    fichier.dataset.sequence = texte;
                    bouton.classList.remove("btn-outline-danger");
                    const alerte = document.getElementById("sequenceAlerte");
                    if (alerte) {alerte.remove();}
                    const n = data.evenements.filter(function (e) {return e.type !== "barre";}).length;
                    bouton.innerHTML = '<i class="bi bi-check2"></i> Séquence saisie (' + n + ' éléments) · modifier';
                    const desc = document.getElementById("descriptionLien" + ligne);
                    if (desc && !desc.value) {desc.value = "Séquence";}
                    if (typeof window.jsLienModification === "function") {window.jsLienModification(parseInt(ligne, 10));}
                });
            });
        }
    }

    function controlerFormulaire(evt) {
    //une ligne "Séquence" sans saisie bloque l'envoi
        const manquantes = Array.prototype.filter.call(evt.target.querySelectorAll(".seq-saisir"), function (b) {
            const f = b.parentNode.querySelector('input[type="file"]');
            return f && f.files.length === 0;
        });
        if (manquantes.length > 0) {
            evt.preventDefault();
            evt.stopPropagation();
            manquantes[0].classList.add("btn-outline-danger");
            manquantes[0].focus();
            alertePage("Saisissez la séquence de notes avant d'enregistrer, ou supprimez la ligne.");
        }
    }

    function alertePage(texte) {
        let zone = document.getElementById("sequenceAlerte");
        if (!zone) {
            zone = document.createElement("div");
            zone.id = "sequenceAlerte";
            zone.className = "alert alert-danger mt-2";
            const liens = document.getElementById("liens");
            (liens ? liens.closest(".row") : document.body).after(zone);
        }
        zone.textContent = texte;
    }

    //--------------------------------------------------------------------------
    // DEMARRAGE
    //--------------------------------------------------------------------------
    document.addEventListener("DOMContentLoaded", function () {
        initialiser(document);
        observer();
        editeur.brancher();
        document.addEventListener("change", function (e) {
            if (e.target.matches && e.target.matches('select[id^="idTypeLien"]')) {ligneTypeChange(e.target);}
        });
        document.addEventListener("submit", controlerFormulaire, true);
    });

    window.SequenceNotes = {
        initialiser: initialiser, dessiner: dessiner, svgTexte: svgTexte, normaliser: normaliser,
        jouer: jouer, arreter: arreter, editer: function (data, surValider) {editeur.brancher(); editeur.ouvrir(data, surValider);}
    };
})();
