# Botovis SDK Specification

> Version: 0.1.0 (Draft)
> 
> This document defines the shared behavior that all Botovis SDKs MUST implement.
> Each language-specific SDK should follow this specification to ensure cross-platform consistency.

## 1. Overview

Botovis is a cross-platform package. All SDKs must provide a consistent API surface 
while respecting the idioms and conventions of their target language/framework.

## 2. Naming Conventions

| Language | Package Name | Namespace/Module |
|----------|-------------|-----------------|
| PHP/Laravel | `botovis/botovis` | `Botovis\` |
| Node.js | `botovis` | `@botovis/` or `botovis` |
| .NET | `Botovis` | `Botovis` |

## 3. Configuration

All SDKs MUST support configuration through:
1. **Constructor/initialization parameters**
2. **Environment variables** (prefixed with `BOTOVIS_`)
3. **Configuration files** (framework-specific: `.env`, `appsettings.json`, etc.)

### Required Environment Variables
```
BOTOVIS_API_KEY=        # API key (if applicable)
BOTOVIS_ENV=            # Environment: production, staging, development
BOTOVIS_DEBUG=          # Enable debug mode: true/false
```

## 4. Versioning

- All SDKs follow **Semantic Versioning** (semver).
- Major and minor versions are kept in sync across SDKs when possible.
- Patch versions may differ per SDK.

## 5. Error Handling

All SDKs MUST:
- Use language-idiomatic error handling (exceptions in PHP/.NET, Error objects in JS)
- Provide consistent error codes across platforms
- Include meaningful error messages

## 6. Logging

All SDKs MUST support configurable log levels:
- `debug`, `info`, `warning`, `error`

## 7. Testing

Each SDK MUST include:
- Unit tests with >80% coverage
- Integration tests where applicable
- Tests against shared test fixtures in `/tests/fixtures/`

---

*This specification will be expanded as the package functionality is defined.*
