<?php
declare(strict_types=1);

namespace FileDrop\Model;

use FileDrop\Support\Permissions;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $passwordHash,
        public bool $isAdmin,
        public bool $active,
        public array $permissions,
        public ?string $totpSecret,
        public int $totpStep,
        public int $createdAt,
        public ?int $lastLoginAt,
    ) {
    }

    public static function fromRow(array $r): self
    {
        $permissions = json_decode((string) $r['permissions'], true);

        return new self(
            (int) $r['id'],
            (string) $r['username'],
            (string) $r['password_hash'],
            (bool) $r['is_admin'],
            (bool) $r['active'],
            Permissions::normalize($permissions),
            $r['totp_secret'] === null ? null : (string) $r['totp_secret'],
            (int) $r['totp_step'],
            (int) $r['created_at'],
            $r['last_login_at'] === null ? null : (int) $r['last_login_at'],
        );
    }

    public function can(string $permission): bool
    {
        return $this->isAdmin || in_array($permission, $this->permissions, true);
    }

    public function hasTotp(): bool
    {
        return $this->totpSecret !== null && $this->totpSecret !== '';
    }

    public function authStamp(): string
    {
        return substr(hash('sha256', $this->passwordHash . '|' . ($this->totpSecret ?? '')), 0, 32);
    }
}