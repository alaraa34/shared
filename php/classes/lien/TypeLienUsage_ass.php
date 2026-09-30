<?php
declare(strict_types=1);
/*******************************************************************************
 * Types de lien admis par sujet, propres à chaque application
 * (table <prefixe>lien_type_usage)
 * - le sujet est la constante SUJET_LIEN de la classe qui demande les liens
 *   (exemple Song::SUJET_LIEN = "SONG") : pas de nomenclature
 * - les types de lien sont communs à toutes les applications (table sh_lien_type, voir TypeLien)
 * - la liste des sujets est déclarée par chaque application (controleur accueil) :
 *   [constante SUJET_LIEN => libellé affiché]
 ******************************************************************************/
namespace shared\php\classes\lien;

use shared\php\database\Model                as Model;
use shared\php\toolbox\Toolbox_adressage     as TbAdressage;
use shared\php\toolbox\Toolbox_html          as TbHtml;

class TypeLienUsage_ass
{
    public const TABLE  = PREFIXE_BDD . "lien_type_usage";
    private const string CHAMP_POST = "usage";   //cases à cocher de la grille : usage[SUJET][] = idTypeLien

    //------------------------------------------------------------------------------------------------
    //AFFICHAGE
    //-----------------------------------------------------------------------------------------------
    public static function renderGrille(array $sujets, string $controleur, string $fonction): string {
    //grille de paramétrage : une ligne par type de lien, une colonne par sujet, cochée selon la table
    //$controleur / $fonction : fonction ct de l'application qui reçoit le formulaire
        $e = fn($valeur) => htmlspecialchars((string)$valeur);
        $types  = TypeLien::listeTous();
        $usages = self::mdUsagesParSujet();
        $nonDeclares = array_diff(array_keys($usages), array_keys($sujets));

        ob_start(); ?>
<form method="post" action="<?= TbAdressage::getURLstatic($controleur, $fonction) ?>">
    <section class="py-4">
        <div class="container">
            <p class="form-text">
                Cocher les types de lien que l'application propose pour chaque sujet.
            </p>
            <?php if (count($nonDeclares) > 0): ?>
                <div class="alert alert-warning">
                    Sujets présents dans la table mais non déclarés par l'application (conservés, non modifiables ici) :
                    <strong><?= $e(implode(", ", $nonDeclares)) ?></strong>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Type de lien</th>
                            <?php foreach ($sujets as $sujet => $libelle): ?>
                                <th class="text-center"><?= $e($libelle) ?><br><small class="text-body-secondary"><?= $e($sujet) ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $type): ?>
                            <tr>
                                <td>
                                    <?= $type['icone'] ?>&nbsp;<?= $e($type['nom']) ?>
                                    <small class="text-body-secondary">(<?= $e($type['nomAffiche']) ?><?= $type['externe'] ? ", externe" : "" ?>)</small>
                                </td>
                                <?php foreach ($sujets as $sujet => $libelle): ?>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox"
                                               name="<?= self::CHAMP_POST ?>[<?= $e($sujet) ?>][]" value="<?= (int)$type['id'] ?>"
                                               title="<?= $e($type['nom'] . " / " . $libelle) ?>"
                                               <?= in_array((int)$type['id'], $usages[$sujet] ?? [], true) ? "checked" : "" ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= TbHtml::htmlBoutonValidation("Enregistrer") ?>
        </div>
    </section>
</form>
<?php
        return ob_get_clean();
    }

    //------------------------------------------------------------------------------------------------
    //MISE A JOUR
    //-----------------------------------------------------------------------------------------------
    public static function enregistrer(array $sujets): bool {
    //remplace, pour chaque sujet déclaré, les types admis par ceux cochés dans la grille
    //une seule transaction : en cas d'erreur la table reste dans son état d'avant
    //les sujets non déclarés et les valeurs inconnues sont ignorés
        $saisie = filter_input(INPUT_POST, self::CHAMP_POST, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $saisie = is_array($saisie) ? $saisie : [];
        $idsTypes = array_map('intval', array_column(TypeLien::listeTous(), 'id'));

        return Model::mdTransactionOk(function () use ($sujets, $saisie, $idsTypes): bool {
            foreach (array_keys($sujets) as $sujet) {
                $sujet = (string)$sujet;
                Model::mdDelete(self::TABLE, ['sujet' => $sujet]);
                $coches = is_array($saisie[$sujet] ?? null) ? $saisie[$sujet] : [];
                foreach (array_unique(array_map('intval', $coches)) as $idTypeLien) {
                    if (!in_array($idTypeLien, $idsTypes, true)) {continue;}
                    if (!Model::mdInsert(self::TABLE, ['idTypeLien' => $idTypeLien, 'sujet' => $sujet])) {
                        throw new \RuntimeException("Insertion du type de lien " . $idTypeLien . " pour le sujet " . $sujet);
                    }
                }
            }
            return true;
        }, "TypeLienUsage_ass::enregistrer");
    }

    //------------------------------------------------------------------------------------------------
    //MODELE
    //-----------------------------------------------------------------------------------------------
    private static function mdUsagesParSujet(): array {
    //[sujet => [idTypeLien, ...]] pour tout le contenu de la table
        $requete = "SELECT sujet, idTypeLien FROM " . self::TABLE . " ORDER BY sujet, idTypeLien;";
        $usages = [];
        foreach (Model::mdRequeteLister($requete) as $ligne) {
            $usages[(string)$ligne['sujet']][] = (int)$ligne['idTypeLien'];
        }
        return $usages;
    }
}
