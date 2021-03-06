<?php

    /**
     * Autoloader for the Resting TLA subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     */
    spl_autoload_register(function ($class) {
    	$path = 'classes/' . $class . '.class.php';
        $prefixes = explode(PATH_SEPARATOR, get_include_path());

        foreach ( $prefixes as $p ) {
            if (file_exists($p .'/' . $path))
            {
                include_once $p . '/' . $path;
            }
        }
    });
?>
