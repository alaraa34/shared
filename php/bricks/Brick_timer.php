<?php
declare(strict_types=1);
namespace shared\php\bricks;

/*******************************************************************************
 * affiche un timer
  * @author Alara
 */

use shared\php\toolbox\Toolbox_date    as TbDate;

class Brick_timer {
    //put your code here
    public static function renderA(int $dureeEnSecondes, bool $boutons=true){
        $html= '<div id="timer" class="timer-display mb-2">'. TbDate::dateSecondesFormatMMSS($dureeEnSecondes) . '</div>';
        if($boutons){
            $html.='<div class="btn-group">
                        <button id="startBtn" class="btn btn-primary">Start</button>
                        <button id="resetBtn" class="btn btn-secondary">Reset</button>
                    </div>';
        }
        
        return $html;
    }
    public static function renderH(int $dureeEnSecondes, string $fonctionStart="",  string $fonctionReset="", int $ordre=0){
    //affichage avec boutons à coté
    //$fonction est une autre fonction à déclencher
    //les fonctions si exprimées doivent commencer par ";"
    //exemple BkTimer::renderH($this->wrkPalier->duree,
    //                ";" . Metronome::fonctionPlay($this->wrkPalier->tempo), (fonction start)
    //                ";" . Metronome::fonctionPlay($this->wrkPalier->tempo)); (fonction reset)
        return 
            '<div id="timer'. $ordre. '" class="timer-display ">'. TbDate::dateSecondesFormatMMSS($dureeEnSecondes) .
                '<div class="btn-group pl-5">
                    <button id="startBtn'. $ordre. '" onclick="resetTimer('.$ordre.',' . $dureeEnSecondes . '); startTimer('.$ordre.')' . $fonctionStart . '" class="btn btn-primary btn-sm">Start</button>
                    <button disabled id="resetBtn'. $ordre. '" onclick="resetTimer(' .$ordre.',' . $dureeEnSecondes . ')' . $fonctionReset . '" class="btn btn-secondary btn-sm">Reset</button>
                </div></div>
            ';
                           

    }
    public static function renderV(int $dureeEnSecondes, string $fonctionStart="",  string $fonctionReset="", int $ordre=0){
    //affichage avec boutons à coté
    //$fonction est une autre fonction à déclencher
    //les fonctions si exprimées doivent commencer par ";"
    //exemple BkTimer::renderH($this->wrkPalier->duree,
    //                ";" . Metronome::fonctionPlay($this->wrkPalier->tempo), (fonction start)
    //                ";" . Metronome::fonctionPlay($this->wrkPalier->tempo)); (fonction reset)
        return 
            '<div class="d-flex flex-column align-items-center justify-content-center">
                <div id="timer'. $ordre. '" class="timer-display ">'. TbDate::dateSecondesFormatMMSS($dureeEnSecondes) . '</div>
                <div class="btn-group">
                    <button id="startBtn'. $ordre. '" onclick="startTimer('.$ordre.','. $dureeEnSecondes .')' . $fonctionStart . '" class="btn btn-primary btn-sm">Start</button>
                    <button disabled id="resetBtn'. $ordre. '" onclick="resetTimer(' .$ordre.',' . $dureeEnSecondes . ')' . $fonctionReset . '" class="btn btn-secondary btn-sm">Reset</button>
                </div>
            </div>';
                           

    }
}
