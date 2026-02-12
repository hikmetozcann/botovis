# Botovis for Laravel

Botovis Smart Layer for Laravel — AI-powered natural language interface for your existing app.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require botovis/botovis-laravel
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=botovis-config
```

Then in `config/botovis.php`, whitelist your models:

```php
'models' => [
    App\Models\Product::class => ['create', 'read', 'update', 'delete'],
    App\Models\Category::class => ['read'],
],
```

## Schema Discovery

See what Botovis "sees" in your project:

```bash
# Pretty table output
php artisan botovis:discover

# JSON output
php artisan botovis:discover --json

# LLM prompt context
php artisan botovis:discover --prompt
```

## Testing

```bash
composer test
```

## License

MIT
