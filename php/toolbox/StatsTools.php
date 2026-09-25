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
    
    protected function deriveeParabolleEn2(array $y): float{
    //applique une regression de degré 2 
    // puis calcule la dérivée du point 2
        [$y1, $y2, $y3] = $y;

        // Résolution exacte du système avec x ∈ {1, 2, 3}
        // f(1) = a + b + c = y1
        // f(2) = 4a + 2b + c = y2
        // f(3) = 9a + 3b + c = y3
        //
        // Par différences successives :
        // (f(3) - f(1)) / 2 = 5a + 2b + c  ... non, on fait mieux :
        // f(3) - f(1) = 8a + 2b          => 8a + 2b = y3 - y1    (I)
        // f(2) - f(1) =  3a +  b          =>  3a +  b = y2 - y1   (II)
        // (I) - 2*(II) => 2a = (y3 - y1) - 2*(y2 - y1) = y3 - 2*y2 + y1

        $a = ($y1 - 2 * $y2 + $y3) / 2.0;
        $b = ($y2 - $y1) - 3 * $a;         // de (II) : b = (y2-y1) - 3a

        // f'(x) = 2ax + b  =>  f'(2) = 4a + b
        return 4.0 * $a + $b;
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
