# Changelog

All notable changes to Botovis will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.2] - 2026-02-17

### Added
- `botovis:models` command — scan Eloquent models, interactively select and assign permissions, generate config snippet or write directly to `config/botovis.php`
- Support for `--all`, `--read-only`, `--write`, and `--path` flags in `botovis:models`

## [0.1.1] - 2026-02-16

### Fixed
- Widget dist now bundled inside Laravel package for proper `vendor:publish` support

## [0.1.0] - 2026-02-16

### Added
- Agent mode with ReAct pattern and native tool calling
- Parallel tool calls support (multiple tools in a single LLM response)
- Generate stopping mechanism (urgency warnings + tool removal on last steps)
- 8 built-in database tools (search, count, aggregate, group, list, create, update, delete)
- Custom tool support via `ToolInterface`
- Three LLM drivers: Anthropic (Claude), OpenAI (GPT), Ollama (local)
- SSE streaming for real-time token output
- Write confirmation flow (approve/reject destructive operations)
- Multi-layer security: authentication, RBAC, Gates, schema filtering
- 4 role resolution methods: property, method, config_map, gate
- Zero-dependency chat widget (Web Component with Shadow DOM)
- Widget theming with CSS variables, light/dark/auto modes
- Conversation history with database or session storage
- React and Vue 3 wrapper components
- Bilingual support (Turkish + English) for widget and backend
- `botovis:discover` command for schema inspection
- `botovis:chat` command for CLI interaction
- Comprehensive bilingual documentation (EN + TR)
