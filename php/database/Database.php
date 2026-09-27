<?php
namespace shared\php\database;
/**
 * Classe de gestion de la base de données
 * 
 * @package Shared\Database
 */
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

final class Database
{
    /** Connexion \PDO mise en cache pour la durée de la requête (évite les reconnexions répétées). */
    private static ?\PDO $pdo = null;
    public const string SESSION_ENVIR = "ENVIR";
    
    public const int ENVIR_DB = 0;
    public const int ENVIR_DB_HOST = 1;
    public const int ENVIR_PORT = 2;
    public const int ENVIR_USER = 3;
    public const int ENVIR_PWD = 4;
    
    public static function dbConnect(): \PDO
    {
        // La connexion a déjà été ouverte pour cette requête : on la réutilise.
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $envir = self::mdGetENVIR();

        $db       = (string) $envir[self::ENVIR_DB];
        $dbhost   = (string) $envir[self::ENVIR_DB_HOST];
        $dbport   = (int) $envir[self::ENVIR_PORT];
        $dbuser   = (string) $envir[self::ENVIR_USER];
        $dbpasswd = (string) $envir[self::ENVIR_PWD];

        // charset directement dans le DSN : évite le round-trip "SET NAMES" / "SET CHARACTER SET"
        // et utf8mb4 gère l'Unicode complet (emojis, certains caractères accentués), contrairement à utf8.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $dbhost,
            $dbport,
            $db
        );

        $options = [
            \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false, // vraies requêtes préparées côté MySQL (perf + sécurité)
            \PDO::ATTR_PERSISTENT       => true,
        ];

        try {
            self::$pdo = new \PDO($dsn, $dbuser, $dbpasswd, $options);
        } catch (\PDOException $e) {
            unset($_SESSION[self::SESSION_ENVIR]);

            // On masque le mot de passe avant de l'exposer dans le message d'erreur.
            $envirSafe = $envir;
            $envirSafe[4] = '***';

            // détail dans le log PHP seulement : l'écran ne doit rien révéler de la configuration
            error_log('Erreur dbConnect: ' . $e->getMessage() . ' Paramètres envir ' . serialize($envirSafe));
            die('Erreur de connexion à la base de données, voir Log PHP');
        }

        return self::$pdo;
    }

    public static function mdGetENVIR(): array
    {
        try {
            if (isset($_SESSION[self::SESSION_ENVIR])) {
                return $_SESSION[self::SESSION_ENVIR];
            }

            $nomfichier = TbAdressage::isDeveloppement() ? 'dev' : 'prod';
            $chemin = ROOT_PATH . '/' . PROJET . '/ENVIR' . $nomfichier . '.txt';
            $fichier = file_get_contents($chemin);

            if ($fichier === false) {
                die('Erreur lors de la lecture du fichier ENVIR, voir Log PHP');
            }

            $envir = explode(';', $fichier);

            if (count($envir) !== 6) {
                unset($_SESSION[self::SESSION_ENVIR]);
                // ne jamais afficher le contenu du fichier : il contient le mot de passe
                die('Contenu incorrect du fichier ENVIR (6 valeurs séparées par ; attendues)');
            }

            $_SESSION[self::SESSION_ENVIR] = $envir;

            return $envir;
        } catch (\Throwable $e) {
            error_log('Erreur mdGetENVIR : ' . $e->getMessage());
            die('Erreur lors de la lecture de la configuration, voir Log PHP');
        }
    }
}
