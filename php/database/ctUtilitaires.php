<?php
declare(strict_types=1);
namespace shared\php\database;
/**
* Fonctions pour gérer les bases de données
* et absentes de $exclusions (sans préfixe).
*/


function viderTablesAvecPrefixe(array $exclusions): void
{
    /**
    * Vide toutes les tables d'une base commençant par PREFIXE_BDD
    * et absentes de $exclusions (sans préfixe).
    */
    
    $pdo = Database::dbConnect();
    //nom de la base
    $base = (string)Database::mdGetENVIR()[Database::ENVIR_DB];

    // Construire la liste des tables exclues avec préfixe
    $exclusionsAvecPrefixe = array_map(
        fn(string $table): string => PREFIXE_BDD . $table,
        $exclusions
    );

    // Récupérer toutes les tables correspondant au préfixe
    $stmt = $pdo->prepare("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = :base
          AND LEFT(table_name, :longueur) = :prefixe
    ");
    $stmt->execute([
        ':base'     => $base,
        ':longueur' => strlen(PREFIXE_BDD),
        ':prefixe'  => PREFIXE_BDD,
    ]);
    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    // Filtrer les exclues
    $aVider = array_filter(
        $tables,
        fn(string $table): bool => !in_array($table, $exclusionsAvecPrefixe, strict: true)
    );

    if (empty($aVider)) {
        echo "Aucune table à vider.\n";
        return;
    }

    // Désactiver les contraintes FK le temps du TRUNCATE
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($aVider as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        echo "Vidée : {$table}\n";
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nTerminé — " . count($aVider) . " table(s) vidée(s).\n";
}

function ajouterPrefixeTables(string $prefixe): void{
    $pdo = Database::dbConnect();
    $base = (string)Database::mdGetENVIR()[Database::ENVIR_DB];
    // Récupérer toutes les tables de la base
    $stmt = $pdo->prepare("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = :base
    ");
    $stmt->execute([':base' => $base]);
    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "Aucune table trouvée dans la base '{$base}'.\n";
        return;
    }

    foreach ($tables as $table) {
        // Éviter de préfixer une table déjà préfixée
        if (str_starts_with($table, $prefixe)) {
            echo "Ignorée (déjà préfixée) : {$table}\n";
            continue;
        }
        $nouveauNom = $prefixe . $table;
        $pdo->exec("RENAME TABLE `{$table}` TO `{$nouveauNom}`");
        echo "Renommée : {$table} → {$nouveauNom}\n";
    }

    echo "\nTerminé — {$base} mise à jour avec le préfixe '{$prefixe}'.\n";
}
