<?php

declare(strict_types=1);

namespace Botovis\Laravel\Security;

use Botovis\Core\Contracts\AuthorizerInterface;
use Botovis\Core\DTO\AuthorizationResult;
use Botovis\Core\DTO\SecurityContext;
use Botovis\Core\Intent\ResolvedIntent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Laravel implementation of Botovis authorization
 */
class BotovisAuthorizer implements AuthorizerInterface
{
    private array $config;
    private ?Authenticatable $user = null;

    public function __construct()
    {
        $this->config = config('botovis.security', []);
    }

    /**
     * Build security context for current request
     */
    public function buildContext(): SecurityContext
    {
        $guard = $this->config['guard'] ?? 'web';
        $this->user = Auth::guard($guard)->user();

        if (!$this->user) {
            return SecurityContext::guest();
        }

        $role = $this->resolveUserRole($this->user);
        $permissions = $this->resolvePermissions($role);

        return new SecurityContext(
            userId: (string) $this->user->getAuthIdentifier(),
            userRole: $role,
            allowedTables: array_keys($permissions),
            permissions: $permissions,
            metadata: [
                'user_name' => $this->user->name ?? $this->user->email ?? 'User',
            ],
        );
    }

    /**
     * Check if the intent is authorized
     */
    public function authorize(ResolvedIntent $intent, SecurityContext $context): AuthorizationResult
    {
        // Check authentication requirement
        $requireAuth = $this->config['require_auth'] ?? true;
        if ($requireAuth && $context->isGuest()) {
            return AuthorizationResult::denyUnauthenticated();
        }

        // Non-action intents (question, clarification) are always allowed
        if (!$intent->isAction()) {
            return AuthorizationResult::allow();
        }

        // Custom callback takes priority
        $callback = $this->config['authorize_callback'] ?? null;
        if ($callback && is_callable($callback)) {
            $allowed = $callback($this->user, $intent->table, $intent->action);
            return $allowed
                ? AuthorizationResult::allow()
                : AuthorizationResult::denyAction($intent->table, $intent->action);
        }

        // Check Laravel Gates if enabled
        if ($this->config['use_gates'] ?? false) {
            $ability = "botovis.{$intent->table}.{$intent->action->value}";
            if (!Gate::allows($ability)) {
                return AuthorizationResult::denyAction($intent->table, $intent->action->value);
            }
            return AuthorizationResult::allow();
        }

        // Role-based permission check
        $actionStr = $intent->action->value;
        if (!$context->can($intent->table, $actionStr)) {
            // Check if table is not accessible at all
            $accessibleTables = $context->getAccessibleTables();
            if (!in_array($intent->table, $accessibleTables, true) && !in_array('*', $accessibleTables, true)) {
                return AuthorizationResult::denyTable($intent->table);
            }
            return AuthorizationResult::denyAction($intent->table, $actionStr);
        }

        return AuthorizationResult::allow();
    }

    /**
     * Filter schema to only include tables user can access
     */
    public function filterSchema(array $schema, SecurityContext $context): array
    {
        if ($context->isGuest() && ($this->config['require_auth'] ?? true)) {
            return [];
        }

        $accessibleTables = $context->getAccessibleTables();

        // Wildcard = all tables
        if (in_array('*', $accessibleTables, true)) {
            // Still annotate with allowed actions
            return array_map(function ($table) use ($context) {
                $table['allowed_actions'] = $context->getAllowedActions($table['name'] ?? $table['table'] ?? '');
                return $table;
            }, $schema);
        }

        // Filter to only accessible tables
        return array_values(array_filter(
            array_map(function ($table) use ($accessibleTables, $context) {
                $tableName = $table['name'] ?? $table['table'] ?? '';
                if (!in_array($tableName, $accessibleTables, true)) {
                    return null;
                }
                $table['allowed_actions'] = $context->getAllowedActions($tableName);
                return $table;
            }, $schema),
            fn($t) => $t !== null
        ));
    }

    /**
     * Resolve user's role
     */
    private function resolveUserRole(?Authenticatable $user): ?string
    {
        if (!$user) {
            return null;
        }

        $resolver = $this->config['role_resolver'] ?? 'attribute';

        return match ($resolver) {
            'attribute' => $this->resolveFromAttribute($user),
            'method' => $this->resolveFromMethod($user),
            'spatie' => $this->resolveFromSpatie($user),
            'callback' => $this->resolveFromCallback($user),
            default => 'user',
        };
    }

    private function resolveFromAttribute(Authenticatable $user): ?string
    {
        $attr = $this->config['role_attribute'] ?? 'role';
        $value = $user->{$attr} ?? null;

        if ($value === null) {
            return null;
        }

        // If it's a string, return directly
        if (is_string($value)) {
            return $value;
        }

        // If it's a model (relationship), try to get the name
        if (is_object($value)) {
            return $value->name ?? $value->slug ?? $value->title ?? (string) $value;
        }

        return null;
    }

    private function resolveFromMethod(Authenticatable $user): ?string
    {
        $method = $this->config['role_method'] ?? 'getRole';
        if (method_exists($user, $method)) {
            return $user->{$method}();
        }
        return null;
    }

    private function resolveFromSpatie(Authenticatable $user): ?string
    {
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            return $roles->first();
        }
        return null;
    }

    private function resolveFromCallback(Authenticatable $user): ?string
    {
        $callback = $this->config['role_callback'] ?? null;
        if ($callback && is_callable($callback)) {
            return $callback($user);
        }
        return null;
    }

    /**
     * Build permissions array for a role
     */
    private function resolvePermissions(?string $role): array
    {
        $rolesConfig = $this->config['roles'] ?? [];

        // Start with default permissions (*)
        $permissions = $rolesConfig['*'] ?? [];

        // Merge role-specific permissions
        if ($role && isset($rolesConfig[$role])) {
            $permissions = array_merge($permissions, $rolesConfig[$role]);
        }

        // Expand wildcard table permissions
        if (isset($permissions['*'])) {
            $wildcardActions = $permissions['*'];
            unset($permissions['*']);
            
            // Get all configured model tables
            $models = config('botovis.models', []);
            foreach ($models as $modelClass => $actions) {
                $tableName = $this->getTableFromModel($modelClass);
                if ($tableName && !isset($permissions[$tableName])) {
                    // Intersect wildcard actions with model's allowed actions
                    $permissions[$tableName] = array_values(array_intersect($wildcardActions, $actions));
                }
            }
            
            // If no models configured, keep wildcard for all
            if (empty($models)) {
                $permissions['*'] = $wildcardActions;
            }
        }

        return $permissions;
    }

    private function getTableFromModel(string $modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }
        
        try {
            $instance = new $modelClass();
            return $instance->getTable();
        } catch (\Throwable) {
            return null;
        }
    }
}
