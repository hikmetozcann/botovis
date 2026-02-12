<?php

declare(strict_types=1);

namespace Botovis\Laravel\Action;

use Botovis\Core\Contracts\ActionExecutorInterface;
use Botovis\Core\Contracts\ActionResult;
use Botovis\Core\Enums\ActionType;
use Botovis\Core\Schema\DatabaseSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Executes CRUD actions using Eloquent models.
 *
 * This is the "hands" of Botovis — it actually touches the database.
 * Every operation goes through the model (not raw SQL) so that
 * Eloquent events, observers, mutators, and casts all work.
 */
class EloquentActionExecutor implements ActionExecutorInterface
{
    public function __construct(
        private readonly DatabaseSchema $schema,
    ) {}

    public function execute(string $table, ActionType $action, array $data = [], array $where = [], array $select = []): ActionResult
    {
        $tableSchema = $this->schema->findTable($table);

        if ($tableSchema === null) {
            return ActionResult::fail("'{$table}' tablosu Botovis'e tanımlı değil.");
        }

        if (!$tableSchema->isActionAllowed($action)) {
            return ActionResult::fail("'{$table}' tablosunda '{$action->value}' işlemi izin verilmemiş.");
        }

        $modelClass = $tableSchema->modelClass;

        if ($modelClass === null || !class_exists($modelClass)) {
            return ActionResult::fail("'{$table}' için model sınıfı bulunamadı.");
        }

        try {
            return match ($action) {
                ActionType::CREATE => $this->executeCreate($modelClass, $data, $tableSchema->fillable),
                ActionType::READ => $this->executeRead($modelClass, $where, $select),
                ActionType::UPDATE => $this->executeUpdate($modelClass, $data, $where, $tableSchema->fillable),
                ActionType::DELETE => $this->executeDelete($modelClass, $where),
            };
        } catch (\Throwable $e) {
            return ActionResult::fail("İşlem hatası: {$e->getMessage()}");
        }
    }

    private function executeCreate(string $modelClass, array $data, array $fillable): ActionResult
    {
        if (empty($data)) {
            return ActionResult::fail('Oluşturulacak veri belirtilmedi.');
        }

        // Filter to only fillable fields
        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        /** @var Model $record */
        $record = new $modelClass();
        $record->fill($data);
        $record->save();

        return ActionResult::ok(
            "Kayıt oluşturuldu (ID: {$record->getKey()}).",
            $record->toArray(),
            1,
        );
    }

    private function executeRead(string $modelClass, array $where, array $select = []): ActionResult
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $modelClass::query();

        // Apply column selection
        if (!empty($select)) {
            // Always include primary key for consistency
            $columns = array_unique(array_merge(['id'], $select));
            $query->select($columns);
        }

        foreach ($where as $column => $value) {
            if (is_string($value) && str_contains($value, '%')) {
                $query->where($column, 'LIKE', $value);
            } else {
                $query->where($column, $value);
            }
        }

        $records = $query->limit(50)->get();

        if ($records->isEmpty()) {
            return ActionResult::ok('Sonuç bulunamadı.', [], 0);
        }

        // When using select, disable appends to prevent accessor errors
        // (accessors may depend on columns not in the select list)
        if (!empty($select)) {
            $records->each(fn ($record) => $record->setAppends([]));
        }

        return ActionResult::ok(
            "{$records->count()} kayıt bulundu.",
            $records->toArray(),
            $records->count(),
        );
    }

    private function executeUpdate(string $modelClass, array $data, array $where, array $fillable): ActionResult
    {
        if (empty($data)) {
            return ActionResult::fail('Güncellenecek veri belirtilmedi.');
        }

        if (empty($where)) {
            return ActionResult::fail('Güncelleme için koşul belirtilmedi. Tüm kayıtları güncellemek tehlikeli.');
        }

        // Filter to only fillable fields
        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $modelClass::query();

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return ActionResult::fail('Güncellenecek kayıt bulunamadı.');
        }

        $count = 0;
        foreach ($records as $record) {
            $record->fill($data);
            $record->save();
            $count++;
        }

        return ActionResult::ok(
            "{$count} kayıt güncellendi.",
            $records->fresh()->toArray(),
            $count,
        );
    }

    private function executeDelete(string $modelClass, array $where): ActionResult
    {
        if (empty($where)) {
            return ActionResult::fail('Silme için koşul belirtilmedi. Tüm kayıtları silmek tehlikeli.');
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $modelClass::query();

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return ActionResult::fail('Silinecek kayıt bulunamadı.');
        }

        $count = $records->count();

        // SoftDelete support: if model uses SoftDeletes, it will soft delete
        foreach ($records as $record) {
            $record->delete();
        }

        return ActionResult::ok(
            "{$count} kayıt silindi.",
            [],
            $count,
        );
    }
}
