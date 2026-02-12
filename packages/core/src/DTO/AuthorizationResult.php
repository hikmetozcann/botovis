<?php

declare(strict_types=1);

namespace Botovis\Core\DTO;

/**
 * Result of an authorization check
 */
final class AuthorizationResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $suggestion = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, ?string $suggestion = null): self
    {
        return new self(false, $reason, $suggestion);
    }

    public static function denyTable(string $table): self
    {
        return new self(
            false,
            "Access to table '{$table}' is not allowed",
            "You can only access tables you have permission for"
        );
    }

    public static function denyAction(string $table, string $action): self
    {
        return new self(
            false,
            "Action '{$action}' is not allowed on table '{$table}'",
            "You may only have read access to this table"
        );
    }

    public static function denyUnauthenticated(): self
    {
        return new self(
            false,
            'Authentication required',
            'Please log in to use this feature'
        );
    }
}
