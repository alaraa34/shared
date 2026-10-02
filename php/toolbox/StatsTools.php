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
    
    function stats_deriveeDernierPoint(array $y): float{
        $d = array_values($y);
        $n = count($d);
        if ($n < 3 || $n > 9) {
            throw new \InvalidArgumentException("Il faut entre 3 et 9 valeurs, $n reçue(s).");
        }

        $derivee = 0.0;
        for ($k = 1; $k < $n; $k++) {
            // différences d'ordre k : on écrase le tableau de droite à gauche
            for ($i = $n - 1; $i >= $k; $i--) {
                $d[$i] -= $d[$i - 1];
            }
            $derivee += $d[$n - 1] / $k;   // ∇^k y au dernier point, divisé par k
        }
        return $derivee;
    }
   
    /**
 * Ordonnée au point n+1 du polynôme de degré n qui passe par
 * (1, y[0]), ..., (n, y[n-1]) et dont la dérivée en n+1 vaut $t.
 *
 * @param list<int|float> $y  n ordonnées (3 ≤ n ≤ 9), $y[0] ↔ abscisse 1
 * @param float           $t  dérivée imposée au point d'abscisse n+1
 */
    function stats_ordonneePointSuivant(array $y, float $t): float{
        $d = array_values($y);
        $n = count($d);
        if ($n < 2 || $n > 9) {
            throw new \InvalidArgumentException("Il faut entre 2 et 9 valeurs, $n reçue(s).");
        }

        $d[] = 0.0;          // point n+1 provisoirement à Y = 0
        $m   = $n + 1;       // nombre de points
        $d0  = 0.0;          // dérivée en n+1 avec Y = 0
        $h   = 0.0;          // H_n = coefficient de Y

        for ($k = 1; $k < $m; $k++) {
            for ($i = $m - 1; $i >= $k; $i--) {
                $d[$i] -= $d[$i - 1];
            }
            $d0 += $d[$m - 1] / $k;
            $h  += 1.0 / $k;
        }

        return ($t - $d0) / $h;
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
