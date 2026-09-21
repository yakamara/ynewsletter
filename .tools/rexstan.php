<?php

/**
 * Schreibt die rexstan-Konfiguration für den CI-Lauf.
 * Wird aus dem REDAXO-Projektverzeichnis aufgerufen (siehe .github/workflows/rexstan.yml),
 * ADDON_KEY enthält den AddOn-Ordner.
 */

unset($REX);
$REX['REDAXO'] = true;
$REX['HTDOCS_PATH'] = './';
$REX['BACKEND_FOLDER'] = 'redaxo';
$REX['LOAD_PAGE'] = false;

require './redaxo/src/core/boot.php';
require './redaxo/src/core/packages.php';

$extensions = [
    '../../../../redaxo/src/addons/rexstan/vendor/phpstan/phpstan-deprecation-rules/rules.neon',
    '../../../../redaxo/src/addons/rexstan/config/phpstan-phpunit.neon',
];

$paths = ['../../../../redaxo/src/addons/' . getenv('ADDON_KEY') . '/'];

// rexstan 3 hat die Klasse umbenannt, rexstan 2 kennt nur den alten Namensraum
$configClass = class_exists(FriendsOfRedaxo\RexStan\RexStanUserConfig::class)
    ? FriendsOfRedaxo\RexStan\RexStanUserConfig::class
    : rexstan\RexStanUserConfig::class;

$configClass::save(5, $paths, $extensions, 80300);
