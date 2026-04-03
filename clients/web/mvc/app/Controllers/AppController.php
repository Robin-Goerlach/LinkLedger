<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\ProjectModel;
use App\Models\LinkModel;
use App\Models\TagModel;

/**
 * Class AppController
 *
 * Wunderlist-Style Hauptscreen + Actions.
 */
final class AppController extends BaseController
{
    public function __construct(
        App $app,
        View $view,
        private ProjectModel $projects,
        private LinkModel $links,
        private TagModel $tags
    ) {
        parent::__construct($app, $view);
    }

    public function home(): void
    {
        $this->redirect('/app');
    }

    public function show(array $params, array $query): void
    {
        Session::start();
        $uid = Auth::requireLogin($this->app);

        $q = trim((string)($query['q'] ?? ''));
        $tagId = (int)($query['tag_id'] ?? 0);
        $projectId = (int)($query['project_id'] ?? 0);
        $linkId = (int)($query['link_id'] ?? 0);

        $projects = $this->projects->list($uid);

        // Seed "Inbox", wenn leer (wie im Windows Client)
        if (empty($projects)) {
            try { $this->projects->create($uid, 'Inbox', 'Standard-Projekt (Auto)'); } catch (\Throwable $e) {}
            $projects = $this->projects->list($uid);
        }

        $selectedProject = null;
        if ($projectId > 0) $selectedProject = $this->projects->get($uid, $projectId);
        if (!$selectedProject && !empty($projects)) {
            $selectedProject = $projects[0];
            $projectId = (int)$selectedProject['id'];
        }

        $allTags = $this->tags->list($uid);

        $links = [];
        if ($selectedProject) {
            $links = $this->links->listFiltered($uid, (int)$selectedProject['id'], ['q' => $q, 'tag_id' => $tagId]);
        }

        $selectedLink = null;
        if ($linkId > 0) $selectedLink = $this->links->get($uid, $linkId);
        if (!$selectedLink && !empty($links)) {
            $selectedLink = $links[0];
            $linkId = (int)$selectedLink['id'];
        }

        $selectedLinkTags = [];
        if ($selectedLink) $selectedLinkTags = $this->links->listTagsForLink($uid, (int)$selectedLink['id']);

        $old = Session::consumeFlashData('link_form', []);
        if (!is_array($old)) $old = [];

        $this->view->render('app/main', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'links' => $links,
            'selectedLink' => $selectedLink,
            'selectedLinkTags' => $selectedLinkTags,
            'tags' => $allTags,
            'filter' => ['q' => $q, 'tag_id' => $tagId],
            'selection' => ['project_id' => $projectId, 'link_id' => $linkId],
            'old' => $old,
        ]);
    }

    public function createProject(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            Session::flash('warn', 'Projektname fehlt.');
            $this->redirect('/app');
        }

        try {
            $id = $this->projects->create($uid, $name, $desc !== '' ? $desc : null);
            Session::flash('success', 'Projekt angelegt.');
            $this->redirect('/app', ['project_id' => $id]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') Session::flash('warn', 'Projektname existiert bereits.');
            else Session::flash('error', 'DB Fehler beim Anlegen des Projekts.');
            $this->redirect('/app');
        }
    }

    public function deleteProject(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            Session::flash('warn', 'Kein Projekt ausgewählt.');
            $this->redirect('/app');
        }

        $this->projects->delete($uid, $projectId);
        Session::flash('success', 'Projekt gelöscht.');
        $this->redirect('/app');
    }

    public function saveLink(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);

        $url = (string)($_POST['url'] ?? '');
        $title = trim((string)($_POST['title'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        // Formdaten merken (falls Validierung scheitert)
        Session::flashData('link_form', ['url' => $url, 'title' => $title, 'description' => $desc, 'link_id' => $linkId]);

        if ($projectId <= 0) {
            Session::flash('error', 'Kein Projekt ausgewählt.');
            $this->redirect('/app');
        }

        if ($linkId > 0) {
            $res = $this->links->update($uid, $linkId, $url, $title !== '' ? $title : null, $desc !== '' ? $desc : null);

            if (!empty($res['validation_error'])) Session::flash('warn', 'URL ungültig: ' . ($res['message'] ?? ''));
            elseif (!empty($res['duplicate'])) Session::flash('warn', $res['message'] ?? 'Duplikat.');
            elseif (!empty($res['not_found'])) Session::flash('error', 'Link nicht gefunden.');
            else {
                Session::flash('success', 'Link gespeichert.');
                Session::flashData('link_form', []);
            }

            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
        }

        $res = $this->links->create($uid, $projectId, $url, $title !== '' ? $title : null, $desc !== '' ? $desc : null);

        if (!empty($res['validation_error'])) {
            Session::flash('warn', 'URL ungültig: ' . ($res['message'] ?? ''));
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => 0]);
        }
        if (!empty($res['duplicate'])) {
            Session::flash('warn', $res['message'] ?? 'URL doppelt.');
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => 0]);
        }

        $newId = (int)($res['id'] ?? 0);
        Session::flash('success', 'Link gespeichert.');
        Session::flashData('link_form', []);
        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $newId]);
    }

    public function deleteLink(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);

        if ($linkId <= 0) {
            Session::flash('warn', 'Kein Link ausgewählt.');
            $this->redirect('/app', ['project_id' => $projectId]);
        }

        $this->links->delete($uid, $linkId);
        Session::flash('success', 'Link gelöscht.');
        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => 0]);
    }

    public function newLinkMode(): void
    {
        $this->requireCsrf();
        Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        Session::flashData('link_form', []);
        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => 0]);
    }

    public function createTag(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $name = trim((string)($_POST['name'] ?? ''));
        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);

        if ($name === '') {
            Session::flash('warn', 'Tag-Name fehlt.');
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
        }

        $res = $this->tags->create($uid, $name);
        if (!empty($res['duplicate'])) Session::flash('warn', $res['message'] ?? 'Tag existiert bereits.');
        else Session::flash('success', 'Tag angelegt.');

        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
    }

    public function deleteTag(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $tagId = (int)($_POST['tag_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);

        if ($tagId <= 0) {
            Session::flash('warn', 'Kein Tag ausgewählt.');
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
        }

        $this->tags->delete($uid, $tagId);
        Session::flash('success', 'Tag gelöscht.');
        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
    }

    public function addTagToLink(): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);
        $tagId = (int)($_POST['tag_id'] ?? 0);

        if ($linkId <= 0 || $tagId <= 0) {
            Session::flash('warn', 'Bitte Link und Tag auswählen.');
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
        }

        $res = $this->links->assignTag($uid, $linkId, $tagId);
        if (!$res['ok']) Session::flash('warn', 'Tag konnte nicht zugewiesen werden: ' . ($res['error'] ?? ''));
        else Session::flash('success', 'Tag zugewiesen.');

        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
    }

    public function removeTagFromLink(array $params): void
    {
        $this->requireCsrf();
        $uid = Auth::requireLogin($this->app);

        $projectId = (int)($_POST['project_id'] ?? 0);
        $linkId = (int)($_POST['link_id'] ?? 0);
        $tagId = (int)($params['tagId'] ?? 0);

        if ($linkId <= 0 || $tagId <= 0) {
            Session::flash('warn', 'Ungültige Parameter.');
            $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
        }

        $this->links->unassignTag($uid, $linkId, $tagId);
        Session::flash('success', 'Tag entfernt.');
        $this->redirect('/app', ['project_id' => $projectId, 'link_id' => $linkId]);
    }
}
