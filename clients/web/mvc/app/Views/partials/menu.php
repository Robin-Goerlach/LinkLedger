<?php
use App\Core\Session;
use App\Core\Auth;

Session::start();
$uid = Auth::userId();
?>
<div class="bg-white border-b border-slate-200">
  <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between">
    <div class="font-semibold">LinkLedger</div>
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
</div>
