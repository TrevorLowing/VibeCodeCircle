<?php
/**
 * Script tag modifier for Vite assets.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Assets;

/**
 * Class ScriptTagModifier
 *
 * Modifies script tags for Vite module loading.
 */
class ScriptTagModifier {

	/**
	 * Add type="module" to script tags.
	 * For now it would add module types for vite-client and svelte-app.
	 * IF we'll need to add more, we'll need to add them here.
	 *
	 * @param string $tag Script HTML tag.
	 * @param string $handle Script handle.
	 * @param string $src Script source URL.
	 * @return string Modified script tag.
	 */
	public static function add_module_type( string $tag, string $handle, string $src ): string {

		if ( 0 === strpos( $handle, 'vite-client-' ) || 0 === strpos( $handle, 'svelte-app-' ) ) {
			return sprintf(
				'<script type="module" src="%s"></script>', // phpcs:ignore
				esc_url( $src )
			);
		}
		return $tag;
	}
}
