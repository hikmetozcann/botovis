# Contributing to Botovis

Thank you for considering contributing to Botovis! This document provides guidelines to make the process smooth for everyone.

## Development Setup

1. **Fork & Clone**
   ```bash
   git clone https://github.com/your-username/botovis.git
   cd botovis
   ```

2. **Install PHP dependencies**
   ```bash
   cd packages/laravel
   composer install
   ```

3. **Install Widget dependencies**
   ```bash
   cd packages/widget
   npm install
   ```

## Project Structure

```
packages/
├── core/      # Framework-agnostic PHP (interfaces, DTOs, agent logic)
├── laravel/   # Laravel integration (drivers, tools, controllers)
└── widget/    # TypeScript Web Component (zero dependencies)
```

## Making Changes

### PHP (Core / Laravel)

- Follow PSR-12 coding style
- Add tests for new features
- Run tests before submitting: `cd packages/laravel && composer test`
- Run static analysis: `cd packages/laravel && composer analyse`

### Widget (TypeScript)

- Zero external dependencies — keep it that way
- Build: `cd packages/widget && npx vite build`
- Output formats: ES module, UMD, IIFE

## Pull Request Process

1. Create a feature branch from `main`: `git checkout -b feature/my-feature`
2. Make your changes with clear, focused commits
3. Ensure all tests pass
4. Update documentation if your change affects the public API
5. Submit a PR with a clear description of what and why

### Commit Messages

Use clear, descriptive commit messages:

```
feat: Add Redis conversation repository
fix: Handle null values in aggregate tool
docs: Update widget configuration guide
refactor: Extract message merging logic in Anthropic driver
```

## Reporting Bugs

Use [GitHub Issues](https://github.com/hikmetozcann/botovis/issues) with the bug report template. Include:

- PHP and Laravel version
- LLM driver and model
- Steps to reproduce
- Expected vs actual behavior

## Feature Requests

Open an issue with the feature request template. Describe:

- The problem you're trying to solve
- Your proposed solution
- Any alternatives you've considered

## Code of Conduct

Be respectful, constructive, and inclusive. We're all here to build something useful together.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
