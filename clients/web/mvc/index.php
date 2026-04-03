<?php
/**
 * LinkLedger – Root index.php
 * ==========================
 * Dieser Einstiegspunkt erlaubt Hosting ohne /public als DocumentRoot.
 *
 * Wichtig:
 * - Wenn PHP < 8.0 ist, zeigen wir hier eine klare Fehlermeldung (statt HTTP 500).
 */
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "LinkLedger benötigt PHP 8.0+.\n";
    echo "Aktuell: " . PHP_VERSION . "\n";
    echo "Bitte in IONOS die PHP-Version auf 8.x stellen.\n";
    exit;
}

require __DIR__ . '/app/entry.php';
