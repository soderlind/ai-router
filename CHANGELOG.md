# Changelog

All notable changes to AI Router will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.3] - 2026-03-29

### Changed

- Config cards now use inline SVG icons for edit/delete (no dashicon font dependency)
- Capability chips rendered inside each config card
- Usage stats show "X of Y capabilities in use (Z%)" per card

## [0.4.2] - 2026-03-29

### Changed

- Config cards: bordered cards with capability usage stats and percentage
- Capability chips: green with checkmark when mapped, grey when unmapped
- Icon-only edit/delete buttons using dashicons
- Add button now primary variant with + prefix
- Modal form renders as overlay with panel visible behind

### Added

- CapabilityChip component for config card capability display

## [0.4.1] - 2026-03-29

### Changed

- Add/edit configuration form now opens in a WordPress Modal dialog

## [0.4.0] - 2026-03-27

### Changed

- Complete admin UI rewrite using WordPress 7 Connectors API and wp.components
- Replaced alert/confirm dialogs with inline Notice and ConfirmDialog components
- ConfigRow uses CSS Grid layout for consistent alignment
- Capability routing uses config's own capabilities instead of provider metadata

### Fixed

- Provider type normalization: `azure_openai` ↔ `azure-openai` mismatch at REST boundary
- Default configuration read: API returns `default_id`, UI now correctly reads it
- Secret fields use sentinel value to prevent accidental overwrites on edit
- Capability routing dropdowns now correctly list all configs that support each capability

### Added

- Vitest test suite for connectors.js (6 tests)
- `@wordpress/connectors` mock for test environment

## [0.3.0] - 2026-03-27

### Added

- Per-request deployment switching via `pre_option` filters for Azure OpenAI
- Capabilities `pre_option` filter for correct image model discovery
- Support for image generation routing (gpt-image-1, DALL-E)

## [0.2.0] - 2026-03-26

### Fixed

- Remove duplicate PHP connector registration — Settings → Connectors now shows only one AI Router entry

### Added

- GitHub issue templates for bug reports and feature requests

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

[0.3.0]: https://github.com/soderlind/ai-router/releases/tag/v0.3.0
[0.2.0]: https://github.com/soderlind/ai-router/releases/tag/v0.2.0
[0.1.0]: https://github.com/soderlind/ai-router/releases/tag/v0.1.0
[Unreleased]: https://github.com/soderlind/ai-router/compare/v0.3.0...HEAD
