<?php
declare(strict_types=1);

namespace shared\php\toolbox;

/**
 * Outils mathematiques
 *
 * @author Alara
 */
trait StatsTools {
    use \shared\php\toolbox\MathTools;
    //put your code here
    protected function stats_irregularite(array $valeurs): float {
    /*Le cas $moyenne == 0 couvre la suite constante (tous les écarts sont nuls) et retourne 0.0 proprement sans division par zéro.
    L'écart-type utilise la variance de population (diviseur nn
    n), cohérent avec le calcul fait plus haut.
    La fonction accepte n'importe quel tableau d'entiers, les valeurs négatives sont gérées grâce au abs().*/
        $n = count($valeurs);
        if ($n < 2) {
            throw new \InvalidArgumentException("Le tableau doit contenir au moins 2 valeurs.");
        }

        // Calcul des écarts consécutifs
        $ecarts = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $ecarts[] = abs($valeurs[$i + 1] - $valeurs[$i]);
        }

        // Moyenne des écarts
        $moyenne = array_sum($ecarts) / count($ecarts);

        if ($moyenne == 0.0) {
            return 0.0; // Suite constante, parfaitement régulière
        }

        // Écart-type des écarts
        $variance = array_sum(array_map(fn($d) => ($d - $moyenne) ** 2, $ecarts)) / count($ecarts);
        $ecartType = sqrt($variance);

        return $ecartType / $moyenne;
    }
    
    protected function r2Serie (array $mesures) :float{
    //calcule le r2 d'une série
    //1 peu décart , 0 beaucoup
        switch (count($mesures)){
            case 0:
                $r2 = 0;
                break;
            case 1:
                $r2 = 1;
                break;
            default:
                //si tous les resulatst sont pareil irrégularité = 0
                $r2= 1 - $this->stats_irregularite($mesures);
        }
        return $r2;
    }
    
    protected function stats_moyenne(array $valeurs, $valeurSiVide=null){
        return count($valeurs)===0 ? $valeurSiVide : array_sum($valeurs)/count($valeurs);
    }
    
    protected function stats_arrondiEntierProche(float $valeur):int{
    //arrondi la valeur à l'entier le plus proche making 1.5 into 2 and -1.5 into -2.
        return (int)round($valeur,0,PHP_ROUND_HALF_UP);
    }
    
    protected function stats_standard_deviation(array $data, bool $sample = false): float
    {
        $n = count($data);
        if ($n < 2) {
            throw new \InvalidArgumentException("Au moins 2 valeurs requises.");
        }

        $mean = array_sum($data) / $n;
        $variance = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $data))
                    / ($sample ? $n - 1 : $n);

        return sqrt($variance);
    }
    
 /**
 * Dérivée en x = $i du polynôme de degré n-1 passant par les points (k, y_k), k = 1..n.
 *
 * @param array<int|float> $y  n valeurs (2 ≤ n ≤ 9), y1..yn
 * @param int              $i  indice du point (1 ≤ i ≤ n)
 */
function stats_deriveeInterpolation(array $y, int $i): float
{
    $y = array_values($y);
    $n = count($y);

    if ($n < 2 || $n > 9) {
        throw new InvalidArgumentException("Le tableau doit contenir entre 2 et 9 valeurs ($n reçues).");
    }
    if ($i < 1 || $i > $n) {
        throw new InvalidArgumentException("L'indice doit être compris entre 1 et $n ($i reçu).");
    }

    // Factorielles 0! .. (n-1)!
    $fact = [1];
    for ($k = 1; $k < $n; $k++) {
        $fact[$k] = $fact[$k - 1] * $k;
    }

    // Poids barycentriques (à un facteur commun près)
    $w = [];
    for ($j = 1; $j <= $n; $j++) {
        $signe = (($n - $j) % 2 === 0) ? 1 : -1;
        $w[$j] = $signe / ($fact[$j - 1] * $fact[$n - $j]);
    }

    // P'(i) = Σ_{j≠i} (w_j / w_i) * (y_j - y_i) / (i - j)
    $yi = (float) $y[$i - 1];
    $d  = 0.0;
    for ($j = 1; $j <= $n; $j++) {
        if ($j === $i) {
            continue;
        }
        $d += ($w[$j] / $w[$i]) * ($y[$j - 1] - $yi) / ($i - $j);
    }

    return $d;
}
   
    /**
    * Ordonnée au point n+1 du polynôme de degré n qui passe par
    * (1, y[0]), ..., (n, y[n-1]) et dont la dérivée au point d'abscisse $i vaut $t.
    *
    * @param list<int|float> $y  n ordonnées (3 ≤ n ≤ 9), $y[0] ↔ abscisse 1
    * @param float           $t  dérivée imposée
    * @param int             $i  abscisse où la dérivée est imposée (1 ≤ i ≤ 9)
    */
    function stats_ordonneePointSuivantDeriveeEn(array $y, float $t, int $i): float
    {
        $y = array_values($y);
        $n = count($y);
        if ($n < 2 || $n > 9) {
            throw new InvalidArgumentException("Il faut entre 2 et 9 valeurs, $n reçue(s).");
        }
        if ($i < 1 || $i > 9) {
            throw new InvalidArgumentException("i doit être entre 1 et 9, $i reçu.");
        }

        // Q(n+1) : valeur de l'interpolation de Lagrange au point suivant
        $q = 0.0;
        for ($j = 1; $j <= $n; $j++) {
            $l = 1.0;
            for ($k = 1; $k <= $n; $k++) {
                if ($k !== $j) {
                    $l *= ($n + 1 - $k) / ($j - $k);
                }
            }
            $q += $y[$j - 1] * $l;
        }

        // Q'(i) : dérivée de l'interpolation en i
        $dq = 0.0;
        for ($j = 1; $j <= $n; $j++) {
            $dl = 0.0;
            for ($m = 1; $m <= $n; $m++) {
                if ($m === $j) {
                    continue;
                }
                $p = 1.0 / ($j - $m);
                for ($k = 1; $k <= $n; $k++) {
                    if ($k !== $j && $k !== $m) {
                        $p *= ($i - $k) / ($j - $k);
                    }
                }
                $dl += $p;
            }
            $dq += $y[$j - 1] * $dl;
        }

        // R'(i) avec R(x) = (x-1)(x-2)...(x-n), et R(n+1) = n!
        $dr = 0.0;
        $fact = 1.0;
        for ($m = 1; $m <= $n; $m++) {
            $p = 1.0;
            for ($k = 1; $k <= $n; $k++) {
                if ($k !== $m) {
                    $p *= $i - $k;
                }
            }
            $dr += $p;
            $fact *= $m;
        }

        $c = ($t - $dq) / $dr;
        return $q + $c * $fact;
    }

    protected function stats_Mediane(array $tableau): ?float {
        if (empty($tableau)) {
            return null;
        }

        sort($tableau);
        $nombreElements = count($tableau);
        $milieu = floor($nombreElements / 2);
        $median = $tableau[$milieu];

        if ($nombreElements % 2 === 0) {
            $median= ($tableau[$milieu - 1] + $tableau[$milieu]) / 2;
        }

        return (float) $median;
    }
    
    function stats_ExtrapolerRegressionLocale(array $y, int $pointsAConsiderer = 5): ?float {
    //extrapolation avec methode des moindres carrés
        $totalPoints = count($y);
        if ($totalPoints < 2) return null;

        // On restreint l'analyse aux k derniers points pour garder l'aspect local/dérivable
        $k = min($pointsAConsiderer, $totalPoints);
        $derniersY = array_slice($y, -$k);

        // Recréation des X correspondants (ex: si n=10 et k=3, les X seront 8, 9, 10)
        $debutX = $totalPoints - $k;
        $derniersX = range($debutX, $totalPoints - 1);
        $cibleX = $totalPoints; // C'est l'indice n+1

        // Calcul des moyennes
        $moyenneX = array_sum($derniersX) / $k;
        $moyenneY = array_sum($derniersY) / $k;

        // Calcul de la pente (pente = covariance / variance)
        $numerateur = 0;
        $denominateur = 0;
        for ($i = 0; $i < $k; $i++) {
            $diffX = $derniersX[$i] - $moyenneX;
            $numerateur += $diffX * ($derniersY[$i] - $moyenneY);
            $denominateur += $diffX * $diffX;
        }

        if ($denominateur == 0) return end($y); // Évite la division par zéro

        $pente = $numerateur / $denominateur;
        $ordonnee = $moyenneY - ($pente * $moyenneX);

        // Équation de la droite : y = pente * x + ordonnee
        return ($pente * $cibleX) + $ordonnee;
    }


     // ------------------------------------------------------------------ //
    //  Fonctions de codage et décodage de groupes                        //
    // ------------------------------------------------------------------ //
    public function statsCoderEnsemble(array $ensemble, int $nbMembresTotal): int
    {
        $ensembleN = array_values($ensemble);
        sort($ensembleN, SORT_NUMERIC);   // réindexer à 0
        $nbrMembre = count($ensembleN);

        // tableau 1-based avec sentinelle à l'index 0 (= 0, comme en VBA)
        $tab = array_merge([0], $ensembleN);    // $tab[0]=0, $tab[1..k] = valeurs

        $numeroEnsemble = 0;

        for ($rang = 1; $rang <= $nbrMembre; $rang++) {
            $rangRestant = $nbrMembre - $rang;

            // Parcourt les "trous" entre la valeur précédente et la valeur courante
            for ($i = $tab[$rang - 1] + 1; $i <= $tab[$rang] - 1; $i++) {
                $cibleChoix = $nbMembresTotal - $i;
                $numeroEnsemble += $this->combin($cibleChoix, $rangRestant);
            }
        }

        return $numeroEnsemble + 1;
    }

    
    
    public function statsDecoderEnsemble(
        int    $numeroEnsemble,
        int    $nbreMembreDsUnEnsemble,
        int    $nombreMembres,
        string $separateur = ';'
    ): string {
        if ($nbreMembreDsUnEnsemble < 2) {
            throw new \InvalidArgumentException("nbreMembreDsUnEnsemble incorrect (min 2).");
        }
        if ($nombreMembres < 1) {
            throw new \InvalidArgumentException("nombreMembres incorrect (min 1).");
        }

        $valeur    = $numeroEnsemble;
        $precedent = 1;
        $parts     = [];                      // stocke les éléments trouvés

        // Balayage des k-1 premières colonnes
        for ($i = 1; $i <= $nbreMembreDsUnEnsemble - 1; $i++) {
            for ($j = $precedent; $j <= $nombreMembres; $j++) {
                $increment = $this->combin($nombreMembres - $j, $nbreMembreDsUnEnsemble - $i);
                if ($increment < $valeur) {
                    $valeur -= $increment;
                } else {
                    $parts[]   = $j;
                    $precedent = $j + 1;
                    break;
                }
            }
        }

        // Dernier élément
        $parts[] = $precedent + $valeur - 1;

        // Formatage
        $formatted = array_map(fn(int $v): string => sprintf('%02d', $v), $parts);

        return implode($separateur, $formatted);
    }
}
