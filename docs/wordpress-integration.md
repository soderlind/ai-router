# WordPress Integration

## Action Hooks

AI Router intercepts AI requests via WordPress hooks:

```php
// Pre-request hook — Router decides which configuration to use
add_action('wp_ai_pre_request', [$router, 'handle_pre_request'], 10, 2);

// Post-request hook — Logging, analytics, etc.
add_action('wp_ai_post_request', [$router, 'handle_post_request'], 10, 2);
```

## REST API Endpoints

All endpoints require `manage_options` capability.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ai-router/v1/configurations` | List all configurations |
| GET | `/wp-json/ai-router/v1/configurations/{id}` | Get single configuration |
| POST | `/wp-json/ai-router/v1/configurations` | Create configuration |
| PUT | `/wp-json/ai-router/v1/configurations/{id}` | Update configuration |
| DELETE | `/wp-json/ai-router/v1/configurations/{id}` | Delete configuration |
| GET | `/wp-json/ai-router/v1/configurations/default` | Get default config ID |
| PUT | `/wp-json/ai-router/v1/configurations/default` | Set default config |
| GET | `/wp-json/ai-router/v1/capability-map` | Get capability mappings |
| PUT | `/wp-json/ai-router/v1/capability-map` | Update capability mappings |

## Configuration Schema

```json
{
  "id": "uuid-string",
  "name": "My OpenAI Config",
  "provider_type": "openai",
  "settings": {
    "api_key": "sk-...",
    "model": "gpt-4",
    "endpoint": "https://api.openai.com/v1"
  },
  "capabilities": ["text_generation", "image_generation"],
  "is_default": true,
  "priority": 10
}
```

## Connector Sync

`ConnectorSync` synchronizes AI Router configurations with WordPress's native Connectors system:

```php
final class ConnectorSync {
    /**
     * Push AI Router config to WordPress connector.
     */
    public function sync_to_connector(Configuration $config): void;

    /**
     * Pull connector data into AI Router.
     */
    public function sync_from_connector(string $connector_id): Configuration;
}
```

This ensures credentials entered in the WordPress Connectors UI are available to AI Router and vice versa.

## Admin UI Integration

AI Router registers a settings page and integrates with the WordPress Connectors panel:

```php
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        __('AI Router', 'ai-router'),
        __('AI Router', 'ai-router'),
        'manage_options',
        'ai-router',
        [$this, 'render_admin_page']
    );
});
```

The React-based `connectors.js` component mounts in the Connectors panel, providing capability checkboxes and priority settings.

## Capabilities Required

| Action | WordPress Capability |
|--------|----------------------|
| View configurations | `manage_options` |
| Create/edit configurations | `manage_options` |
| Delete configurations | `manage_options` |
| Change default | `manage_options` |
| Update capability map | `manage_options` |
