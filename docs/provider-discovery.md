# Provider Discovery

`ProviderDiscovery` wraps WordPress's AI Client SDK to enumerate available providers and their capabilities.

## API

```php
final class ProviderDiscovery {
    /**
     * Get all registered AI providers.
     * @return array<string, WP_AI_Provider>
     */
    public function get_providers(): array;

    /**
     * Get capabilities for a specific provider.
     * @return string[] Capability slugs (text_generation, image_generation, etc.)
     */
    public function get_provider_capabilities(string $provider_id): array;
}
```

## Under the Hood

```php
// Internally calls WordPress AI Client SDK
$services = wp_ai_get_services();
$provider = $services->get_provider_registry()->get_registered_provider($provider_id);
$provider->get_capabilities(); // Returns CapabilityEnum[]
```

Each `CapabilityEnum` uses `__toString()` to return its slug — **not** `getValue()`.

## Supported Providers

| Provider | ID | Typical Capabilities |
|----------|----|-----------------------|
| OpenAI | `openai` | text_generation, image_generation, embedding_generation |
| Anthropic | `anthropic` | text_generation, chat_history |
| Azure OpenAI | `azure_openai` | text_generation, image_generation, embedding_generation |
| Ollama | `ollama` | text_generation, embedding_generation |

## Capability Detection

The router checks if a configuration supports a requested capability:

```php
$provider = $this->provider_discovery->get_provider($config->provider_type);
$provider_caps = array_map(fn($cap) => (string) $cap, $provider->get_capabilities());

if (in_array($capability, $provider_caps, true)) {
    return $config;
}
```

## Adding New Providers

When WordPress 7.0+ adds new AI providers (e.g., Google Gemini), `ProviderDiscovery` will automatically detect them. The only requirement is that the provider registers with `WP_AI_Provider_Registry`.
