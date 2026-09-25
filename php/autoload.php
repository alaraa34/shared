<?php

spl_autoload_register(function (string $class): void {
    
     // Extraire le nom court (après le dernier \)
    $nomCourt = ltrim(strrchr($class, '\\'), '\\') ?: $class;
    
    // Ignorer si déjà chargée (nom complet ou nom court natif)
    if (class_exists($class, false) || class_exists($nomCourt, false)) {
        return;
    }
    
    $chemin = filter_input(INPUT_SERVER,'DOCUMENT_ROOT')   . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $class)
            . '.php';
    
    if (file_exists($chemin)) {
        require_once $chemin;
    }
    else{
        throw new \InvalidArgumentException (">>> Autoload : Classe " . $class . "( " . $chemin ." ) introuvable");
    }
});
