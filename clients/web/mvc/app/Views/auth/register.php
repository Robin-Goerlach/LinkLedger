<div class="max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6">
  <h1 class="text-xl font-semibold">Register</h1>
  <form class="mt-4 space-y-3" method="post" action="<?= htmlspecialchars($_app->url('/register')) ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
    <div>
      <label class="block text-sm">E-Mail</label>
      <input class="w-full border rounded-xl p-2" name="email" type="email" required>
    </div>
    <div>
      <label class="block text-sm">Passwort</label>
      <input class="w-full border rounded-xl p-2" name="password" type="password" required>
    </div>
    <button class="bg-slate-900 text-white rounded-xl px-4 py-2">Konto anlegen</button>
  </form>
</div>
