<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * Envoi de mails en SMTP avec PHPMailer (shared/php/lib/PHPMailer)
 *
 * Configuration dans le dossier du projet (comme ENVIRdev.txt / ENVIRprod.txt) :
 *     MAILdev.txt  (localhost)   ou   MAILprod.txt (production)
 * une seule ligne :  serveur;port;adresse;motdepasse
 *     exemple : mail.kontouma.fr;465;contact@kontouma.fr;MonMotDePasse
 * L'adresse sert à la fois d'identifiant SMTP et d'expéditeur.
 * Port 465 : SSL, port 587 : STARTTLS, autre port : sans chiffrement (tests)
 * Ces fichiers ne vont jamais dans git (.gitignore) et sont protégés du web (.htaccess)
 *******************************************************************************/
use PHPMailer\PHPMailer\PHPMailer    as PHPMailer;

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

class Toolbox_mail {

    public static string $derniereErreur = "";     //motif du dernier échec d'envoi (sans mot de passe), pour l'affichage

    public static function envoyer(string $destinataire, string $objet, string $texte): bool {
    //envoie un mail texte ; retourne false en cas d'échec (détail dans le log PHP)
        self::$derniereErreur = "";
        $configuration = self::lireConfiguration();
        if (count($configuration) === 0) {
            self::$derniereErreur = "fichier de configuration MAIL absent ou incorrect";
            return false;
        }
        [$serveur, $port, $adresse, $motDePasse] = $configuration;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host     = $serveur;
            $mail->Port     = $port;
            $mail->SMTPAuth = true;
            $mail->Timeout  = 20;                           //secondes : connexion
            $mail->getSMTPInstance()->Timelimit = 20;       //secondes : attente des réponses du serveur
            $mail->Username = $adresse;
            $mail->Password = $motDePasse;
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setLanguage('fr', __DIR__ . '/../lib/PHPMailer/language/');
            $mail->setFrom($adresse);
            $mail->addAddress($destinataire);
            $mail->Subject = $objet;
            $mail->Body    = $texte;
            $mail->isHTML(false);
            return $mail->send();
        } catch (\Throwable $e) {
            self::$derniereErreur = $mail->ErrorInfo !== "" ? $mail->ErrorInfo : $e->getMessage();
            error_log("Toolbox_mail::envoyer vers " . $destinataire . " : " . $mail->ErrorInfo . " " . $e->getMessage());
            return false;
        }
    }

    private static function lireConfiguration(): array {
    //lit MAILdev.txt ou MAILprod.txt dans le dossier du projet : [serveur, port, adresse, motdepasse]
        $chemin = ROOT_PATH . PROJET . '/MAIL' . (Toolbox_adressage::isDeveloppement() ? 'dev' : 'prod') . '.txt';
        $contenu = is_file($chemin) ? trim((string)file_get_contents($chemin)) : '';
        //limite à 4 morceaux : le mot de passe peut contenir des ;
        $valeurs = explode(';', $contenu, 4);
        if (count($valeurs) !== 4 || (int)$valeurs[1] === 0) {
            //ne jamais afficher le contenu du fichier : il contient un mot de passe
            error_log("Toolbox_mail : fichier de configuration absent ou incorrect (" . basename($chemin) . ")");
            return [];
        }
        return [trim($valeurs[0]), (int)$valeurs[1], trim($valeurs[2]), $valeurs[3]];
    }
}
