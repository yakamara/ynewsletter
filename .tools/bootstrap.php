<?php

/**
 * PHPUnit-Bootstrap: bootet die REDAXO-Instanz, in der das AddOn liegt
 * (redaxo/src/addons/ynewsletter/.tools → fünf Ebenen hoch ist das Projektverzeichnis).
 */

unset($REX);
$REX['REDAXO'] = true;
$REX['HTDOCS_PATH'] = dirname(__DIR__, 5) . '/';
$REX['BACKEND_FOLDER'] = 'redaxo';
$REX['LOAD_PAGE'] = false;

require dirname(__DIR__, 3) . '/core/boot.php';
require dirname(__DIR__, 3) . '/core/packages.php';

// PHPUnit soll Fehler selbst behandeln
rex_error_handler::unregister();
