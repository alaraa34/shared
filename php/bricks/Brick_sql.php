<?php
declare(strict_types=1);
namespace shared\php\bricks;

/**
 * Description of Brick_sql
 *
 * @author Alara
 */
use shared\php\database\Model as Model;
use shared\php\database\Model_utils as ModelU;
use shared\php\toolbox\Toolbox as Tbx;

class Brick_sql {
    public readonly string $myHtml;
    public readonly string $myCss;
    public readonly string $myScript;

    private array $requetes;

    private const string TABLE = PREFIXE_BDD . 'requete';

    public function __construct(string $groupe)
    {
        $requetes = ModelU::mdSelectTable($this::TABLE, ['groupe' => $groupe], 'sujet');

        // Injecter les paramètres détectés automatiquement depuis le champ 'requete'
        foreach ($requetes as &$req) {
            $req['params'] = $this->extraireParams($req['requete']);
        }
        unset($req);
        $this->requetes = $requetes;

        // Intercepter l'AJAX avant tout rendu HTML
        $this->gererAjax();

        // Construire les sorties pour le layout
        $this->myCss    = Tbx::includeCSS('queryRunner');
        $this->myScript = Tbx::includeJS('queryRunner');
        $this->myHtml   = $this->buildHtml();
    }

    // ────────────────────────────────────────────────────────────
    // AJAX — détection et court-circuit du layout
    // ────────────────────────────────────────────────────────────
    private function gererAjax(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            ($_POST['action'] ?? '') !== 'qr_executer'
        ) {
            return;
        }

        ob_end_clean();  // vide tout output parasite (debug autoloader, etc.)
        header('Content-Type: application/json; charset=utf-8');

        $id  = (int) ($_POST['index'] ?? -1);
        $def = null;
        foreach ($this->requetes as $req) {
            if ((int) $req['id'] === $id) { $def = $req; break; }
        }
        if ($def === null) {
            echo json_encode(['erreur' => 'Requête introuvable.']);
            exit;
        }

        $params = [];
        foreach ($def['params'] as $nom) {
            $params[] = $_POST['param_' . $nom] ?? '';
        }

        try {
            $rows = Model::mdRequeteLister($def['requete'], $params);
            echo json_encode([
                'sujet'       => $def['sujet'],
                'description' => $def['description'],
                'rows'        => $rows,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['erreur' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────
    // Extraction automatique des noms de paramètres depuis le champ 'requete'
    // Couvre : col = ?, col >= ?, col LIKE ?, col BETWEEN ? AND ?
    // Pour BETWEEN : génère col_min et col_max
    // ────────────────────────────────────────────────────────────
    private function extraireParams(string $requete): array
    {
        $params = [];

        // Cas BETWEEN : col BETWEEN ? AND ? → col_min et col_max
        preg_match_all(
            '/([\w]+)\s+BETWEEN\s+\?\s+AND\s+\?/i',
            $requete,
            $matchesBetween
        );
        foreach ($matchesBetween[1] as $col) {
            $params[] = $col . '_min';
            $params[] = $col . '_max';
        }

        // Cas standard : col = ?, col >= ?, col LIKE ?, etc.
        // On neutralise les BETWEEN pour éviter une double capture
        $requeteSansBetween = preg_replace(
            '/([\w]+)\s+BETWEEN\s+\?\s+AND\s+\?/i',
            '',
            $requete
        );
        preg_match_all(
            '/([\w]+)\s*(?:<=|>=|!=|<|>|=|LIKE)\s*\?/i',
            $requeteSansBetween,
            $matchesStd
        );
        foreach ($matchesStd[1] as $col) {
            $params[] = $col;
        }

        return $params;
    }

    // ────────────────────────────────────────────────────────────
    // Génération du HTML
    // ────────────────────────────────────────────────────────────
    private function buildHtml(): string
    {
        $rows = '';
        foreach ($this->requetes as $req) {
            $id = (int) $req['id'];
            $rows .= <<<HTML
            <tr>
                <td class="text-center text-muted">{$id}</td>
                <td>{$this->e($req['sujet'])}</td>
                <td class="text-muted">{$this->e($req['description'])}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary qr-btn-lancer"
                            data-index="{$id}"
                            data-sujet="{$this->e($req['sujet'], true)}"
                            data-params="{$this->e(json_encode($req['params']), true)}">
                        ▶ Lancer
                    </button>
                </td>
            </tr>
            HTML;
        }

        return <<<HTML

        <!-- ── LISTE DES REQUÊTES ── -->
        <div id="qr-zone-liste">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width:4rem" class="text-center">ID</th>
                        <th style="width:20%">Sujet</th>
                        <th>Description</th>
                        <th style="width:7rem" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
        </div>

        <!-- ── SPINNER ── -->
        <div id="qr-spinner" class="text-center my-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement…</span>
            </div>
        </div>

        <!-- ── ZONE RÉSULTAT ── -->
        <div id="qr-zone-resultat" style="display:none">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="mb-0" id="qr-res-sujet"></h5>
                    <small class="text-muted" id="qr-res-description"></small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-secondary" id="qr-res-count"></span>
                    <button class="btn btn-sm btn-outline-secondary" id="qr-btn-retour">
                        ← Retour à la liste
                    </button>
                </div>
            </div>
            <div class="qr-table-wrap">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead><tr id="qr-res-thead"></tr></thead>
                    <tbody id="qr-res-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- ── MODALE PARAMÈTRES ── -->
        <div class="modal fade" id="qr-modal" tabindex="-1"
             aria-labelledby="qr-modal-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qr-modal-label">Paramètres</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="qr-modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary" id="qr-btn-confirmer">Exécuter</button>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }

    // ────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────
    private function e(string $str, bool $quotes = false): string
    {
        return htmlspecialchars($str, $quotes ? ENT_QUOTES : ENT_NOQUOTES, 'UTF-8');
    }
}
