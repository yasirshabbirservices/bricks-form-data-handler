<?php
/**
 * Simple autoloader for PhpSpreadsheet library
 * This autoloader loads the PhpOffice\PhpSpreadsheet classes from the lib directory
 */

spl_autoload_register(function ($class) {
    // Only autoload PhpOffice\PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        // Convert namespace to file path
        $classPath = str_replace('PhpOffice\\PhpSpreadsheet\\', '', $class);
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $classPath);
        $file = __DIR__ . '/lib/PhpSpreadsheet/' . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
