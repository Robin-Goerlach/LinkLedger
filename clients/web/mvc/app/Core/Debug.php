<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Class Debug
 *
 * Ziel:
 * - Bei "HTTP ERROR 500" nicht im Dunkeln sitzen.
 *
 * Features:
 * - Debug-Schalter (enabled)
 * - Debug-Ausgabe als JavaScript console.log (debug_console)
 * - zusätzliches serverseitiges Logfile (log_file)
 *
 * Nutzung:
 * - Debug::init(...) wird in app/entry.php aufgerufen.
 * - Debug::log(...) kann überall aufgerufen werden.
 * - In Views wird Debug::consoleScript() eingebunden (wenn aktiviert).
 */
final class Debug
{
    private static bool $enabled = false;
    private static bool $console = false;
    private static string $requestId = '';
    private static ?string $logFile = null;

    /** @var array<int, array{ts:string, msg:string, ctx:array<string,mixed>}> */
    private static array $buffer = [];

    public static function init(bool $enabled, bool $console, string $requestId, ?string $logFile): void
    {
        self::$enabled = $enabled;
        self::$console = $console;
        self::$requestId = $requestId;
        self::$logFile = $logFile;

        self::log('Debug initialized', [
            'enabled' => $enabled,
            'console' => $console,
            'php' => PHP_VERSION,
        ]);
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function consoleEnabled(): bool
    {
        return self::$enabled && self::$console;
    }

    /**
     * Debug Log (nur wenn enabled=true)
     *
     * @param string $message
     * @param array<string,mixed> $context
     */
    public static function log(string $message, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }

        $row = [
            'ts' => gmdate('c'),
            'msg' => $message,
            'ctx' => $context,
        ];

        self::$buffer[] = $row;

        // Auch ins Logfile schreiben (wenn konfiguriert)
        if (self::$logFile) {
            self::appendFileLog($row);
        }
    }

    /**
     * Exceptions/Throwables loggen.
     */
    public static function exception(Throwable $e, string $phase = 'runtime'): void
    {
        self::log('Exception', [
            'phase' => $phase,
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Erzeugt <script>console.log(...)</script> für alle Debug-Meldungen.
     */
    public static function consoleScript(): string
    {
        if (!self::consoleEnabled()) {
            return '';
        }

        $payload = [
            'requestId' => self::$requestId,
            'count' => count(self::$buffer),
            'logs' => self::$buffer,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) {
            return '<script>console.log("LinkLedger debug: json_encode failed");</script>';
        }

        return "<script>
(function(){
  const p = $json;
  const prefix = '[LinkLedger][' + p.requestId + ']';
  console.groupCollapsed(prefix + ' debug logs (' + p.count + ')');
  for (const row of p.logs) {
    console.log(prefix, row.ts, row.msg, row.ctx);
  }
  console.groupEnd();
})();
</script>";
    }

    /**
     * @param array{ts:string,msg:string,ctx:array<string,mixed>} $row
     */
    private static function appendFileLog(array $row): void
    {
        $line = '[' . $row['ts'] . '][' . self::$requestId . '] ' . $row['msg'] . ' ' . json_encode($row['ctx']) . PHP_EOL;

        $dir = dirname((string)self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents((string)self::$logFile, $line, FILE_APPEND);
    }
}
