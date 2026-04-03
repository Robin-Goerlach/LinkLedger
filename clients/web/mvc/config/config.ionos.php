<?php
/**
 * Beispiel-Konfiguration.
 *
 * Kopiere diese Datei nach config.php und trage deine DB-Zugangsdaten ein.
 */
return [
    'app' => [
        // base_path: falls die App in einem Unterverzeichnis läuft (z.B. '/apps/linkledger')
        // null => automatische Erkennung
        'base_path' => '/linkledger',

        // Debug-Schalter:
        // true  => detaillierte Error Page + PHP error_reporting
        // false => generische Error Page
        'debug' => true,

        // Debug-Meldungen in Browser-Konsole ausgeben (console.log)
        'debug_console' => true,

        // Log-Datei für Debug (zusätzlich zu console.log)
        'log_file' => __DIR__ . '/../storage/logs/app.log',
    ],

    'db' => [
        'host' => 'db5018365891.hosting-data.io',
        'name' => 'dbs14539044',
        'user' => 'dbu3159497',
        'pass' => 'pR8z#1Vn!qJ',
        'charset' => 'utf8mb4',
    ],
];
