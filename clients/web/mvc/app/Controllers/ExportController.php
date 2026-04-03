<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Session;
use App\Models\ProjectModel;
use App\Models\TagModel;
use App\Models\LinkModel;

/**
 * Class ExportController
 *
 * Export wie im Windows Client:
 * - JSON Snapshot
 * - CSV (Excel, UTF-8 BOM)
 */
final class ExportController extends BaseController
{
    public function __construct(
        App $app,
        \App\Core\View $view,
        private ProjectModel $projects,
        private TagModel $tags,
        private LinkModel $links
    ) {
        parent::__construct($app, $view);
    }

    public function exportJson(array $params, array $query): void
    {
        Session::start();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($query['project_id'] ?? 0);

        $projects = $this->projects->list($uid);
        $tags = $this->tags->list($uid);
        $links = $this->links->exportLinksWithTags($uid, $projectId > 0 ? $projectId : null);

        $snapshot = [
            'schema_version' => 1,
            'generated_at' => gmdate('c'),
            'projects' => $projects,
            'tags' => $tags,
            'links' => $links,
        ];

        $file = 'sasdlinks_export_' . gmdate('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        echo json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public function exportCsv(array $params, array $query): void
    {
        Session::start();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($query['project_id'] ?? 0);

        $projects = $this->projects->list($uid);
        $links = $this->links->exportLinksWithTags($uid, $projectId > 0 ? $projectId : null);

        $projectNameById = [];
        foreach ($projects as $p) {
            $projectNameById[(int)$p['id']] = (string)$p['name'];
        }

        $rows = [];
        $rows[] = "project;url;title;description;tags";

        foreach ($links as $l) {
            $pname = $projectNameById[(int)$l['project_id']] ?? '';
            $tagNames = [];
            foreach (($l['tags'] ?? []) as $t) {
                $tagNames[] = (string)($t['name'] ?? '');
            }
            $tagsJoined = implode(',', array_filter($tagNames));

            $rows[] = implode(';', [
                $this->csv($pname),
                $this->csv((string)$l['url']),
                $this->csv((string)($l['title'] ?? '')),
                $this->csv((string)($l['description'] ?? '')),
                $this->csv($tagsJoined),
            ]);
        }

        $file = $projectId > 0
            ? ('links_project_' . $projectId . '_' . gmdate('Y-m-d') . '.csv')
            : ('links_' . gmdate('Y-m-d') . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');

        echo \"\\xEF\\xBB\\xBF\"; // UTF-8 BOM für Excel
        echo implode(\"\\r\\n\", $rows);
        exit;
    }

    private function csv(string $value): string
    {
        if (strpbrk($value, \";\\n\\r\\\"\") === false) {
            return $value;
        }
        return '\"' . str_replace('\"', '\"\"', $value) . '\"';
    }
}
