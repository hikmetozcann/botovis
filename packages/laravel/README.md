# Botovis for Laravel

Official Botovis SDK for Laravel.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require botovis/botovis
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=botovis-config
```

Add to your `.env`:

```env
BOTOVIS_API_KEY=your-api-key
BOTOVIS_ENV=production
BOTOVIS_DEBUG=false
```

## Usage

### Via Facade

```php
use Botovis\Facades\Botovis;

$version = Botovis::version();
```

### Via Dependency Injection

```php
use Botovis\Botovis;

class MyController extends Controller
{
    public function index(Botovis $botovis)
    {
        $version = $botovis->version();
    }
}
```

## Testing

```bash
composer test
```

## License

MIT
