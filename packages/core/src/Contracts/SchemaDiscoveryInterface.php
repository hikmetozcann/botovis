<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\Schema\DatabaseSchema;

/**
 * Discovers the database schema from the application.
 *
 * Each framework adapter implements this differently:
 * - Laravel: reads Eloquent models + DB introspection
 * - .NET: reads Entity Framework DbContext
 * - Node: reads Prisma/TypeORM schemas
 */
interface SchemaDiscoveryInterface
{
    /**
     * Discover and return the full database schema.
     */
    public function discover(): DatabaseSchema;
}
