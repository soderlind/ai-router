# AI Router

[![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Capability-based AI provider routing for WordPress 7.0**

Route AI requests to different provider configurations based on capability. Configure multiple instances of the same AI provider (e.g., GPT-4o for text, DALL-E for images) and let AI Router automatically select the right one.

## Why AI Router?

WordPress 7.0's AI Client SDK only allows one configuration per provider type. AI Router solves this by enabling:

- **Multiple configurations** of the same provider (e.g., separate Azure OpenAI deployments)
- **Capability-based routing** — text generation to GPT-4, images to DALL-E
- **Default fallback** — set a default for unmatched capabilities
- **Automatic selection** — no code changes needed in plugins using the AI SDK

## Supported Providers

- OpenAI
- Azure OpenAI
- Anthropic
- Ollama

## Supported Capabilities

| Capability | Description |
|------------|-------------|
| `text_generation` | GPT models, Claude, etc. |
| `chat_history` | Conversation context |
| `image_generation` | DALL-E, Stable Diffusion |
| `embedding_generation` | Vector embeddings |
| `text_to_speech` | Audio synthesis |
| `speech_generation` | Voice generation |
| `music_generation` | Music synthesis |
| `video_generation` | Video synthesis |

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Underlying AI provider plugin(s) installed (e.g., `ai-provider-for-openai`)

## Installation

### From GitHub Release

1. Download `ai-router.zip` from [Releases](https://github.com/soderlind/ai-router/releases)
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate the plugin

### From Source

```bash
cd wp-content/plugins
git clone https://github.com/soderlind/ai-router.git
cd ai-router
composer install --no-dev
npm install && npm run build
```

### Automatic Updates

The plugin includes a GitHub updater — updates are delivered automatically when new releases are published.

## Configuration

1. Go to **Settings → Connectors** in WordPress admin
2. In the AI Router section, click **Add Configuration**
3. Select a provider and configure its settings (API key, model, endpoint)
4. Assign capabilities to the configuration
5. Optionally set one configuration as default

### Capability Mapping

Map specific capabilities to specific configurations:

```
text_generation  → GPT-4 Config
image_generation → DALL-E Config
chat_history     → GPT-4 Config
```

Unmapped capabilities fall back to the default configuration.

## How It Works

```
Plugin/Theme → AI Request (text_generation)
                    ↓
              AI Router
                    ↓
         ┌─────────────────────┐
         │ 1. Explicit mapping │ → Use mapped config
         │ 2. Default supports │ → Use default config
         │ 3. Any supporting   │ → Use first match
         │ 4. None found       │ → Use WP default
         └─────────────────────┘
                    ↓
           AI Provider (OpenAI, Azure, etc.)
```

See [docs/architecture.md](docs/architecture.md) for detailed routing logic.

## Development

### Build Assets

```bash
npm install
npm run build        # Production build
npm run start        # Watch mode
```

### Run Tests

```bash
# PHP tests (PHPUnit + Brain Monkey)
composer install
composer test

# JavaScript tests (Vitest)
npm run test:js
npm run test:js:watch
```

### Project Structure

```
ai-router/
├── src/
│   ├── Admin/              # Connectors page integration
│   ├── DTO/                # Configuration data object
│   ├── Repository/         # Configuration CRUD
│   ├── Rest/               # REST API controller
│   ├── js/                 # React admin UI
│   ├── CapabilityMap.php   # Capability → Config mapping
│   ├── Router.php          # Core routing logic
│   └── ProviderDiscovery.php
├── tests/
│   ├── php/                # PHPUnit tests
│   └── js/                 # Vitest tests
└── docs/
    └── architecture.md     # Architecture documentation
```

## REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai-router/v1/configurations` | GET | List all configurations |
| `/ai-router/v1/configurations` | POST | Create configuration |
| `/ai-router/v1/configurations/{id}` | GET | Get single configuration |
| `/ai-router/v1/configurations/{id}` | PUT | Update configuration |
| `/ai-router/v1/configurations/{id}` | DELETE | Delete configuration |
| `/ai-router/v1/capability-map` | GET | Get capability mappings |
| `/ai-router/v1/capability-map` | POST | Update capability mappings |

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.

## Author

[Per Søderlind](https://soderlind.no)
