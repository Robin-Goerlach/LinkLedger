<?php
/**
 * LinkLedger – public/index.php (Front Controller)
 * ===============================================
 * Standard-Einstiegspunkt, wenn der Webserver auf /public zeigt.
 */
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "LinkLedger benötigt PHP 8.0+. Aktuell: " . PHP_VERSION . "\n";
    exit;
}

require dirname(__DIR__) . '/app/entry.php';
