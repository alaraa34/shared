"""
Agent de génération des pistes du lecteur multipiste (theBand, training...)

Tourne sur un PC où Demucs est installé. En boucle :
  1. demande au site le prochain MP3 à traiter (page "Générer les pistes" : icônes orange)
  2. le télécharge, le sépare en 6 pistes avec Demucs (modèle htdemucs_6s)
  3. renvoie les pistes une par une au site, puis signale la fin (icône verte) ou l'erreur

Utilisation (dans l'environnement virtuel où Demucs est installé) :
    python agent_pistes.py              tourne en boucle jusqu'à Ctrl+C
    python agent_pistes.py --une-fois   traite la file d'attente puis s'arrête

Réglages : agent_pistes.ini dans le même dossier (copier agent_pistes.ini.exemple).
Seule la bibliothèque standard de Python est utilisée, en plus de Demucs.
"""
import argparse
import configparser
import json
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


def journal(message):
    print(f"{datetime.now():%d/%m %H:%M:%S}  {message}", flush=True)


class ErreurAgent(Exception):
    pass


class Agent:
    def __init__(self, config):
        section = config["agent"]
        self.url_projet = section.get("url_projet").rstrip("/") + "/"
        self.cle = section.get("cle").strip()
        self.nom = section.get("nom_agent", "").strip() or socket.gethostname()
        self.nom = "".join(c for c in self.nom if c.isalnum() or c in "-_")[:40]
        self.modele = section.get("modele", "htdemucs_6s")
        self.bitrate = section.getint("mp3_bitrate", 128)
        self.attente = section.getint("attente_secondes", 30)
        self.travail = Path(section.get("dossier_travail", str(DOSSIER / "travail")))
        self.options_demucs = section.get("options_demucs", "").split()
        if len(self.cle) < 20:
            raise ErreurAgent("La clé (cle) doit faire au moins 20 caractères : celle du fichier ENVIRagent.txt du site")

    # ------------------------------------------------------------------ appels au site
    def _url(self, fonction):
        return f"{self.url_projet}index.php?fct={fonction}&ctr=song"

    def _lire_reponse(self, requete, delai):
        try:
            with urllib.request.urlopen(requete, timeout=delai) as reponse:
                return json.loads(reponse.read().decode("utf-8"))
        except urllib.error.HTTPError as erreur:
            texte = erreur.read().decode("utf-8", "replace")
            try:
                message = json.loads(texte).get("message", texte)
            except ValueError:
                message = texte[:300]
            raise ErreurAgent(f"Le site a répondu {erreur.code} : {message}") from None
        except ValueError:
            raise ErreurAgent("Réponse du site illisible (pas du JSON) : vérifier url_projet") from None

    def appeler(self, fonction, champs=None, delai=60):
        donnees = {"cle": self.cle, "agent": self.nom, **(champs or {})}
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
            source = dossier / "source.mp3"
            journal(f"Lien {id_lien} : téléchargement de {urllib.parse.unquote(tache['chemin'])}")
            urllib.request.urlretrieve(self.url_projet + tache["chemin"], source)

            journal(f"Lien {id_lien} : séparation avec {self.modele} (plusieurs minutes)...")
            commande = [sys.executable, "-m", "demucs", "-n", self.modele, "--mp3",
                        "--mp3-bitrate", str(self.bitrate), "-o", str(dossier / "sortie"),
                        *self.options_demucs, str(source)]
            resultat = subprocess.run(commande, capture_output=True, text=True, encoding="utf-8", errors="replace")
            if resultat.returncode != 0:
                derniere_ligne = (resultat.stderr.strip().splitlines() or ["erreur inconnue"])[-1]
                raise ErreurAgent(f"Demucs a échoué : {derniere_ligne}")

            sortie = dossier / "sortie" / self.modele / "source"
            for piste in tache["pistes"]:
                fichier = sortie / f"{piste}.mp3"
                if not fichier.is_file():
                    raise ErreurAgent(f"Piste {piste} absente de la sortie de Demucs")
                journal(f"Lien {id_lien} : envoi de {piste} ({fichier.stat().st_size // 1024} Ko)")
                self.envoyer_fichier("pistesAgentRecevoir", {"idLien": id_lien, "piste": piste}, fichier)

            duree = int(time.time() - debut)
            self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 1, "message": "",
                                                 "modele": self.modele, "duree": duree})
            journal(f"Lien {id_lien} : terminé en {duree // 60} min {duree % 60:02d} s")
        except Exception as erreur:  # on signale toute erreur au site pour que l'icône passe en rouge
            message = str(erreur) or erreur.__class__.__name__
            journal(f"Lien {id_lien} : ERREUR {message}")
            try:
                self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 0, "message": message[:250]})
            except Exception as erreur2:
                journal(f"Impossible de signaler l'erreur au site : {erreur2}")
        except KeyboardInterrupt:  # Ctrl+C ou fenêtre fermée pendant un traitement : on prévient le site
            journal(f"Lien {id_lien} : arrêt demandé, traitement abandonné")
            try:
                self.appeler("pistesAgentTerminer", {"idLien": id_lien, "succes": 0,
                                                     "message": "Agent arrêté pendant le traitement"}, delai=10)
            except Exception:
                pass
            raise
        finally:
            shutil.rmtree(dossier, ignore_errors=True)

    def tourner(self, une_fois):
        journal(f"Agent {self.nom} démarré sur {self.url_projet} (Ctrl+C pour arrêter)")
        while True:
            try:
                tache = self.appeler("pistesAgentProchain")
            except (ErreurAgent, OSError) as erreur:
                journal(f"Site injoignable ou refus : {erreur}")
                tache = {"idLien": 0}
                if une_fois:
                    return
            if tache.get("idLien"):
                self.traiter(tache)
                continue
            if une_fois:
                journal("Plus rien à traiter.")
                return
            time.sleep(self.attente)


def main():
    parametres = argparse.ArgumentParser(description="Agent de génération des pistes du lecteur multipiste")
    parametres.add_argument("--une-fois", action="store_true", help="traite la file d'attente puis s'arrête")
    parametres.add_argument("--config", default=str(DOSSIER / "agent_pistes.ini"), help="fichier de réglages")
    arguments = parametres.parse_args()

    config = configparser.ConfigParser()
    if not config.read(arguments.config, encoding="utf-8"):
        sys.exit(f"Fichier de réglages introuvable : {arguments.config} (copier agent_pistes.ini.exemple)")
    try:
        Agent(config).tourner(arguments.une_fois)
    except ErreurAgent as erreur:
        sys.exit(str(erreur))
    except KeyboardInterrupt:
        journal("Arrêt demandé.")


if __name__ == "__main__":
    main()
