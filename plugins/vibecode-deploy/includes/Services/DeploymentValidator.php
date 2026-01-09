<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Deployment Validator
 *
 * Validates deployment after import to ensure CPTs, assets, and pages are working correctly.
 *
 * @package VibeCode\Deploy\Services
 */
final class DeploymentValidator {
	/**
	 * Validate CPT registration.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Validation result with 'ok', 'message', 'fix' keys.
	 */
	public static function validate_cpt( string $post_type ): array {
		$post_type_obj = get_post_type_object( $post_type );
		if ( $post_type_obj === null ) {
			return array(
				'ok' => false,
				'message' => sprintf( 'CPT "%s" is not registered.', $post_type ),
				'fix' => 'Check theme functions.php for register_post_type() call.',
			);
		}

		return array(
			'ok' => true,
			'message' => sprintf( 'CPT "%s" is registered correctly.', $post_type ),
		);
	}

	/**
	 * Validate asset file exists and is accessible.
	 *
	 * @param string $asset_path Relative asset path (e.g., 'css/styles.css').
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Build fingerprint.
	 * @return array Validation result with 'ok', 'message', 'fix' keys.
	 */
	public static function validate_asset( string $asset_path, string $project_slug, string $fingerprint ): array {
		$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
		$full_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $asset_path );

		if ( ! is_file( $full_path ) ) {
			return array(
				'ok' => false,
				'message' => sprintf( 'Asset file not found: %s', $asset_path ),
				'fix' => 'Ensure the asset file exists in the deployment package.',
			);
		}

		if ( ! is_readable( $full_path ) ) {
			return array(
				'ok' => false,
				'message' => sprintf( 'Asset file not readable: %s', $asset_path ),
				'fix' => 'Check file permissions on the asset file.',
			);
		}

		return array(
			'ok' => true,
			'message' => sprintf( 'Asset file accessible: %s', $asset_path ),
		);
	}

	/**
	 * Validate page exists and has content.
	 *
	 * @param string $page_slug Page slug.
	 * @return array Validation result with 'ok', 'message', 'fix' keys.
	 */
	public static function validate_page( string $page_slug ): array {
		$page = get_page_by_path( $page_slug );
		if ( $page === null ) {
			return array(
				'ok' => false,
				'message' => sprintf( 'Page "%s" not found.', $page_slug ),
				'fix' => 'Deploy the page from the deployment package.',
			);
		}

		if ( empty( $page->post_content ) ) {
			return array(
				'ok' => false,
				'message' => sprintf( 'Page "%s" has no content.', $page_slug ),
				'fix' => 'Re-deploy the page from the deployment package.',
			);
		}

		return array(
			'ok' => true,
			'message' => sprintf( 'Page "%s" exists with content.', $page_slug ),
		);
	}

	/**
	 * Run full deployment validation.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Build fingerprint.
	 * @param array  $options Validation options (cpts, assets, pages).
	 * @return array Validation results.
	 */
	public static function validate_deployment( string $project_slug, string $fingerprint, array $options = array() ): array {
		$results = array(
			'ok' => true,
			'checks' => array(),
			'errors' => array(),
			'warnings' => array(),
		);

		// Validate CPTs if requested
		if ( isset( $options['cpts'] ) && is_array( $options['cpts'] ) ) {
			foreach ( $options['cpts'] as $post_type ) {
				$check = self::validate_cpt( $post_type );
				$results['checks'][] = $check;
				if ( ! $check['ok'] ) {
					$results['ok'] = false;
					$results['errors'][] = $check;
				}
			}
		}

		// Validate assets if requested
		if ( isset( $options['assets'] ) && is_array( $options['assets'] ) ) {
			foreach ( $options['assets'] as $asset_path ) {
				$check = self::validate_asset( $asset_path, $project_slug, $fingerprint );
				$results['checks'][] = $check;
				if ( ! $check['ok'] ) {
					$results['ok'] = false;
					$results['errors'][] = $check;
				}
			}
		}

		// Validate pages if requested
		if ( isset( $options['pages'] ) && is_array( $options['pages'] ) ) {
			foreach ( $options['pages'] as $page_slug ) {
				$check = self::validate_page( $page_slug );
				$results['checks'][] = $check;
				if ( ! $check['ok'] ) {
					$results['ok'] = false;
					$results['warnings'][] = $check; // Pages are warnings, not errors
				}
			}
		}

		return $results;
	}
}
