\
<?php
/**
 * Beispiel-Konfiguration.
 *
 * Kopiere diese Datei nach config.php und trage deine DB-Zugangsdaten ein.
 */
return [
    'app' => [
        /**
         * base_path:
         * - Wenn du NICHT auf Domain-Root hostest, kannst du hier einen festen Prefix setzen,
         *   z.B. '/apps/sasd-links' oder '/apps/sasd-links/public'.
         * - Wenn null: automatische Erkennung über SCRIPT_NAME.
         */
        'base_path' => null,

        /**
         * debug:
         * - true: ausführliche Fehlermeldungen (DEV)
         * - false: generische Fehlermeldungen (PROD)
         */
        'debug' => true,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'name' => 'sasd_links',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
