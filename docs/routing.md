# Routing Logic

The `Router::get_configuration_for_capability()` method implements the routing algorithm.

## Priority Order

1. **Explicit Mapping** — If a capability is explicitly mapped to a configuration via the UI's "Capability Routing" section, use that configuration.

2. **Default Configuration** — If no explicit mapping exists, check if the default configuration supports the requested capability.

3. **Best Effort** — If no default is set or it doesn't support the capability, find all configurations that support the capability and return the one with the lowest priority number (1 = highest priority, 100 = lowest).

## Configuration Priority

Each configuration has a `priority` field (1-100):

- **1** = highest priority (selected first)
- **100** = lowest priority (selected last)
- **10** = default priority

When multiple configurations support a capability but none are explicitly mapped or set as default, the router sorts them by priority and selects the first available one.

## Example Scenario

**Configurations:**

| Name | Provider | Priority | Capabilities | Default |
|------|----------|----------|--------------|---------|
| GPT-4 Config | OpenAI | 10 | text_generation, chat_history | ✓ |
| Claude Config | Anthropic | 5 | text_generation | |
| DALL-E Config | OpenAI | 10 | image_generation | |

**Routing Results:**

| Capability | Selected | Reason |
|------------|----------|--------|
| `text_generation` | GPT-4 Config | Default supports it |
| `image_generation` | DALL-E Config | Only one supports it |
| `chat_history` | GPT-4 Config | Only one supports it |

**If no default were set:**

| Capability | Selected | Reason |
|------------|----------|--------|
| `text_generation` | Claude Config | Lower priority (5 < 10) |
| `image_generation` | DALL-E Config | Only one supports it |

## Capability Map

The `CapabilityMap` class manages explicit routing rules stored in `wp_options`:

```php
// Option: ai_router_capability_map
[
    'text_generation'  => 'config-uuid-1',
    'image_generation' => 'config-uuid-2',
]
```

Explicit mappings override both default and priority-based selection.

## Data Flow

```text
1. Request for capability "text_generation"
2. Check CapabilityMap for explicit mapping
   └─ Found? → Return mapped configuration
3. Check default configuration
   └─ Supports capability? → Return default
4. Query all configurations supporting the capability
5. Sort by priority (ascending)
6. Return first available configuration
7. No match? → Return null (use WordPress default)
```
