\
<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Class TagModel
 */
final class TagModel
{
    public function __construct(private PDO $pdo) {}

    public function list(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tags WHERE user_id = :u ORDER BY name ASC");
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, string $name): array
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO tags (user_id, name) VALUES (:u, :n)");
            $stmt->execute([':u' => $userId, ':n' => $name]);
            return ['created' => true, 'id' => (int)$this->pdo->lastInsertId()];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['created' => false, 'duplicate' => true, 'message' => 'Tag existiert bereits.'];
            }
            throw $e;
        }
    }

    public function delete(int $userId, int $tagId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tags WHERE id = :t AND user_id = :u");
        return (bool)$stmt->execute([':t' => $tagId, ':u' => $userId]);
    }
}
