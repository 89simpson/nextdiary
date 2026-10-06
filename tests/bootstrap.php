<?php

/**
 * PHPUnit bootstrap for NextDiary.
 *
 * The app lives in <server>/apps/nextdiary, so the Nextcloud server's own test bootstrap is
 * three levels up. It defines PHPUNIT_RUN, boots the server (lib/base.php) and loads the apps;
 * this works on every supported server version (25+). The old `\OC::$loader` entry point is
 * gone since Nextcloud 32 and must not be used here.
 */

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Make sure the app and its autoloader are registered even if it is not enabled yet.
$appManager = \OC::$server->get(\OCP\App\IAppManager::class);
if (method_exists($appManager, 'loadApp')) {
    // Nextcloud 27+
    $appManager->loadApp('nextdiary');
} else {
    // Nextcloud 25/26
    \OC_App::loadApp('nextdiary');
}

\OC_Hook::clear();
