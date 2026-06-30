# Data Model

## Configuration DTO

```php
final readonly class Configuration implements JsonSerializable {
    public function __construct(
        public string $id,           // UUID
        public string $name,         // Display name
        public string $provider_type,// openai, anthropic, azure_openai, etc.
        public array $settings,      // api_key, endpoint, model, etc.
        public array $capabilities,  // text_generation, image_generation, etc.
        public bool $is_default,     // Default fallback flag
        public int $priority = 10,   // Routing priority (1-100, lower = higher)
    ) {}
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string | UUID, auto-generated if not provided |
| `name` | string | Human-readable display name |
| `provider_type` | string | Provider slug (openai, azure_openai, etc.) |
| `settings` | array | Provider-specific settings (api_key, endpoint, model) |
| `capabilities` | array | List of capability slugs this config supports |
| `is_default` | bool | Whether this is the default fallback config |
| `priority` | int | Routing priority (1 = highest, 100 = lowest) |

## RequestContext DTO

`RequestContext` is an **immutable value object** capturing per-request routing state:

```php
final readonly class RequestContext {
    public function __construct(
        public string $capability,
        public Configuration $configuration,
        public ?string $deployment_id = null,
        public ?string $api_version = null,
        public array $capabilities = [],
    ) {}

    public static function from_configuration(string $capability, Configuration $config): self;
    public function has_azure_overrides(): bool;
    public function get_provider_type(): string;
    public function get_setting(string $key, mixed $default = null): mixed;
}
```

The Router stores a single `?RequestContext` instead of multiple mutable fields, making per-request state explicit and testable.

## Vocabulary Module

`Vocabulary.php` is the **single source of truth** for capabilities and provider types:

```php
final class Vocabulary {
    public const CAPABILITIES = [
        'text_generation',
        'chat_history',
        'image_generation',
        'embedding_generation',
        'text_to_speech_conversion',
        'speech_generation',
        'music_generation',
        'video_generation',
    ];

    public const PROVIDER_TYPES = [
        'openai',
        'anthropic',
        'azure_openai',
        'ollama',
    ];

    public static function capabilities(): array;
    public static function get_capability_label(string $capability): string;
    public static function is_valid_capability(string $capability): bool;
    public static function normalize_provider_type(string $type): string;
    public static function get_provider_id(string $type): string;
}
```

All modules reference `Vocabulary` constants — no magic strings scattered through the codebase.

## ConfigurationService

Domain logic extracted from the REST controller:

```php
final class ConfigurationService {
    public function list(): array;
    public function get(string $id): Configuration;
    public function create(array $data): Configuration;
    public function update(string $id, array $data): Configuration;
    public function delete(string $id): void;
    public function get_default_id(): ?string;
    public function set_default(string $id): void;
    public function get_capability_map(): array;
    public function update_capability_map(array $map): void;
}
```

Throws `ConfigurationNotFoundException` and `ConfigurationValidationException` — the REST controller converts these to `WP_Error` responses.

## Storage

All data is stored in `wp_options`:

| Option | Content |
|--------|---------|
| `ai_router_configurations` | Array of configuration arrays |
| `ai_router_default_config` | Default configuration ID |
| `ai_router_capability_map` | Capability → Config ID mapping |
