# Contributing to Botovis

Thank you for your interest in contributing to Botovis!

## Repository Structure

This is a monorepo containing SDKs for multiple platforms:

```
packages/
├── laravel/    # PHP/Laravel
├── node/       # Node.js
└── dotnet/     # .NET
```

## Development Workflow

1. **Fork** the repository
2. **Create a branch** from `main`: `git checkout -b feature/my-feature`
3. **Work in the appropriate package** directory
4. **Write tests** — all SDKs must maintain >80% test coverage
5. **Follow the spec** — all changes must align with `SPECIFICATION.md`
6. **Submit a PR** with a clear description

## Language-Specific Guidelines

### Laravel (PHP)
- Follow PSR-12 coding standards
- Run `composer test` before submitting
- Minimum PHP 8.1

### Node.js
- Use TypeScript
- Follow ESLint configuration
- Run `npm test` before submitting

### .NET
- Follow .NET naming conventions
- Target .NET 8.0+
- Run `dotnet test` before submitting

## Commit Messages

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(laravel): add configuration support
fix(node): resolve connection timeout
docs: update specification
```

## Questions?

Open a GitHub Discussion or Issue.
