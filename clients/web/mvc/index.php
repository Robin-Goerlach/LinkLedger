<?php
declare(strict_types=1);

/**
 * Root index.php (Code-Root)
 * --------------------------
 * Dieser Einstiegspunkt ermöglicht das Hosting, ohne dass der Webserver
 * zwingend auf /public zeigen muss.
 *
 * Empfehlung (Produktion):
 * - DocumentRoot auf /public setzen (Sicherheitsbest practice).
 * Für Lern-/Demo-Setups unterstützen wir aber beide Varianten.
 */

require __DIR__ . '/app/entry.php';
