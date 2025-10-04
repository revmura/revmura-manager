<?php
/**
 * Panel registry: modules can register admin panels (tabs) in Manager.
 *
 * @package Revmura\Manager
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Manager\Admin;

/**
 * Registry for Manager admin panels (tabs).
 *
 * @since 1.0.0
 */
final class PanelRegistry {

	/**
	 * Registered panels keyed by id.
	 *
	 * @var array
	 */
	private static $panels = array();

	/**
	 * Register a panel (WPCS-compliant snake_case).
	 *
	 * Expected $panel keys: id (string), label (string), render_cb (callable).
	 *
	 * @param array $panel Panel definition.
	 * @return void
	 */
	public static function register_panel( array $panel ): void {
		$id        = isset( $panel['id'] ) ? sanitize_key( (string) $panel['id'] ) : '';
		$label     = isset( $panel['label'] ) ? (string) $panel['label'] : '';
		$render_cb = isset( $panel['render_cb'] ) ? $panel['render_cb'] : null;

		if ( '' === $id || '' === $label || ! is_callable( $render_cb ) ) {
			return;
		}

		self::$panels[ $id ] = array(
			'id'        => $id,
			'label'     => $label,
			'render_cb' => $render_cb,
		);
	}

	/**
	 * Back-compat wrapper for older calls using ::register().
	 *
	 * @param array $panel Panel definition.
	 * @return void
	 */
	public static function register( array $panel ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		self::register_panel( $panel );
	}

	/**
	 * Get all registered panels (WPCS-compliant name).
	 *
	 * @return array
	 */
	public static function get_panels(): array {
		return self::$panels;
	}

	/**
	 * Back-compat wrapper for older calls using ::all().
	 *
	 * @return array
	 */
	public static function all(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::get_panels();
	}
}

// phpcs:enable WordPress.Files.FileName
