<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\Enums\ActionType;

/**
 * Checks whether a user is authorized to perform an action.
 *
 * Each framework implements this using its own auth system:
 * - Laravel: Gates & Policies
 * - .NET: IAuthorizationService
 * - Node: middleware-based auth
 */
interface AuthorizationInterface
{
    /**
     * Check if the current user can perform the given action on the given table.
     *
     * @param string     $table  Table/model name
     * @param ActionType $action The intended action
     * @param array      $data   Context data (e.g. record being updated)
     * @return bool
     */
    public function can(string $table, ActionType $action, array $data = []): bool;
}
