<?php

declare(strict_types=1);

namespace Botovis\Core\Enums;

/**
 * The type of action Botovis can perform.
 */
enum ActionType: string
{
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
