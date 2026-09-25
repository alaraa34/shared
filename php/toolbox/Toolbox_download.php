<?php
declare(strict_types=1);
namespace shared\php\toolbox;

    
class Toolbox_download {
    
// ═══════════════════════════════════════════════════════════════════════════════
// SERVICE — téléchargement cURL
// ═══════════════════════════════════════════════════════════════════════════════

    /** @throws FdjDownloadException */
    public function download(string $url, string $destDir): string
    {
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new FdjDownloadException("Impossible de créer : $destDir");
        }

        $destPath = $destDir . '/loto_latest.zip';
        $ch       = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => Config::CURL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => Config::CURL_CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'FDJ-Loto-Importer/2.0 (PHP ' . PHP_VERSION . ')',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/zip, application/octet-stream, */*',
                'Accept-Language: fr-FR,fr;q=0.9',
            ],
        ]);

        $data     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($data === false) {
            throw new FdjDownloadException("Erreur cURL : $curlErr");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new FdjDownloadException("HTTP $httpCode pour $url");
        }
        if (file_put_contents($destPath, $data) === false) {
            throw new FdjDownloadException("Impossible d'écrire : $destPath");
        }

        return $destPath;
    }
}

