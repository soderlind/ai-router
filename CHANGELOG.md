# Changelog

All notable changes to AI Router will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-03-26

### Added

- Initial release of AI Router plugin
- Capability-based routing for WordPress 7.0 AI Client SDK
- Support for multiple AI provider configurations (OpenAI, Azure OpenAI, Anthropic, Ollama)
- REST API for managing configurations (`/ai-router/v1/configurations`)
- Capability mapping UI in WordPress Settings → Connectors
- Default configuration fallback system
- GitHub plugin updater for automatic updates from releases
- PHPUnit + Brain Monkey test suite (52 tests)

### Fixed

- Register AI Router as connector for proper credential detection
- Sync Azure OpenAI settings (endpoint, deployment_id, api_version) to connector options
- `before_generate` hook now correctly accepts single `BeforeGenerateResultEvent` argument

### Documentation

- Architecture documentation with routing logic explanation
- Credential sync explanation for AI plugin compatibility

## [Unreleased]

### Planned

- Priority/weight system for multiple configs supporting same capability
- Load balancing across providers
- Cost tracking per provider
- Health checks and automatic failover
- Per-provider rate limiting

[0.1.0]: https://github.com/soderlind/ai-router/releases/tag/v0.1.0
[Unreleased]: https://github.com/soderlind/ai-router/compare/v0.1.0...HEAD
