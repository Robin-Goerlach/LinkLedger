<?php
/**
 * Hauptansicht (Wunderlist-Style)
 *
 * 3 Spalten:
 * - Links: Projekte
 * - Mitte: Links/URLs + Suche/Filter
 * - Rechts: Details + Tags
 */

function qs(array $base, array $extra = []): string {
    $m = array_merge($base, $extra);
    foreach ($m as $k => $v) {
        if ($v === null || $v === '' || $v === 0) unset($m[$k]);
    }
    return http_build_query($m);
}

$projectId = (int)($selection['project_id'] ?? 0);
$linkId = (int)($selection['link_id'] ?? 0);
$q = (string)($filter['q'] ?? '');
$tagId = (int)($filter['tag_id'] ?? 0);

$formUrl = $old['url'] ?? ($selectedLink['url'] ?? '');
$formTitle = $old['title'] ?? ($selectedLink['title'] ?? '');
$formDesc = $old['description'] ?? ($selectedLink['description'] ?? '');
$formLinkId = (int)($old['link_id'] ?? ($selectedLink['id'] ?? 0));
?>
<div class="max-w-7xl mx-auto">
  <div class="grid grid-cols-12 gap-4" style="min-height: 70vh;">
    <!-- Left: Projects -->
    <aside class="col-span-12 md:col-span-3 bg-slate-800 text-white rounded-2xl p-4">
      <h2 class="text-lg font-semibold mb-3">Projekte</h2>

      <form method="post" action="<?= htmlspecialchars($_app->url('/projects/create')) ?>" class="space-y-2 mb-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="text" name="name" placeholder="Neues Projekt..." class="w-full rounded-xl p-2 text-slate-900" required>
        <input type="text" name="description" placeholder="Beschreibung (optional)" class="w-full rounded-xl p-2 text-slate-900">
        <button class="w-full bg-white/10 hover:bg-white/20 rounded-xl py-2 text-sm">+ Projekt anlegen</button>
      </form>

      <form method="post" action="<?= htmlspecialchars($_app->url('/projects/delete')) ?>" class="mb-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
        <button class="w-full bg-red-500/20 hover:bg-red-500/30 rounded-xl py-2 text-sm">Projekt löschen</button>
      </form>

      <nav class="space-y-1">
        <?php foreach ($projects as $p): ?>
          <?php $active = ((int)$p['id'] === $projectId); ?>
          <a class="block rounded-xl px-3 py-2 <?= $active ? 'bg-sky-500/30' : 'hover:bg-white/10' ?>"
             href="<?= htmlspecialchars($_app->url('/app') . '?' . qs(['project_id' => (int)$p['id'], 'link_id' => 0, 'q' => $q, 'tag_id' => $tagId])) ?>">
             <?= htmlspecialchars((string)$p['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <!-- Middle: Links list + search/filter -->
    <section class="col-span-12 md:col-span-5 bg-white rounded-2xl p-4 border">
      <div class="mb-3">
        <form class="flex gap-2" method="get" action="<?= htmlspecialchars($_app->url('/app')) ?>">
          <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
          <input type="hidden" name="link_id" value="<?= (int)$linkId ?>">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Suche..." class="flex-1 border rounded-xl p-2">
          <select name="tag_id" class="border rounded-xl p-2">
            <option value="0">Tag-Filter</option>
            <?php foreach ($tags as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === $tagId) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="border rounded-xl px-3">Filter</button>
        </form>
      </div>

      <div class="mb-3 flex flex-wrap gap-2">
        <form method="post" action="<?= htmlspecialchars($_app->url('/links/new')) ?>">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
          <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
          <button class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm">Neu</button>
        </form>

        <a class="px-3 py-2 rounded-xl border text-sm"
           href="<?= htmlspecialchars($_app->url('/export/csv') . '?' . qs(['project_id' => (int)$projectId])) ?>">Export CSV</a>

        <a class="px-3 py-2 rounded-xl border text-sm"
           href="<?= htmlspecialchars($_app->url('/export/json') . '?' . qs(['project_id' => (int)$projectId])) ?>">Export JSON</a>
      </div>

      <div class="divide-y">
        <?php if (empty($links)): ?>
          <div class="text-slate-600 py-4">Keine Links gefunden.</div>
        <?php endif; ?>

        <?php foreach ($links as $l): ?>
          <?php $isActive = ((int)$l['id'] === $linkId); ?>
          <a class="block py-3 <?= $isActive ? 'bg-slate-50 rounded-xl px-3' : '' ?>"
             href="<?= htmlspecialchars($_app->url('/app') . '?' . qs(['project_id' => $projectId, 'link_id' => (int)$l['id'], 'q' => $q, 'tag_id' => $tagId])) ?>">
            <div class="font-semibold"><?= htmlspecialchars((string)($l['title'] ?? '(ohne Titel)')) ?></div>
            <div class="text-sm text-slate-500 break-all"><?= htmlspecialchars((string)$l['url']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Right: Details -->
    <section class="col-span-12 md:col-span-4 bg-white rounded-2xl p-4 border">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">Details</h2>
        <?php if ($formUrl): ?>
          <a class="text-sm text-slate-600 hover:underline" href="<?= htmlspecialchars($formUrl) ?>" target="_blank">open ↗</a>
        <?php endif; ?>
      </div>

      <form method="post" action="<?= htmlspecialchars($_app->url('/links/save')) ?>" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
        <input type="hidden" name="link_id" value="<?= (int)$formLinkId ?>">

        <div>
          <label class="block text-sm font-semibold">URL</label>
          <input class="w-full border rounded-xl p-2" name="url" value="<?= htmlspecialchars((string)$formUrl) ?>" placeholder="https://example.com oder example.com">
          <div class="text-xs text-slate-500 mt-1">Nur http/https. Scheme wird ergänzt, falls es fehlt.</div>
        </div>

        <div>
          <label class="block text-sm font-semibold">Titel</label>
          <input class="w-full border rounded-xl p-2" name="title" value="<?= htmlspecialchars((string)$formTitle) ?>">
        </div>

        <div>
          <label class="block text-sm font-semibold">Beschreibung</label>
          <textarea class="w-full border rounded-xl p-2" name="description" rows="4"><?= htmlspecialchars((string)$formDesc) ?></textarea>
        </div>

        <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm">Speichern</button>
      </form>

      <?php if ($formLinkId > 0): ?>
        <form method="post" action="<?= htmlspecialchars($_app->url('/links/delete')) ?>" class="mt-2">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
          <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
          <input type="hidden" name="link_id" value="<?= (int)$formLinkId ?>">
          <button class="px-4 py-2 rounded-xl border text-sm">Löschen</button>
        </form>
      <?php endif; ?>

      <hr class="my-4">

      <h3 class="font-semibold mb-2">Tags</h3>

      <?php if (!$selectedLink): ?>
        <div class="text-sm text-slate-600">Wähle zuerst einen Link aus (oder lege einen an), um Tags zuzuweisen.</div>
      <?php else: ?>
        <form method="post" action="<?= htmlspecialchars($_app->url('/links/tags/add')) ?>" class="flex gap-2 items-center mb-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
          <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
          <input type="hidden" name="link_id" value="<?= (int)$selectedLink['id'] ?>">

          <select name="tag_id" class="flex-1 border rounded-xl p-2">
            <option value="0">Tag hinzufügen...</option>
            <?php foreach ($tags as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars((string)$t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="px-3 py-2 rounded-xl bg-sky-600 text-white text-sm">Hinzufügen</button>
        </form>

        <div class="flex flex-wrap gap-2">
          <?php foreach ($selectedLinkTags as $t): ?>
            <form method="post" action="<?= htmlspecialchars($_app->url('/links/tags/remove/' . (int)$t['id'])) ?>">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
              <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
              <input type="hidden" name="link_id" value="<?= (int)$selectedLink['id'] ?>">
              <button class="px-3 py-1 rounded-full bg-slate-200 text-sm">
                <?= htmlspecialchars((string)$t['name']) ?> <span class="ml-1 text-slate-600">×</span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <hr class="my-4">

      <h3 class="font-semibold mb-2">Tag-Verwaltung (kompakt)</h3>

      <form method="post" action="<?= htmlspecialchars($_app->url('/tags/create')) ?>" class="flex gap-2 mb-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
        <input type="hidden" name="link_id" value="<?= (int)$linkId ?>">

        <input name="name" class="flex-1 border rounded-xl p-2" placeholder="Neuer Tag...">
        <button class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm">+</button>
      </form>

      <form method="post" action="<?= htmlspecialchars($_app->url('/tags/delete')) ?>" class="flex gap-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
        <input type="hidden" name="link_id" value="<?= (int)$linkId ?>">

        <select name="tag_id" class="flex-1 border rounded-xl p-2">
          <option value="0">Tag löschen...</option>
          <?php foreach ($tags as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars((string)$t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="px-3 py-2 rounded-xl border text-sm">Löschen</button>
      </form>
    </section>
  </div>
</div>
