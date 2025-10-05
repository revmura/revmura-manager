<?php
/**
 * Plugin Name: Revmura Manager
 * Description: UI shell for Revmura modules. Hosts admin panels; no business logic.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Requires Plugins: revmura-power-core
 * Author: Saleh Bamatraf
 * License: GPL-2.0-or-later
 * Text Domain: revmura
 *
 * @package Revmura\Manager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BOOT GUARD: prevent double loading (which would register hooks twice).
 */
if ( defined( 'REVMURA_MANAGER_BOOTED' ) ) {
	return;
}
define( 'REVMURA_MANAGER_BOOTED', true );

// Composer autoload (needed to load src/ classes).
$autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

// Load translations no earlier than `init` (WP 6.7+ requirement).
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'revmura', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

/**
 * Defer Core API check until all plugins are loaded; never early-return.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'REVMURA_CORE_API' ) || version_compare( REVMURA_CORE_API, '1.0.0', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Revmura Manager: Core API too old or missing.', 'revmura' ) . '</p></div>';
				}
			);
		}
	},
	20
);

/**
 * Allow modules to register panels:
 * do_action( 'revmura_manager_register_panel', [
 *   'id' => 'offers', 'label' => __('Offers','revmura'), 'render_cb' => 'your_callback'
 * ] );
 */
add_action(
	'revmura_manager_register_panel',
	array( \Revmura\Manager\Admin\PanelRegistry::class, 'register' )
);

/**
 * Built-in Import/Export panel (talks to Core REST).
 */
add_action(
	'init',
	static function (): void {
		if ( has_action( 'revmura_manager_register_panel' ) ) {
			do_action(
				'revmura_manager_register_panel',
				array(
					'id'        => 'import-export',
					'label'     => __( 'Import/Export', 'revmura' ),
					'render_cb' => 'revmura_manager_render_import_export_panel',
				)
			);
		}
	},
	20
);

/**
 * Admin page with tabs contributed by modules.
 */
add_action(
	'admin_menu',
	static function (): void {
		/**
		 * MENU GUARD: ensure we don’t register the page twice even if hooks ran twice.
		 */
		if ( defined( 'REVMURA_MANAGER_MENU_DONE' ) ) {
			return;
		}
		define( 'REVMURA_MANAGER_MENU_DONE', true );

		add_menu_page(
			'Revmura',
			'Revmura',
			'manage_options',
			'revmura',
			static function (): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Access denied', 'revmura' ) );
				}

				$panels = \Revmura\Manager\Admin\PanelRegistry::all();

				echo '<div class="wrap"><h1>Revmura</h1>';

				// Tabs.
				echo '<h2 class="nav-tab-wrapper">';
				// Read-only selector; sanitized + no state change.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param
				$tab_raw = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tab'] ) ) : '';

				// Compute $active without short ternary.
				if ( is_string( $tab_raw ) && '' !== $tab_raw ) {
					$active = sanitize_key( $tab_raw );
				} else {
					$first  = array_key_first( $panels );
					$active = is_string( $first ) ? $first : '';
				}

				foreach ( $panels as $id => $p ) {
					$class   = ( $id === $active ) ? ' nav-tab nav-tab-active' : ' nav-tab';
					$id_safe = sanitize_key( (string) $id );
					$url     = add_query_arg(
						array(
							'page' => 'revmura',
							'tab'  => $id_safe,
						),
						admin_url( 'admin.php' )
					);
					echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $p['label'] ) . '</a>';
				}
				echo '</h2>';

				// Render the active panel.
				if ( $active && isset( $panels[ $active ] ) && is_callable( $panels[ $active ]['render_cb'] ) ) {
					call_user_func( $panels[ $active ]['render_cb'] );
				} else {
					echo '<p>' . esc_html__( 'No panels registered yet.', 'revmura' ) . '</p>';
				}

				echo '</div>';
			},
			'dashicons-admin-generic',
			58
		);
	}
);

/**
 * Import/Export UI (Manager → Core REST).
 */
function revmura_manager_render_import_export_panel(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Access denied', 'revmura' ) );
	}

	$rest_nonce   = wp_create_nonce( 'wp_rest' );          // REQUIRED by WP REST cookie auth.
	$action_nonce = wp_create_nonce( 'revmura_import' );   // Custom action nonce for import endpoints.
	$export_url   = rest_url( 'revmura/v1/export' );
	$dry_url      = rest_url( 'revmura/v1/import/dry-run' );
	$apply_url    = rest_url( 'revmura/v1/import/apply' );

	echo '<div class="wrap"><h2>' . esc_html__( 'Import / Export', 'revmura' ) . '</h2>';
	echo '<p>' . esc_html__( 'Export current CPT/Tax schema or dry-run/apply an import JSON.', 'revmura' ) . '</p>';

	echo '<p><button class="button" id="revmura-ie-export">' . esc_html__( 'Export to JSON', 'revmura' ) . '</button> ';
	echo '<button class="button button-secondary" id="revmura-ie-dry">' . esc_html__( 'Dry-run Import', 'revmura' ) . '</button> ';
	echo '<button class="button button-primary" id="revmura-ie-apply">' . esc_html__( 'Apply Import', 'revmura' ) . '</button></p>';

	echo '<textarea id="revmura-ie-json" rows="16" style="width:100%;"></textarea>';

	?>
	<script>
	(() => {
		const restNonce   = '<?php echo esc_js( $rest_nonce ); ?>';
		const actionNonce = '<?php echo esc_js( $action_nonce ); ?>';
		const exportUrl   = '<?php echo esc_url_raw( $export_url ); ?>';
		const dryUrl      = '<?php echo esc_url_raw( $dry_url ); ?>';
		const applyUrl    = '<?php echo esc_url_raw( $apply_url ); ?>';
		const ta = document.getElementById('revmura-ie-json');

		async function request(url, opts={}) {
			const baseHeaders = { 'X-WP-Nonce': restNonce };
			const res = await fetch(url, {
				credentials: 'same-origin',
				headers: Object.assign(baseHeaders, opts.headers || {}),
				method: opts.method || 'GET',
				body: opts.body || undefined
			});
			const text = await res.text();
			try { return { ok: res.ok, data: JSON.parse(text) }; } catch { return { ok: res.ok, data: text }; }
		}

		document.getElementById('revmura-ie-export').addEventListener('click', async () => {
			const { ok, data } = await request(exportUrl);
			ta.value = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
		});

		document.getElementById('revmura-ie-dry').addEventListener('click', async () => {
			const { ok, data } = await request(dryUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-Revmura-Nonce': actionNonce },
				body: ta.value
			});
			alert(JSON.stringify(data, null, 2));
		});

		document.getElementById('revmura-ie-apply').addEventListener('click', async () => {
			if (!confirm('<?php echo esc_js( __( 'Apply import? This will modify CPT/Tax and flush rewrites.', 'revmura' ) ); ?>')) return;
			const { ok, data } = await request(applyUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-Revmura-Nonce': actionNonce },
				body: ta.value
			});
			alert(JSON.stringify(data, null, 2));
		});
	})();
	</script>
	<?php
	echo '</div>';
}
