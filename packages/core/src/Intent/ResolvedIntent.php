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
    public function __construct(
        public readonly IntentType $type,
        public readonly ?ActionType $action = null,
        public readonly ?string $table = null,
        public readonly array $data = [],
        public readonly array $where = [],
        public readonly string $message = '',
        public readonly float $confidence = 0.0,
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
                $fields[] = "{$key}: {$value}";
            }
            $parts[] = "Veri: " . implode(', ', $fields);
        }

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as $key => $value) {
                $conditions[] = "{$key} = {$value}";
            }
            $parts[] = "Koşul: " . implode(', ', $conditions);
        }

        return implode("\n", $parts);
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'action' => $this->action?->value,
            'table' => $this->table,
            'data' => $this->data ?: null,
            'where' => $this->where ?: null,
            'message' => $this->message ?: null,
            'confidence' => $this->confidence,
        ], fn ($v) => $v !== null);
    }
}
