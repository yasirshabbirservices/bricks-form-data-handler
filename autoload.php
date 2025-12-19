<?php
/**
 * Autoloader for PhpSpreadsheet library and PSR dependencies
 * Loads PhpOffice\PhpSpreadsheet and Psr classes from the lib directory
 */

spl_autoload_register(function ($class) {
    // Check if the class is in the PhpOffice or Psr namespace
    if (strpos($class, 'PhpOffice\\') === 0 || strpos($class, 'Psr\\') === 0) {
        // Convert namespace to file path
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        
        // For PhpOffice, remove the namespace prefix
        if (strpos($class, 'PhpOffice\\') === 0) {
            $classPath = str_replace('PhpOffice' . DIRECTORY_SEPARATOR, '', $classPath);
        }
        
        $file = __DIR__ . '/lib/' . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
