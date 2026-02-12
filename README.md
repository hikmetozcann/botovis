# Botovis

> Multi-language SDK — one spec, every framework.

Botovis is a cross-platform package available for multiple languages and frameworks. Each SDK follows a shared specification to ensure consistent behavior across all platforms.

## Supported Platforms

| Platform | Package | Registry | Status |
|----------|---------|----------|--------|
| **Laravel** (PHP) | `botovis/botovis` | Packagist | 🚧 In Development |
| **Node.js** (Express/NestJS) | `botovis` | npm | 📋 Planned |
| **.NET** (ASP.NET Core) | `Botovis` | NuGet | 📋 Planned |

## Repository Structure

This is a **monorepo** containing all Botovis SDKs:

```
botovis/
├── packages/
│   ├── laravel/       # PHP/Laravel package
│   ├── node/          # Node.js package
│   └── dotnet/        # .NET package
├── docs/              # Shared documentation
├── examples/          # Example apps per language
└── SPECIFICATION.md   # Shared SDK specification
```

## Development

Each package is independently versioned and published to its respective registry.
All packages follow the shared [SPECIFICATION.md](SPECIFICATION.md).

### Quick Start

```bash
# Laravel
composer require botovis/botovis

# Node.js
npm install botovis

# .NET
dotnet add package Botovis
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines.

## License

MIT License — see [LICENSE](LICENSE) for details.
