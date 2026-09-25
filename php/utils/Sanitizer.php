<?php
/**
 * Classe de nettoyage des données
 * 
 * @package Shared\Utils
 */
namespace shared\php\utils;
class Sanitizer {
    
    /**
     * Échappe les caractères HTML
     * 
     * @param string $string Chaîne à échapper
     * @return string Chaîne échappée
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Nettoie une chaîne de texte
     * 
     * @param string $string Chaîne à nettoyer
     * @return string Chaîne nettoyée
     */
    public static function cleanString($string) {
        return trim(strip_tags($string));
    }
    
    /**
     * Nettoie un email
     * 
     * @param string $email Email à nettoyer
     * @return string Email nettoyé
     */
    public static function cleanEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Nettoie une URL
     * 
     * @param string $url URL à nettoyer
     * @return string URL nettoyée
     */
    public static function cleanUrl($url) {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
}
