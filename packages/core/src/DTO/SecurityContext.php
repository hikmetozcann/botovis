<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

/**
 * Security context for the current request
 */
final class SecurityContext
{
    public function __construct(
        public readonly ?string $userId,
        public readonly ?string $userRole,
        public readonly array $allowedTables = [],
        public readonly array $permissions = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * Check if user can perform action on table
     */
    public function can(string $table, string $action): bool
    {
        // If no restrictions, allow all
        if (empty($this->permissions) && empty($this->allowedTables)) {
            return true;
        }

        // Check table-level permissions
        if (isset($this->permissions[$table])) {
            return in_array($action, $this->permissions[$table], true)
                || in_array('*', $this->permissions[$table], true);
        }

        // Check if table is in allowed list (with wildcard action)
        if (in_array($table, $this->allowedTables, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get list of tables user can access
     */
    public function getAccessibleTables(): array
    {
        return array_unique(array_merge(
            $this->allowedTables,
            array_keys($this->permissions)
        ));
    }

    /**
     * Get actions allowed for a specific table
     */
    public function getAllowedActions(string $table): array
    {
        return $this->permissions[$table] ?? ['*'];
    }

    /**
     * Create guest context (no auth)
     */
    public static function guest(): self
    {
        return new self(null, null, [], []);
    }

    /**
     * Create admin context (full access)
     */
    public static function admin(string $userId): self
    {
        return new self($userId, 'admin', ['*'], ['*' => ['*']]);
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'user_role' => $this->userRole,
            'allowed_tables' => $this->allowedTables,
            'permissions' => $this->permissions,
        ];
    }
}
