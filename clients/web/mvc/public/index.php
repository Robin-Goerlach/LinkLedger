<?php
declare(strict_types=1);

/**
 * public/index.php (Front Controller)
 * -----------------------------------
 * Standard-Einstiegspunkt, wenn dein Webserver auf /public zeigt.
 * In beiden Fällen (Root oder /public) nutzen wir das gleiche Bootstrapping.
 */

require dirname(__DIR__) . '/app/entry.php';
