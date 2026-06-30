# Extensibility

## Filter Hooks

AI Router provides filters for customizing routing behavior:

```php
/**
 * Override routing decision.
 *
 * @param ?Configuration $config  Resolved configuration (null if none found)
 * @param string         $capability Requested capability
 * @param array          $context Additional request context
 * @return ?Configuration
 */
add_filter('ai_router_configuration', function($config, $capability, $context) {
    // Force a specific config for embeddings
    if ($capability === 'embedding_generation') {
        return $repository->get('custom-embedding-config-id');
    }
    return $config;
}, 10, 3);

/**
 * Modify provider capabilities.
 *
 * @param string[] $capabilities Capability slugs
 * @param string   $provider_id  Provider identifier
 * @return string[]
 */
add_filter('ai_router_provider_capabilities', function($capabilities, $provider_id) {
    // Add custom capability
    if ($provider_id === 'openai') {
        $capabilities[] = 'custom_capability';
    }
    return $capabilities;
}, 10, 2);

/**
 * Modify configuration before save.
 *
 * @param array $data Configuration data
 * @return array
 */
add_filter('ai_router_before_save_config', function($data) {
    // Normalize model names
    if (isset($data['settings']['model'])) {
        $data['settings']['model'] = strtolower($data['settings']['model']);
    }
    return $data;
}, 10, 1);
```

## Action Hooks

```php
/**
 * Fires after a configuration is created.
 *
 * @param Configuration $config The new configuration
 */
do_action('ai_router_config_created', $config);

/**
 * Fires after a configuration is updated.
 *
 * @param Configuration $config The updated configuration
 */
do_action('ai_router_config_updated', $config);

/**
 * Fires after a configuration is deleted.
 *
 * @param string $id The deleted configuration ID
 */
do_action('ai_router_config_deleted', $id);

/**
 * Fires when a routing decision is made.
 *
 * @param RequestContext $context The routing context
 */
do_action('ai_router_request_routed', $context);
```

## Security Considerations

### API Key Storage

API keys are stored in `wp_options`. For enhanced security:

1. **Use environment variables** in production:

   ```php
   add_filter('ai_router_before_save_config', function($data) {
       // Store reference instead of actual key
       if (isset($data['settings']['api_key'])) {
           $data['settings']['api_key'] = 'env:OPENAI_API_KEY';
       }
       return $data;
   });
   ```

2. **Encrypt sensitive fields** using a plugin like WP Encryption.

### Capability Checks

All REST endpoints verify `manage_options` capability:

```php
'permission_callback' => function() {
    return current_user_can('manage_options');
}
```

### Input Sanitization

All inputs are sanitized:

- `sanitize_text_field()` for strings
- `absint()` for integers
- `wp_kses_post()` for HTML
- Array validation for known fields

### Nonce Verification

The admin UI includes nonce verification for all form submissions.

## Future Considerations

### Cost Tracking

```php
// Planned hook for cost attribution
do_action('ai_router_request_completed', [
    'config_id' => $config->id,
    'tokens_used' => $response->usage->total_tokens,
    'cost_estimate' => $calculator->estimate($response),
]);
```

### Rate Limiting

```php
// Planned filter for rate limit checks
add_filter('ai_router_can_route', function($can_route, $capability, $config) {
    return $rate_limiter->check($config->id);
}, 10, 3);
```

### Provider Health Checks

```php
// Planned method for health monitoring
$router->get_provider_health('openai'); // Returns 'healthy' | 'degraded' | 'down'
```

### Multi-Site Support

Currently single-site only. Multi-site support planned for v2.0.
