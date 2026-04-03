<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Validator;
use App\Core\Debug;

/**
 * Class LinkModel
 *
 * Verantwortlich für:
 * - Links anlegen/ändern/löschen
 * - Filter (Suche + Tag)
 * - Tag-Zuweisung (Many-to-many via link_tags)
 * - Export (links + tags)
 */
final class LinkModel
{
    public function __construct(private PDO $pdo) {}

    public function listFiltered(int $userId, int $projectId, array $filter): array
    {
        $q = trim((string)($filter['q'] ?? ''));
        $tagId = (int)($filter['tag_id'] ?? 0);

        Debug::log('List links', ['userId' => $userId, 'projectId' => $projectId, 'q' => $q, 'tagId' => $tagId]);

        $where = ["l.user_id = :u", "l.project_id = :p"];
        $params = [':u' => $userId, ':p' => $projectId];
        $join = "";

        if ($tagId > 0) {
            $join .= " JOIN link_tags lt ON lt.link_id = l.id ";
            $where[] = "lt.tag_id = :tag";
            $params[':tag'] = $tagId;
        }

        if ($q !== '') {
            $where[] = "(l.url LIKE :q OR l.title LIKE :q OR l.description LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $whereSql = "WHERE " . implode(" AND ", $where);

        $stmt = $this->pdo->prepare("
          SELECT DISTINCT l.*
          FROM links l
          $join
          $whereSql
          ORDER BY l.updated_at DESC, l.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function get(int $userId, int $linkId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM links WHERE id = :id AND user_id = :u LIMIT 1");
        $stmt->execute([':id' => $linkId, ':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, int $projectId, string $urlInput, ?string $title, ?string $description): array
    {
        Debug::log('Create link', ['userId' => $userId, 'projectId' => $projectId]);

        $val = Validator::validateUrl($urlInput);
        if (!$val['ok']) {
            return ['created' => false, 'validation_error' => true, 'message' => $val['error']];
        }

        $url = $val['url'];
        $canonical = Validator::canonicalizeUrl($url);
        $hash = Validator::sha256($canonical);

        try {
            $stmt = $this->pdo->prepare("
              INSERT INTO links (user_id, project_id, url, canonical_url, canonical_hash, title, description)
              VALUES (:u, :p, :url, :c, :h, :t, :d)
            ");
            $stmt->execute([
                ':u' => $userId,
                ':p' => $projectId,
                ':url' => $url,
                ':c' => $canonical,
                ':h' => $hash,
                ':t' => $title,
                ':d' => $description,
            ]);
            return ['created' => true, 'id' => (int)$this->pdo->lastInsertId()];
        } catch (\PDOException $e) {
            Debug::exception($e, 'link_create');
            if ($e->getCode() === '23000') {
                return ['created' => false, 'duplicate' => true, 'message' => 'Diese URL existiert im Projekt bereits.'];
            }
            throw $e;
        }
    }

    public function update(int $userId, int $linkId, string $urlInput, ?string $title, ?string $description): array
    {
        Debug::log('Update link', ['userId' => $userId, 'linkId' => $linkId]);

        if (!$this->get($userId, $linkId)) {
            return ['ok' => false, 'not_found' => true];
        }

        $val = Validator::validateUrl($urlInput);
        if (!$val['ok']) {
            return ['ok' => false, 'validation_error' => true, 'message' => $val['error']];
        }

        $url = $val['url'];
        $canonical = Validator::canonicalizeUrl($url);
        $hash = Validator::sha256($canonical);

        try {
            $stmt = $this->pdo->prepare("
              UPDATE links
              SET url = :url, canonical_url = :c, canonical_hash = :h,
                  title = :t, description = :d
              WHERE id = :id AND user_id = :u
            ");
            $stmt->execute([
                ':url' => $url,
                ':c' => $canonical,
                ':h' => $hash,
                ':t' => $title,
                ':d' => $description,
                ':id' => $linkId,
                ':u' => $userId,
            ]);
            return ['ok' => true];
        } catch (\PDOException $e) {
            Debug::exception($e, 'link_update');
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'duplicate' => true, 'message' => 'Änderung würde ein Duplikat erzeugen.'];
            }
            throw $e;
        }
    }

    public function delete(int $userId, int $linkId): bool
    {
        Debug::log('Delete link', ['userId' => $userId, 'linkId' => $linkId]);
        $stmt = $this->pdo->prepare("DELETE FROM links WHERE id = :id AND user_id = :u");
        return (bool)$stmt->execute([':id' => $linkId, ':u' => $userId]);
    }

    public function listTagsForLink(int $userId, int $linkId): array
    {
        if (!$this->get($userId, $linkId)) return [];
        $stmt = $this->pdo->prepare("
          SELECT t.*
          FROM tags t
          JOIN link_tags lt ON lt.tag_id = t.id
          WHERE lt.link_id = :l
          ORDER BY t.name ASC
        ");
        $stmt->execute([':l' => $linkId]);
        return $stmt->fetchAll();
    }

    public function assignTag(int $userId, int $linkId, int $tagId): array
    {
        Debug::log('Assign tag', ['linkId' => $linkId, 'tagId' => $tagId]);

        if (!$this->get($userId, $linkId)) return ['ok' => false, 'error' => 'link_not_found'];

        $stmt = $this->pdo->prepare("SELECT id FROM tags WHERE id = :t AND user_id = :u LIMIT 1");
        $stmt->execute([':t' => $tagId, ':u' => $userId]);
        if (!$stmt->fetch()) return ['ok' => false, 'error' => 'tag_not_found'];

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO link_tags (link_id, tag_id) VALUES (:l, :t)");
        $stmt->execute([':l' => $linkId, ':t' => $tagId]);

        return ['ok' => true];
    }

    public function unassignTag(int $userId, int $linkId, int $tagId): array
    {
        Debug::log('Unassign tag', ['linkId' => $linkId, 'tagId' => $tagId]);

        if (!$this->get($userId, $linkId)) return ['ok' => false, 'error' => 'link_not_found'];

        $stmt = $this->pdo->prepare("DELETE FROM link_tags WHERE link_id = :l AND tag_id = :t");
        $stmt->execute([':l' => $linkId, ':t' => $tagId]);

        return ['ok' => true];
    }

    public function exportLinksWithTags(int $userId, ?int $projectId = null): array
    {
        $where = "WHERE l.user_id = :u";
        $params = [':u' => $userId];
        if ($projectId) {
            $where .= " AND l.project_id = :p";
            $params[':p'] = $projectId;
        }

        $stmt = $this->pdo->prepare("SELECT l.* FROM links l $where ORDER BY l.updated_at DESC");
        $stmt->execute($params);
        $links = $stmt->fetchAll();

        foreach ($links as &$l) {
            $tags = $this->listTagsForLink($userId, (int)$l['id']);
            $l['tags'] = array_map(fn($t) => ['id' => (int)$t['id'], 'name' => (string)$t['name']], $tags);
        }
        unset($l);

        return $links;
    }
}
