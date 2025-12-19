<?php
/**
 * Autoloader for PhpSpreadsheet library
 * This autoloader loads all PhpOffice\PhpSpreadsheet classes from the lib directory
 */

spl_autoload_register(function ($class) {
    // Check if the class is in the PhpOffice namespace
    if (strpos($class, 'PhpOffice\\') === 0) {
        // Convert namespace to file path
        $classPath = str_replace('PhpOffice\\', '', $class);
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $classPath);
        $file = __DIR__ . '/lib/' . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
