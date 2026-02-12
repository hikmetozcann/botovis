<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

/**
 * Result of an executed database action.
 */
final class ActionResult
{
    /**
     * @param bool        $success  Whether the action succeeded
     * @param string      $message  Human-readable result message
     * @param array       $data     Returned data (e.g. created record)
     * @param int|null    $affected Number of affected rows
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?int $affected = null,
    ) {}

    public static function ok(string $message, array $data = [], ?int $affected = null): self
    {
        return new self(true, $message, $data, $affected);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'affected' => $this->affected,
        ];
    }
}
