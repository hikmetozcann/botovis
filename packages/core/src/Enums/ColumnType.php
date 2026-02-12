<?php

declare(strict_types=1);

namespace Botovis\Core\Enums;

/**
 * Supported column data types normalized across all databases.
 */
enum ColumnType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';
    case TIME = 'time';
    case JSON = 'json';
    case ENUM = 'enum';
    case BINARY = 'binary';
    case UUID = 'uuid';
    case UNKNOWN = 'unknown';
}
