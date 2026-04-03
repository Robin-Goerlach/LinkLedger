<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Debug;

/**
 * Class UserModel
 */
final class UserModel
{
    public function __construct(private PDO $pdo) {}

    public function create(string $email, string $password): int
    {
        // Passwort niemals loggen!
        Debug::log('Create user', ['email' => $email]);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:e, :h)");
        $stmt->execute([':e' => $email, ':h' => $hash]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
