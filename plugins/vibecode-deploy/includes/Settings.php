<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION_NAME = 'vibecode_deploy_settings';

	public static function defaults(): array {
		return array(
			'project_slug' => '',
			'class_prefix' => '',
			'staging_dir'  => 'vibecode-deploy-staging',
			'on_missing_required' => 'warn',
			'on_missing_recommended' => 'warn',
			'on_unknown_placeholder' => 'warn',
		);
	}

	public static function get_all(): array {
		$raw = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array_merge( self::defaults(), $raw );
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$out = self::defaults();
		$mode_keys = array( 'on_missing_required', 'on_missing_recommended', 'on_unknown_placeholder' );

		if ( isset( $input['project_slug'] ) ) {
			$out['project_slug'] = sanitize_key( (string) $input['project_slug'] );
		}

		if ( isset( $input['class_prefix'] ) ) {
			$prefix = strtolower( trim( (string) $input['class_prefix'] ) );
			if ( $prefix === '' ) {
				$out['class_prefix'] = '';
			} elseif ( preg_match( '/^[a-z0-9-]+-$/', $prefix ) ) {
				$out['class_prefix'] = $prefix;
			} elseif ( preg_match( '/^[a-z0-9-]+$/', $prefix ) ) {
				$out['class_prefix'] = $prefix . '-';
			} else {
				$out['class_prefix'] = '';
				add_settings_error( self::OPTION_NAME, 'vibecode_deploy_class_prefix', 'Class Prefix must match ^[a-z0-9-]+-$ and include a trailing dash.' );
			}
		}

		if ( isset( $input['staging_dir'] ) ) {
			$dir = trim( (string) $input['staging_dir'] );
			$dir = preg_replace( '/[^a-zA-Z0-9._-]/', '', $dir );
			$out['staging_dir'] = $dir !== '' ? $dir : 'vibecode-deploy-staging';
		}

		foreach ( $mode_keys as $k ) {
			if ( ! isset( $input[ $k ] ) ) {
				continue;
			}
			$mode = strtolower( trim( (string) $input[ $k ] ) );
			$out[ $k ] = ( $mode === 'fail' ) ? 'fail' : 'warn';
		}

		return $out;
	}
}
