<?php
namespace shared\php\utils;

/**
 * Description of ZipExtractor
 *
 * @author Alara
 */
final class Utilitaires
{
// ═══════════════════════════════════════════════════════════════════════════════
// SERVICE — décompression ZIP
// ═══════════════════════════════════════════════════════════════════════════════

    /** @throws FdjZipException */
    public function extract(string $zipPath, string $destDir): string
    {
        if (!class_exists('ZipArchive')) {
            throw new FdjZipException('Extension PHP ZipArchive non disponible.');
        }

        $zip    = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new FdjZipException("Impossible d'ouvrir le ZIP (code $result).");
        }

        $csvName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.csv')) {
                $csvName = $name;
                break;
            }
        }

        if ($csvName === null) {
            $zip->close();
            throw new FdjZipException('Aucun fichier .csv trouvé dans le ZIP.');
        }

        $zip->extractTo($destDir, $csvName);
        $zip->close();

        $fullPath = $destDir . '/' . $csvName;
        if (!is_file($fullPath)) {
            // Cas où le CSV est à la racine du ZIP mais extractTo crée un sous-dossier
            $alt = $destDir . '/' . basename($csvName);
            if (is_file($alt)) {
                $fullPath = $alt;
            } else {
                throw new FdjZipException("CSV introuvable après extraction : $fullPath");
            }
        }

        return $fullPath;
    }
}
