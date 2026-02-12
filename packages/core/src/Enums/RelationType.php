<?php

declare(strict_types=1);

namespace Botovis\Core\Enums;

/**
 * Types of relationships between models/tables.
 */
enum RelationType: string
{
    case HAS_ONE = 'has_one';
    case HAS_MANY = 'has_many';
    case BELONGS_TO = 'belongs_to';
    case BELONGS_TO_MANY = 'belongs_to_many';
    case MORPH_ONE = 'morph_one';
    case MORPH_MANY = 'morph_many';
    case MORPH_TO = 'morph_to';
}
