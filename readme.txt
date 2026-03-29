=== AI Router ===
Contributors: PerS
Tags: ai, openai, azure, routing
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route AI requests to different provider configurations based on capability.

== Description ==

AI Router allows you to configure multiple instances of the same AI provider (e.g., multiple Azure OpenAI deployments) and route AI requests to the appropriate configuration based on the requested capability.

**Why use AI Router?**

WordPress 7.0 introduces native AI capabilities, but only allows one configuration per provider type. With AI Router, you can:

* Configure multiple deployments of the same provider (e.g., GPT-4o for text, DALL-E for images)
* Route each capability (text generation, image generation, embeddings, etc.) to a specific configuration
* Set a default fallback configuration
* Easily manage all your AI configurations from one place

**Supported Providers**

* OpenAI
* Azure OpenAI

**Supported Capabilities**

* Text Generation (GPT models)
* Chat History (conversation context)
* Image Generation (DALL-E models)
* Embedding Generation
* Text-to-Speech
* Speech Generation
* Music Generation
* Video Generation

== Installation ==

1. Upload the `ai-router` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → AI Router to configure your providers
4. Build the admin assets: `cd wp-content/plugins/ai-router && npm install && npm run build`

== Frequently Asked Questions ==

= Do I need to have the underlying provider plugins installed? =

Yes, the underlying provider plugins (e.g., ai-provider-for-openai, ai-provider-for-azure-openai) must be installed and activated for AI Router to route requests to them.

= Can I have different models for different capabilities? =

Yes! That's the main purpose of AI Router. Create separate configurations for each model/deployment, assign the appropriate capabilities to each, and then map capabilities to configurations.

= What happens if a capability isn't mapped? =

If a capability isn't explicitly mapped, AI Router will use the default configuration (if set and it supports that capability). If no default is available, it will try to find any configuration that supports the capability.

== Changelog ==

= 0.4.1 =
* Changed: Add/edit configuration form now opens in a WordPress Modal dialog

= 0.4.0 =
* Changed: Complete admin UI rewrite using WordPress 7 Connectors API and wp.components
* Changed: Capability routing uses config's own capabilities instead of provider metadata
* Fixed: Provider type normalization (azure_openai ↔ azure-openai) at REST boundary
* Fixed: Default configuration read (default_id contract)
* Fixed: Secret fields use sentinel to prevent accidental overwrites on edit
* Fixed: Capability routing dropdowns now correctly list eligible configs
* Added: Vitest test suite for connectors.js (6 tests)

= 0.3.0 =
* Added: Per-request deployment switching via pre_option filters for Azure OpenAI
* Added: Capabilities pre_option filter for correct image model discovery
* Added: Support for image generation routing (gpt-image-1, DALL-E)

= 0.2.0 =
* Fix: Settings → Connectors now shows only one AI Router entry
* Added: GitHub issue templates for bug reports and feature requests

= 0.1.0 =
* Initial release
* Capability-based routing for WordPress 7.0 AI Client SDK
* Support for OpenAI, Azure OpenAI, Anthropic, Ollama providers
* Admin UI in Settings → Connectors
* Capability-to-configuration mapping
* Default configuration fallback
* GitHub plugin updater for automatic updates

== Upgrade Notice ==

= 0.4.2 =
Config cards redesigned with capability usage stats, green chips, and icon buttons.

= 0.4.1 =
Configuration form now opens in a modal dialog for better UX.

= 0.4.0 =
Complete admin UI rewrite with critical bug fixes for provider type normalization and capability routing.

= 0.3.0 =
Adds image generation routing support for Azure OpenAI deployments.

= 0.2.0 =
Fix duplicate connector entry in Settings → Connectors.
