<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use FileDrop\Model\User;
use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function all(): array
    {
        return array_map(User::fromRow(...), $this->pdo->query('SELECT * FROM users ORDER BY is_admin DESC, LOWER(username)')->fetchAll());
    }

    public function createAdmin(string $username, string $passwordHash, int $now): ?int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, is_admin, active, permissions, created_at)
             SELECT ?, ?, TRUE, TRUE, ?, ? WHERE NOT EXISTS (SELECT 1 FROM users)
             RETURNING id'
        );
        try {
            $stmt->execute([$username, $passwordHash, '[]', $now]);
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                return null;
            }
            throw $e;
        }
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function create(string $username, string $passwordHash, array $permissions, int $now): ?int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, permissions, created_at) VALUES (?, ?, ?, ?) RETURNING id'
        );
        try {
            $stmt->execute([$username, $passwordHash, json_encode($permissions), $now]);
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                return null;
            }
            throw $e;
        }

        return (int) $stmt->fetchColumn();
    }

    public function updateAccess(int $id, array $permissions, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET permissions = :permissions, active = :active WHERE id = :id AND NOT is_admin');
        $stmt->bindValue('permissions', json_encode($permissions));
        $stmt->bindValue('active', $active, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setPassword(int $id, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public function setTotp(int $id, ?string $secret, int $step = 0): void
    {
        $this->pdo->prepare('UPDATE users SET totp_secret = ?, totp_step = ? WHERE id = ?')->execute([$secret, $step, $id]);
    }

    public function claimTotpStep(int $id, int $step): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET totp_step = ? WHERE id = ? AND totp_step < ?');
        $stmt->execute([$step, $id, $step]);

        return $stmt->rowCount() === 1;
    }

    public function touchLogin(int $id, int $now): void
    {
        $this->pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([$now, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ? AND NOT is_admin');
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }
}