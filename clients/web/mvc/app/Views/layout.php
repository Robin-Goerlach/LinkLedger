<?php
/**
 * Layout
 *
 * - Tailwind via CDN
 * - Debug console logs (falls aktiviert)
 */
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LinkLedger</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
  <?php include __DIR__ . '/partials/menu.php'; ?>
  <?php include __DIR__ . '/partials/flash.php'; ?>
  <main class="p-4"><?php include $contentTemplate; ?></main>
  <footer class="p-4 text-xs text-slate-500">request_id: <?= htmlspecialchars($_app->requestId()) ?></footer>
  <?= $_debug_console_script ?>
</body>
</html>
