<?php

declare(strict_types=1);

namespace Botovis\Core\Intent;

use Botovis\Core\Enums\ActionType;
use Botovis\Core\Enums\IntentType;

/**
 * A structured representation of what the user wants to do.
 *
 * The LLM parses the user's natural language and returns this structure.
 * 
 * Examples:
 *   "Yeni çalışan ekle, adı Ahmet"
 *     → type=ACTION, action=CREATE, table=employees, data={first_name: "Ahmet"}
 *
 *   "Kaç tane aktif çalışan var?"
 *     → type=ACTION, action=READ, table=employees, where={is_active: true}
 *
 *   "Ahmet'in maaşını 15000 yap"
 *     → type=ACTION, action=UPDATE, table=employees, where={first_name: "Ahmet"}, data={base_salary: 15000}
 *
 *   "Bu ne işe yarıyor?"
 *     → type=QUESTION, message="..."
 */
final class ResolvedIntent
{
    /**
     * @param IntentType      $type           Intent category
     * @param ActionType|null $action         CRUD action type
     * @param string|null     $table          Target table name
     * @param array           $data           Data payload (columns => values) for CREATE/UPDATE
     * @param array           $where          Filter conditions for READ/UPDATE/DELETE
     * @param string[]        $select         Columns to return for READ (empty = all)
     * @param string          $message        Human-readable message
     * @param float           $confidence     Confidence score 0.0-1.0
     * @param bool            $autoContinue   If true, system auto-continues to next step after this READ
     */
    public function __construct(
        public readonly IntentType $type,
        public readonly ?ActionType $action = null,
        public readonly ?string $table = null,
        public readonly array $data = [],
        public readonly array $where = [],
        public readonly array $select = [],
        public readonly string $message = '',
        public readonly float $confidence = 0.0,
        public readonly bool $autoContinue = false,
    ) {}

    /**
     * Is this an actionable intent (CRUD)?
     */
    public function isAction(): bool
    {
        return $this->type === IntentType::ACTION
            && $this->action !== null
            && $this->table !== null;
    }

    /**
     * Does this intent require user confirmation?
     * READ actions don't need confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return $this->isAction() && $this->action !== ActionType::READ;
    }

    /**
     * Build a human-readable summary of the intent for confirmation.
     */
    public function toConfirmationMessage(): string
    {
        if (!$this->isAction()) {
            return $this->message;
        }

        $actionLabel = match ($this->action) {
            ActionType::CREATE => 'Yeni kayıt oluştur',
            ActionType::READ => 'Kayıtları getir',
            ActionType::UPDATE => 'Kayıt güncelle',
            ActionType::DELETE => 'Kayıt sil',
        };

        $parts = ["{$actionLabel} → {$this->table}"];

        if (!empty($this->data)) {
            $fields = [];
            foreach ($this->data as $key => $value) {
                $fields[] = "{$key}: " . self::valueToString($value);
            }
            $parts[] = "Veri: " . implode(', ', $fields);
        }

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as $key => $value) {
                $conditions[] = "{$key} = " . self::valueToString($value);
            }
            $parts[] = "Koşul: " . implode(', ', $conditions);
        }

        if (!empty($this->select)) {
            $parts[] = "Sütunlar: " . implode(', ', $this->select);
        }

        return implode("\n", $parts);
    }

    public function toArray(): array
    {
        $arr = array_filter([
            'type' => $this->type->value,
            'action' => $this->action?->value,
            'table' => $this->table,
            'data' => $this->data ?: null,
            'where' => $this->where ?: null,
            'select' => $this->select ?: null,
            'message' => $this->message ?: null,
            'confidence' => $this->confidence,
        ], fn ($v) => $v !== null);

        if ($this->autoContinue) {
            $arr['auto_continue'] = true;
        }

        return $arr;
    }

    /**
     * Safely convert any value to a string for display.
     */
    private static function valueToString(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}
