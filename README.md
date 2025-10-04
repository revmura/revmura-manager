# Revmura Manager

Admin **UI shell** for Revmura. Hosts tabs (panels) contributed by modules. No business logic.

- **Panel registry** via `do_action( 'revmura_manager_register_panel', … )`
- Built-in **Import/Export** tab (calls Core REST)
- PHPCS‑clean, WordPress 6.5+ / PHP 8.3+

## Requirements
- WordPress **6.5+**
- PHP **8.3+**
- **Revmura Power Core** active first

## Install & Activate
1. Copy to `wp-content/plugins/revmura-manager`
2. Activate **after** Core
3. Open **Dashboard → Revmura**

## Add a tab from a module
```php
// In your module plugin (after plugins_loaded)
if ( has_action( 'revmura_manager_register_panel' ) ) {
    do_action( 'revmura_manager_register_panel', [
        'id'        => 'offers',
        'label'     => __( 'Offers', 'revmura' ),
        'render_cb' => static function (): void {
            echo '<div class="wrap"><h2>' . esc_html__( 'Offers Settings', 'revmura' ) . '</h2><p>' .
                 esc_html__( 'This is a test panel (UX only).', 'revmura' ) .
                 '</p></div>';
        },
    ]);
}
```

## Import / Export tab
- **Export to JSON**: current CPT/Tax schema
- **Dry‑run Import**: validates and prints a diff
- **Apply Import**: persists snapshot (LKG) and flushes rewrites

Manager sends required headers to Core REST:
- `X-WP-Nonce` = `wp_create_nonce('wp_rest')`
- `X-Revmura-Nonce` = `wp_create_nonce('revmura_import')` (for imports)

## Development
```bash
composer install
vendor/bin/phpcbf .
vendor/bin/phpcs -q --report=summary
```