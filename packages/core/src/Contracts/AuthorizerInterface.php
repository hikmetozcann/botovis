<?php

declare(strict_types=1);

namespace Botovis\Core\Contracts;

use Botovis\Core\DTO\AuthorizationResult;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Intent\ResolvedIntent;

/**
 * Contract for authorization logic
 */
interface AuthorizerInterface
{
    /**
     * Build security context for current request
     */
    public function buildContext(): SecurityContext;

    /**
     * Check if the intent is authorized for the current user
     */
    public function authorize(ResolvedIntent $intent, SecurityContext $context): AuthorizationResult;

    /**
     * Get filtered schema based on user permissions
     * Returns only tables/columns the user can access
     */
    public function filterSchema(array $schema, SecurityContext $context): array;
}
