# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 0.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within Botovis, please **do not** open a public issue.

Instead, please send an email to **info@personateknoloji.com** (or contact the maintainer directly via GitHub).

We take security seriously — especially since Botovis interacts with databases. We will:

1. Acknowledge your report within **48 hours**
2. Investigate and provide an initial assessment within **5 business days**
3. Work on a fix and coordinate disclosure

## Security Architecture

Botovis includes multiple security layers by default:

- **Authentication** — Enforced via Laravel middleware
- **Role-Based Access Control** — Read/write permissions per role
- **Schema Filtering** — Sensitive tables hidden from the AI entirely
- **Write Confirmation** — All destructive operations require explicit user approval
- **Laravel Gates** — Fine-grained per-table access control

For more details, see the [Security Documentation](docs/en/security.md).

## Best Practices

- Always keep `BOTOVIS_AUTH_ENABLED=true` in production
- Never disable write confirmation (`BOTOVIS_WRITE_CONFIRM=true`)
- Exclude sensitive tables (`users`, `password_resets`, etc.)
- Use the principle of least privilege — default role should be `viewer`
