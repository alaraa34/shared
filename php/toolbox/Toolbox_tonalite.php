<?php
declare(strict_types=1);
namespace shared\php\toolbox;
/*******************************************************************************
 * Calcul de tonalité : tonalité d'origine transposée d'un écart en demi-tons
 * ex. transposer("Cm", 2) -> "Dm" ; transposer("Bb", -1) -> "A" ; transposer("F#min", 3) -> "Amin"
 * Formats acceptés : note (A à G, majuscule ou minuscule), altération # ou b (ou ♯ ♭),
 * puis le reste tel quel (m, min, Min, maj7...) qui est recopié après la nouvelle note.
 * Orthographe de la note obtenue : celle de l'armure usuelle de la tonalité
 * (Bb, Eb, Ab, Db, F# en majeur ; C#m, F#m, G#m, Ebm, Bbm en mineur)
 ******************************************************************************/
class Toolbox_tonalite
{
    private const array NOTES = ['C'=>0, 'D'=>2, 'E'=>4, 'F'=>5, 'G'=>7, 'A'=>9, 'B'=>11];
    //nom usuel de la tonique par demi-ton (0 = do)
    private const array MAJEUR = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];
    private const array MINEUR = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'G#', 'A', 'Bb', 'B'];

    public static function transposer(string $tonalite, int $demiTons): string {
    //tonalité transposée ; "" si la tonalité n'est pas reconnue
        $analyse = self::analyser($tonalite);
        if ($analyse === null) {return "";}
        [$hauteur, $suffixe] = $analyse;
        $nouvelle = (($hauteur + $demiTons) % 12 + 12) % 12;
        $noms = self::estMineur($suffixe) ? self::MINEUR : self::MAJEUR;
        return $noms[$nouvelle] . $suffixe;
    }

    public static function libelleScene(string $tonaliteOrigine, string $ecart): string {
    //texte à afficher pour une tona de scène : "+2 → Dm" ; l'écart seul si la tonalité d'origine n'est pas reconnue
        $ecart = trim($ecart);
        $demiTons = (int)$ecart;
        if ($demiTons === 0) {return $ecart === "" ? "0" : $ecart;}
        $transposee = self::transposer($tonaliteOrigine, $demiTons);
        $ecartAffiche = sprintf('%+d', $demiTons);
        return $transposee === "" ? $ecartAffiche : $ecartAffiche . " → " . $transposee;
    }

    private static function analyser(string $tonalite): ?array {
    //[hauteur en demi-tons (0 à 11), suffixe] ou null
        if (!preg_match('/^\s*([A-Ga-g])(#|♯|b|♭)?(.*)$/u', $tonalite, $morceaux)) {return null;}
        $hauteur = self::NOTES[strtoupper($morceaux[1])];
        $alteration = $morceaux[2] ?? '';
        if ($alteration === '#' || $alteration === '♯') {$hauteur++;}
        if ($alteration === 'b' || $alteration === '♭') {$hauteur--;}
        return [($hauteur + 12) % 12, rtrim($morceaux[3])];
    }

    private static function estMineur(string $suffixe): bool {
    //m, min, Min, mi, - : mineur ; maj, M (majeur 7e...) : majeur
        $suffixe = ltrim($suffixe);
        if ($suffixe === '') {return false;}
        if (str_starts_with($suffixe, '-')) {return true;}
        if (stripos($suffixe, 'maj') === 0 || str_starts_with($suffixe, 'M') && stripos($suffixe, 'min') !== 0) {return false;}
        return str_starts_with(strtolower($suffixe), 'm');
    }
}
