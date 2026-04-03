<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Debug;

/**
 * Class ProjectModel
 */
final class ProjectModel
{
    public function __construct(private PDO $pdo) {}

    public function list(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE user_id = :u ORDER BY name ASC");
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public function get(int $userId, int $projectId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = :p AND user_id = :u LIMIT 1");
        $stmt->execute([':p' => $projectId, ':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, string $name, ?string $description): int
    {
        Debug::log('Create project', ['userId' => $userId, 'name' => $name]);

        $stmt = $this->pdo->prepare("
            INSERT INTO projects (user_id, name, description)
            VALUES (:u, :n, :d)
        ");
        $stmt->execute([':u' => $userId, ':n' => $name, ':d' => $description]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $userId, int $projectId): bool
    {
        Debug::log('Delete project', ['userId' => $userId, 'projectId' => $projectId]);
        $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = :p AND user_id = :u");
        return (bool)$stmt->execute([':p' => $projectId, ':u' => $userId]);
    }
}
