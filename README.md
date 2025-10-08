# Revmura Manager

Admin **UI shell** for Revmura. Hosts tabs (panels) contributed by modules. No business logic.

- **Panel registry** via `do_action( 'revmura_manager_register_panel', … )`
- Built‑in **Import/Export** tab (calls Core REST)
- Runs with **WordPress 6.5+ / PHP 8.3+**
- Depends on **Revmura Power Core**

---

## Requirements
- WordPress **6.5+**
- PHP **8.3+**
- **Revmura Power Core** active first

---

## Install & Activate
1. Copy to `wp-content/plugins/revmura-manager`
2. Activate **after** Core
3. Open **Dashboard → Revmura**

---

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

---

## Import / Export tab
- **Export to JSON**: current CPT/Tax schema (via Core)
- **Dry‑run Import**: validates and prints a diff
- **Apply Import**: persists snapshot (LKG) and flushes rewrites

Manager sends required headers to Core REST:
- `X-WP-Nonce` = `wp_create_nonce('wp_rest')`
- `X-Revmura-Nonce` = `wp_create_nonce('revmura_import')` (for imports)

---

## Development

```bash
# install dev tools (PHPCS/WPCS/etc.)
composer install

# auto-fix what can be fixed, then check (cross-platform)
composer run lint:fix
composer run lint
```

PHPCS uses the project ruleset (`phpcs.xml.dist`) and also runs in CI via `.github/workflows/phpcs.yml`.

**Raw commands (only if you’re not using composer scripts):**

**Windows (PowerShell):**
```powershell
.\vendor\bin\phpcbf.bat -p -s --standard=phpcs.xml.dist .
.\vendor\bin\phpcs.bat  -q -p -s --standard=phpcs.xml.dist .
```

**macOS/Linux:**
```bash
vendor/bin/phpcbf -p -s --standard=phpcs.xml.dist .
vendor/bin/phpcs  -q -p -s --standard=phpcs.xml.dist .
```
