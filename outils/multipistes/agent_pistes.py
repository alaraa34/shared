"""
Agent de génération des pistes du lecteur multipiste (theBand, training...)

Tourne sur un PC où Demucs est installé. En boucle :
  1. demande au site le prochain MP3 à traiter (page "Générer les pistes" : icônes orange)
  2. le télécharge, le sépare en 6 pistes avec Demucs (modèle htdemucs_6s)
  3. si une transposition est demandée (tona de scène), transpose les pistes avec ffmpeg
     (filtre rubberband : la hauteur change, pas le tempo ; batterie recopiée telle quelle)
  4. renvoie les pistes une par une au site, puis signale la fin (icône verte) ou l'erreur
Quand seule la tona de scène a changé, l'agent réutilise les pistes d'origine du site (quelques secondes).

Utilisation (dans l'environnement virtuel où Demucs est installé) :
    python agent_pistes.py              tourne en boucle jusqu'à Ctrl+C
    python agent_pistes.py --une-fois   traite la file d'attente puis s'arrête

Réglages : agent_pistes.ini dans le même dossier (copier agent_pistes.ini.exemple).
Plusieurs sites (theBand, training...) : une section [site <nom>] par site avec url_projet et cle ;
l'agent les interroge à tour de rôle et ne fait qu'un traitement à la fois.
Seule la bibliothèque standard de Python est utilisée, en plus de Demucs.
"""
import argparse
import configparser
import json
import re
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from datetime import datetime
from pathlib import Path

DOSSIER = Path(__file__).resolve().parent
VERSION = 2  # 2 : transposition (tona de scène) ; le site refuse les travaux de transposition aux agents plus anciens


def journal(message):
    print(f"{datetime.now():%d/%m %H:%M:%S}  {message}", flush=True)


class ErreurAgent(Exception):
    pass


class Agent:
    def __init__(self, config, nom_site=None):
        """réglages communs dans [agent] ; url_projet et cle dans [site <nom>] si plusieurs sites, sinon dans [agent]"""
        section = config["agent"]
        site = config[nom_site] if nom_site else section
        self.site = nom_site[5:].strip() if nom_site else ""
        self.url_projet = (site.get("url_projet") or "").rstrip("/") + "/"
        self.cle = (site.get("cle") or "").strip()
        self.nom = section.get("nom_agent", "").strip() or socket.gethostname()
        self.nom = "".join(c for c in self.nom if c.isalnum() or c in "-_")[:40]
        self.modele = section.get("modele", "htdemucs_6s")
        self.bitrate = section.getint("mp3_bitrate", 128)
        self.attente = section.getint("attente_secondes", 30)
        self.travail = Path(section.get("dossier_travail", str(DOSSIER / "travail")))
        self.options_demucs = section.get("options_demucs", "").split()
        self.ffmpeg = section.get("ffmpeg", "ffmpeg").strip() or "ffmpeg"
        self._rubberband = None  # ffmpeg dispose-t-il du filtre rubberband ? (vérifié au premier besoin)
        if len(self.cle) < 20:
            raise ErreurAgent(f"{self.prefixe()}La clé (cle) doit faire au moins 20 caractères : celle du fichier ENVIRagent.txt du site")

    def prefixe(self):
        return f"[{self.site}] " if self.site else ""

    # ------------------------------------------------------------------ appels au site
    def _url(self, fonction):
        return f"{self.url_projet}index.php?fct={fonction}&ctr=song"

    def _lire_reponse(self, requete, delai):
        # certains hébergeurs refusent l'identité par défaut de Python (Python-urllib)
        requete.add_header("User-Agent", f"Mozilla/5.0 (compatible; agent_pistes/{VERSION})")
        texte = ""
        adresse = requete.full_url
        try:
            with urllib.request.urlopen(requete, timeout=delai) as reponse:
                adresse = reponse.geturl()
                texte = reponse.read().decode("utf-8", "replace")
                return json.loads(texte)
        except urllib.error.HTTPError as erreur:
            texte = erreur.read().decode("utf-8", "replace")
            try:
                message = json.loads(texte).get("message", texte)
            except ValueError:
                message = " ".join(re.sub(r"<[^>]+>", " ", texte).split())[:300]
            raise ErreurAgent(f"Le site a répondu {erreur.code} : {message}") from None
        except ValueError:
            # page HTML ou message PHP au lieu du JSON : on montre l'adresse réellement appelée et le début de la réponse
            debut = " ".join(re.sub(r"<[^>]+>", " ", texte).split())[:300]
            raise ErreurAgent(f"Réponse du site illisible (pas du JSON) : vérifier url_projet\n"
                              f"    adresse appelée : {adresse}\n"
                              f"    début de la réponse : {debut or '(vide)'}") from None

    def appeler(self, fonction, champs=None, delai=60):
        donnees = {"cle": self.cle, "agent": self.nom, "version": VERSION, **(champs or {})}
        corps = urllib.parse.urlencode(donnees).encode("utf-8")
        requete = urllib.request.Request(self._url(fonction), data=corps, method="POST")
        return self._lire_reponse(requete, delai)

    def envoyer_fichier(self, fonction, champs, chemin, delai=300):
        limite = uuid.uuid4().hex
        morceaux = []
        for nom, valeur in {"cle": self.cle, "agent": self.nom, **champs}.items():
            morceaux.append(f'--{limite}\r\nContent-Disposition: form-data; name="{nom}"\r\n\r\n{valeur}\r\n'.encode("utf-8"))
        morceaux.append(f'--{limite}\r\nContent-Disposition: form-data; name="fichier"; filename="{chemin.name}"\r\n'
                        f'Content-Type: audio/mpeg\r\n\r\n'.encode("utf-8"))
        morceaux.append(chemin.read_bytes())
        morceaux.append(f"\r\n--{limite}--\r\n".encode("utf-8"))
        requete = urllib.request.Request(self._url(fonction), data=b"".join(morceaux), method="POST",
                                         headers={"Content-Type": f"multipart/form-data; boundary={limite}"})
        reponse = self._lire_reponse(requete, delai)
        if not reponse.get("ok"):
            raise ErreurAgent(reponse.get("message", "envoi refusé"))

    # ------------------------------------------------------------------ traitement
    def traiter(self, tache):
        id_lien = tache["idLien"]
        dossier = self.travail / str(id_lien)
        shutil.rmtree(dossier, ignore_errors=True)
        dossier.mkdir(parents=True)
        debut = time.time()
        try:
            tona = int(tache.get("tona", 0))
            if tache.get("mode", "complet") == "complet":
                origine = self.separer(tache, dossier)
                for piste in tache["pistes"]:
                    self.envoyer_piste(id_lien, piste, origine / f"{piste}.mp3", 0)
            else:
                origine = self.telecharger_origine(tache, dossier)

            if tona != 0:
                transposees = dossier / "transposees"
                transposees.mkdir()
                journal(f"{self.prefixe()}Lien {id_lien} : transposition de {tona:+d} demi-ton(s)")
                for piste in tache["pistes"]:
                    destination = transposees / f"{piste}.mp3"
                    self.transposer(origine / f"{piste}.mp3", destination, tona,
                                    piste in tache.get("nonTransposees", []), piste == "vocals")
                    self.envoyer_piste(id_lien, piste, destination, tona)

            duree = int(time.time() - debut)
            self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 1, "message": "",
                                                 "modele": self.modele, "duree": duree})
            journal(f"{self.prefixe()}Lien {id_lien} : terminé en {duree // 60} min {duree % 60:02d} s")
        except Exception as erreur:  # on signale toute erreur au site pour que l'icône passe en rouge
            message = str(erreur) or erreur.__class__.__name__
            journal(f"{self.prefixe()}Lien {id_lien} : ERREUR {message}")
            try:
                self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 0, "message": message[:250]})
            except Exception as erreur2:
                journal(f"Impossible de signaler l'erreur au site : {erreur2}")
        except KeyboardInterrupt:  # Ctrl+C ou fenêtre fermée pendant un traitement : on prévient le site
            journal(f"{self.prefixe()}Lien {id_lien} : arrêt demandé, traitement abandonné")
            try:
                self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 0,
                                                     "message": "Agent arrêté pendant le traitement"}, delai=10)
            except Exception:
                pass
            raise
        finally:
            shutil.rmtree(dossier, ignore_errors=True)

    def separer(self, tache, dossier):
        """télécharge le MP3 et le sépare en pistes avec Demucs ; retourne le dossier des pistes"""
        id_lien = tache["idLien"]
        source = dossier / "source.mp3"
        journal(f"{self.prefixe()}Lien {id_lien} : téléchargement de {urllib.parse.unquote(tache['chemin'])}")
        urllib.request.urlretrieve(self.url_projet + tache["chemin"], source)

        journal(f"{self.prefixe()}Lien {id_lien} : séparation avec {self.modele} (plusieurs minutes)...")
        commande = [sys.executable, "-m", "demucs", "-n", self.modele, "--mp3",
                    "--mp3-bitrate", str(self.bitrate), "-o", str(dossier / "sortie"),
                    *self.options_demucs, str(source)]
        resultat = subprocess.run(commande, capture_output=True, text=True, encoding="utf-8", errors="replace")
        if resultat.returncode != 0:
            derniere_ligne = (resultat.stderr.strip().splitlines() or ["erreur inconnue"])[-1]
            raise ErreurAgent(f"Demucs a échoué : {derniere_ligne}")
        sortie = dossier / "sortie" / self.modele / "source"
        for piste in tache["pistes"]:
            if not (sortie / f"{piste}.mp3").is_file():
                raise ErreurAgent(f"Piste {piste} absente de la sortie de Demucs")
        return sortie

    def telecharger_origine(self, tache, dossier):
        """transposition seule : récupère les pistes d'origine déjà générées sur le site"""
        origine = dossier / "t0"
        origine.mkdir()
        journal(f"{self.prefixe()}Lien {tache['idLien']} : récupération des pistes d'origine")
        for piste, chemin in tache["cheminsT0"].items():
            urllib.request.urlretrieve(self.url_projet + chemin, origine / f"{piste}.mp3")
        return origine

    def envoyer_piste(self, id_lien, piste, fichier, tona):
        dossier = "t0" if tona == 0 else f"t{tona:+d}"
        journal(f"{self.prefixe()}Lien {id_lien} : envoi de {piste} ({dossier}, {fichier.stat().st_size // 1024} Ko)")
        self.envoyer_fichier("pistesAgentRecevoir", {"idLien": id_lien, "piste": piste, "tona": tona}, fichier)

    def rubberband_disponible(self):
        """vrai si ffmpeg contient le filtre rubberband (meilleure qualité de transposition)"""
        if self._rubberband is None:
            try:
                filtres = subprocess.run([self.ffmpeg, "-hide_banner", "-filters"], capture_output=True,
                                         text=True, encoding="utf-8", errors="replace").stdout
            except FileNotFoundError:
                raise ErreurAgent(f"ffmpeg introuvable ({self.ffmpeg}) : l'installer (winget install Gyan.FFmpeg) "
                                  "ou indiquer son chemin dans agent_pistes.ini (ffmpeg = ...)") from None
            self._rubberband = " rubberband " in filtres
            if not self._rubberband:
                journal("ffmpeg sans le filtre rubberband : transposition de qualité moindre")
        return self._rubberband

    def transposer(self, source, destination, demi_tons, copie_seule, voix):
        """transpose une piste sans changer son tempo ; copie_seule : piste recopiée telle quelle (batterie)"""
        if copie_seule:
            shutil.copyfile(source, destination)
            return
        rapport = 2 ** (demi_tons / 12)
        if self.rubberband_disponible():
            filtre = f"rubberband=pitch={rapport:.6f}:pitchq=quality:formant={'preserved' if voix else 'shifted'}"
        else:
            filtre = f"asetrate=44100*{rapport:.6f},aresample=44100,atempo={1 / rapport:.6f}"
        commande = [self.ffmpeg, "-hide_banner", "-loglevel", "error", "-y", "-i", str(source),
                    "-af", filtre, "-ar", "44100", "-b:a", f"{self.bitrate}k", str(destination)]
        resultat = subprocess.run(commande, capture_output=True, text=True, encoding="utf-8", errors="replace")
        if resultat.returncode != 0 or not destination.is_file():
            derniere_ligne = (resultat.stderr.strip().splitlines() or ["erreur inconnue"])[-1]
            raise ErreurAgent(f"Transposition de {source.name} impossible : {derniere_ligne}")

    def demander_travail(self):
        """prochain travail du site, ou None s'il n'y a rien (ou si le site ne répond pas)"""
        try:
            tache = self.appeler("pistesAgentProchain")
        except (ErreurAgent, OSError) as erreur:
            journal(f"{self.prefixe()}Site injoignable ou refus : {erreur}")
            return None
        return tache if tache.get("idLien") else None


def tourner(agents, une_fois):
    """interroge les sites à tour de rôle ; un seul traitement à la fois sur le PC"""
    for agent in agents:
        journal(f"{agent.prefixe()}Agent {agent.nom} (version {VERSION}) démarré sur {agent.url_projet}")
    journal("Ctrl+C pour arrêter")
    while True:
        travail = False
        for agent in agents:
            tache = agent.demander_travail()
            if tache:
                agent.traiter(tache)
                travail = True
        if travail:
            continue
        if une_fois:
            journal("Plus rien à traiter.")
            return
        time.sleep(min(agent.attente for agent in agents))


def main():
    parametres = argparse.ArgumentParser(description="Agent de génération des pistes du lecteur multipiste")
    parametres.add_argument("--une-fois", action="store_true", help="traite la file d'attente puis s'arrête")
    parametres.add_argument("--config", default=str(DOSSIER / "agent_pistes.ini"), help="fichier de réglages")
    arguments = parametres.parse_args()

    config = configparser.ConfigParser()
    if not config.read(arguments.config, encoding="utf-8"):
        sys.exit(f"Fichier de réglages introuvable : {arguments.config} (copier agent_pistes.ini.exemple)")
    try:
        sites = [nom for nom in config.sections() if nom.lower().startswith("site ")]
        agents = [Agent(config, nom) for nom in sites] if sites else [Agent(config)]
        tourner(agents, arguments.une_fois)
    except ErreurAgent as erreur:
        sys.exit(str(erreur))
    except KeyboardInterrupt:
        journal("Arrêt demandé.")


if __name__ == "__main__":
    main()
