<?php

/**
 * Apply debug settings from config.inc.php before Composer autoload.
 * Vendor packages can emit deprecations during autoload on PHP 8.4+.
 */

$configFile = dirname(__DIR__) . '/config.inc.php';
if (!is_readable($configFile)) {
    return;
}

$configContents = file_get_contents($configFile);
if (preg_match('/^\s*deprecation_warnings\s*=\s*Off\s*$/mi', $configContents)) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
if (preg_match('/^\s*display_errors\s*=\s*Off\s*$/mi', $configContents)) {
    ini_set('display_errors', '0');
}
