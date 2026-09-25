
<?php
namespace shared\php\utils;

/**
 * Classe de validation des données
 * 
 * @package Shared\Utils
 */
class Validator {
    
    /**
     * Valide une adresse email
     * 
     * @param string $email Email à valider
     * @return bool True si valide
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valide qu'une chaîne n'est pas vide
     * 
     * @param string $value Valeur à valider
     * @param int $minLength Longueur minimale
     * @return bool True si valide
     */
    public static function required($value, $minLength = 1) {
        return is_string($value) && strlen(trim($value)) >= $minLength;
    }
    
    /**
     * Valide un numéro de téléphone français
     * 
     * @param string $phone Numéro à valider
     * @return bool True si valide
     */
    public static function phone($phone) {
        $phone = preg_replace('/[\s\-\.]/', '', $phone);
        return preg_match('/^0[1-9][0-9]{8}$/', $phone);
    }
    
    /**
     * Valide une URL
     * 
     * @param string $url URL à valider
     * @return bool True si valide
     */
    public static function url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
