\
<?php
use App\Core\Session;
use App\Core\Auth;

Session::start();
$uid = Auth::userId();
?>
<div class="bg-white border-b border-slate-200">
  <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between">
    <div class="font-semibold">SASD Links</div>
    <div class="text-sm text-slate-600">
      <?php if ($uid): ?>
        Eingeloggt
        <a class="ml-3 text-slate-700 hover:underline" href="<?= htmlspecialchars($_app->url('/logout')) ?>">Logout</a>
      <?php else: ?>
        <a class="text-slate-700 hover:underline" href="<?= htmlspecialchars($_app->url('/login')) ?>">Login</a>
        <a class="ml-3 text-slate-700 hover:underline" href="<?= htmlspecialchars($_app->url('/register')) ?>">Register</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($uid): ?>
  <div class="max-w-7xl mx-auto px-4 pb-3">
    <div class="flex flex-wrap gap-2">
      <div class="flex items-center gap-2 bg-slate-50 border rounded-xl p-2">
        <span class="text-xs text-slate-500 mr-1">Start</span>
        <a class="px-3 py-1 rounded-lg bg-slate-900 text-white text-sm" href="<?= htmlspecialchars($_app->url('/app')) ?>">App</a>
      </div>

      <div class="flex items-center gap-2 bg-slate-50 border rounded-xl p-2">
        <span class="text-xs text-slate-500 mr-1">Export</span>
        <a class="px-3 py-1 rounded-lg border text-sm" href="<?= htmlspecialchars($_app->url('/export/json')) ?>">JSON</a>
        <a class="px-3 py-1 rounded-lg border text-sm" href="<?= htmlspecialchars($_app->url('/export/csv')) ?>">CSV</a>
      </div>

      <div class="flex items-center gap-2 bg-slate-50 border rounded-xl p-2">
        <span class="text-xs text-slate-500 mr-1">Hilfe</span>
        <span class="text-sm text-slate-600">Wunderlist-Style MVC Demo</span>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
