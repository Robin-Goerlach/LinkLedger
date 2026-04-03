<?php if (!empty($_flash)): ?>
  <div class="max-w-7xl mx-auto px-4 pt-4 space-y-2">
    <?php foreach ($_flash as $m): ?>
      <?php
        $type = $m['type'] ?? 'info';
        $cls = 'bg-slate-100 text-slate-900 border-slate-200';
        if ($type === 'success') $cls = 'bg-green-100 text-green-900 border-green-200';
        if ($type === 'warn')    $cls = 'bg-yellow-100 text-yellow-900 border-yellow-200';
        if ($type === 'error')   $cls = 'bg-red-100 text-red-900 border-red-200';
      ?>
      <div class="border rounded-xl p-3 <?= $cls ?>">
        <?= htmlspecialchars((string)($m['message'] ?? '')) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
