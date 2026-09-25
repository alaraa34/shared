<?php
declare(strict_types=1);
namespace shared\php\toolbox;
// ─────────────────────────────────────────────────────────────────────────────
// Outils mathématiques partagés (trait)
// ─────────────────────────────────────────────────────────────────────────────

trait MathTools
{
    /**
     * Moindres carrés ordinaires linéaires simples.
     * Retourne [a, b] tel que y ≈ a·x + b.
     *
     * @return float[]
     */
    protected function olsLinear(array $xs, array $ys): array
    {
        $n   = count($xs);
        $sx  = array_sum($xs);
        $sy  = array_sum($ys);
        $sxx = array_sum(array_map(fn($x) => $x ** 2, $xs));
        $sxy = array_sum(array_map(fn($x, $y) => $x * $y, $xs, $ys));

        $denom = $n * $sxx - $sx ** 2;

        if (abs($denom) < 1e-12) {
            throw new RegressionException('Points colinéaires ou identiques, régression impossible.');
        }

        $a = ($n * $sxy - $sx * $sy) / $denom;
        $b = ($sy - $a * $sx) / $n;

        return [$a, $b];
    }

    /**
     * Ajustement polynomial par moindres carrés (élimination de Gauss).
     * Retourne les coefficients [c0, c1, ..., cn] (c0 = terme constant).
     *
     * @return float[]
     */
    protected function polyFit(array $xs, array $ys, int $degree): array
    {
        $n = count($xs);
        $d = $degree + 1;

        $A = [];
        $b = [];

        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $d; $j++) {
                $row[] = $xs[$i] ** $j;
            }
            $A[] = $row;
            $b[] = $ys[$i];
        }

        $AtA = array_fill(0, $d, array_fill(0, $d, 0.0));
        $Atb = array_fill(0, $d, 0.0);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $d; $j++) {
                $Atb[$j] += $A[$i][$j] * $b[$i];
                for ($k = 0; $k < $d; $k++) {
                    $AtA[$j][$k] += $A[$i][$j] * $A[$i][$k];
                }
            }
        }

        return $this->gaussianElimination($AtA, $Atb);
    }

    /**
     * Évalue un polynôme en un point x.
     * coeffs[i] = coefficient de x^i.
     */
    protected function polyEval(array $coeffs, float $x): float
    {
        $result = 0.0;
        foreach ($coeffs as $i => $c) {
            $result += $c * ($x ** $i);
        }
        return $result;
    }

    /**
     * Résolution Ax = b par élimination de Gauss avec pivot.
     *
     * @return float[]
     */
    protected function gaussianElimination(array $A, array $b): array
    {
        $n = count($b);

        for ($i = 0; $i < $n; $i++) {
            $A[$i][] = $b[$i];
        }

        for ($col = 0; $col < $n; $col++) {
            $maxRow = $col;
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($A[$row][$col]) > abs($A[$maxRow][$col])) {
                    $maxRow = $row;
                }
            }
            [$A[$col], $A[$maxRow]] = [$A[$maxRow], $A[$col]];

            if (abs($A[$col][$col]) < 1e-12) {
                throw new RegressionException('Matrice singulière, système impossible à résoudre.');
            }

            for ($row = 0; $row < $n; $row++) {
                if ($row === $col) continue;
                $factor = $A[$row][$col] / $A[$col][$col];
                for ($k = $col; $k <= $n; $k++) {
                    $A[$row][$k] -= $factor * $A[$col][$k];
                }
            }
        }

        return array_map(fn(int $i) => $A[$i][$n] / $A[$i][$i], range(0, $n - 1));
    }

    /**
     * Descente de gradient numérique générique.
     *
     * @param  float[]  $params    Paramètres initiaux
     * @param  callable $predict   fn(float $x, float[] $params): float
     * @return float[]             Paramètres optimisés
     */
    protected function gradientDescent(
        array    $params,
        callable $predict,
        array    $xs,
        array    $ys,
        int      $maxIter = 10000,
        float    $lr      = 1e-4,
        float    $eps     = 1e-8,
    ): array {
        $h = 1e-5;

        for ($iter = 0; $iter < $maxIter; $iter++) {
            $grad = array_fill(0, count($params), 0.0);

            foreach ($xs as $i => $x) {
                $yHat = $predict($x, $params);
                $err  = $yHat - $ys[$i];

                foreach ($params as $j => $_) {
                    $pPlus      = $params;
                    $pPlus[$j] += $h;
                    $grad[$j]  += $err * ($predict($x, $pPlus) - $yHat) / $h;
                }
            }

            $newParams = [];
            $delta     = 0.0;

            foreach ($params as $j => $p) {
                $step        = $lr * $grad[$j];
                $newParams[] = $p - $step;
                $delta       = max($delta, abs($step));
            }

            $params = $newParams;

            if ($delta < $eps) break;
        }

        return $params;
    }

    /**
     * Coefficient de détermination R².
     */
    protected function r2(array $observed, array $predicted): float
    {
        $mean  = array_sum($observed) / count($observed);
        $ssTot = array_sum(array_map(fn($y) => ($y - $mean) ** 2, $observed));
        $ssRes = array_sum(array_map(fn($y, $yHat) => ($y - $yHat) ** 2, $observed, $predicted));

        return $ssTot < 1e-12 ? 1.0 : 1.0 - ($ssRes / $ssTot);
    }
    
    /**
     * Coefficient binomial C(n, k) — équivalent de COMBIN() Excel.
     * Retourne 0 si k > n ou k < 0.
     */
    private function combin(int $n, int $k): int{
    //nombre de combinaisons 
        if ($k < 0 || $k > $n) {die('Nombres invalide pour calcul de combinaisons');}
        if ($k === 0 || $k === $n) {return 1;}

        // Optimisation : C(n,k) = C(n, n-k)
        if ($k > $n - $k) {$k = $n - $k;}

        $result = 1;
        for ($i = 0; $i < $k; $i++) {
            $result = intdiv($result * ($n - $i), $i + 1);
        }
        return $result;
    }
}
