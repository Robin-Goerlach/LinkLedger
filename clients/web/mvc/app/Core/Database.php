<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Class Database
 *
 * - PDO-Verbindung
 * - Auto-Schema Init (CREATE TABLE IF NOT EXISTS ...)
 *
 * Debugging:
 * - Bei Verbindungsproblemen wird eine Exception geworfen und im Debug-Log protokolliert.
 */
final class Database
{
    private PDO $pdo;

    public function __construct(array $cfg)
    {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $cfg['host'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        Debug::log('DB connect attempt', ['dsn' => $dsn, 'user' => $cfg['user'] ?? '']);

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            Debug::exception($e, 'db_connect');
            throw $e;
        }

        $this->pdo->exec("SET NAMES utf8mb4");
        $this->pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES'");

        $this->initSchema();
        Debug::log('DB schema init done');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function initSchema(): void
    {
        $sql = [];

        $sql[] = "
        CREATE TABLE IF NOT EXISTS users (
          id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
          email VARCHAR(255) NOT NULL,
          password_hash VARCHAR(255) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql[] = "
        CREATE TABLE IF NOT EXISTS projects (
          id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          name VARCHAR(200) NOT NULL,
          description TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_projects_user_name (user_id, name),
          KEY ix_projects_user (user_id),
          CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql[] = "
        CREATE TABLE IF NOT EXISTS tags (
          id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          name VARCHAR(80) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_tags_user_name (user_id, name),
          KEY ix_tags_user (user_id),
          CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql[] = "
        CREATE TABLE IF NOT EXISTS links (
          id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          project_id BIGINT UNSIGNED NOT NULL,
          url TEXT NOT NULL,
          canonical_url TEXT NOT NULL,
          canonical_hash CHAR(64) NOT NULL,
          title VARCHAR(500) NULL,
          description TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_links_dup (user_id, project_id, canonical_hash),
          KEY ix_links_user (user_id),
          KEY ix_links_project (project_id),
          CONSTRAINT fk_links_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE,
          CONSTRAINT fk_links_project FOREIGN KEY (project_id) REFERENCES projects(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql[] = "
        CREATE TABLE IF NOT EXISTS link_tags (
          link_id BIGINT UNSIGNED NOT NULL,
          tag_id BIGINT UNSIGNED NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (link_id, tag_id),
          CONSTRAINT fk_link_tags_link FOREIGN KEY (link_id) REFERENCES links(id)
            ON DELETE CASCADE,
          CONSTRAINT fk_link_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        foreach ($sql as $stmt) {
            $this->pdo->exec($stmt);
        }
    }
}
