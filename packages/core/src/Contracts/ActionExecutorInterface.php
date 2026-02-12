<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\Enums\ActionType;

/**
 * Executes CRUD actions against the database.
 *
 * Each framework adapter implements this using its own ORM:
 * - Laravel: Eloquent
 * - .NET: Entity Framework
 * - Node: Prisma/TypeORM
 */
interface ActionExecutorInterface
{
    /**
     * Execute a database action.
     *
     * @param string     $table  Table/model name
     * @param ActionType $action The action type
     * @param array      $data   The data payload (columns => values)
     * @param array      $where  Conditions for READ/UPDATE/DELETE
     * @param string[]   $select Columns to return for READ (empty = all)
     * @return ActionResult
     */
    public function execute(string $table, ActionType $action, array $data = [], array $where = [], array $select = []): ActionResult;
}
