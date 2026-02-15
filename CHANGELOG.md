# Changelog

All notable changes to Botovis will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
