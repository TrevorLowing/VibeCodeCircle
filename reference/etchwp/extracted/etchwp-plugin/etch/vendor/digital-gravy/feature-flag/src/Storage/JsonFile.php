<?php
/**
 * JSON File Storage
 *
 * @package DigitalGravy\FeatureFlag\Storage
 */

namespace DigitalGravy\FeatureFlag\Storage;

use DigitalGravy\FeatureFlag\FeatureFlag;
use DigitalGravy\FeatureFlag\Helpers\Functions;
use DigitalGravy\FeatureFlag\Storage\Exception\FileNotFoundException;
use DigitalGravy\FeatureFlag\Storage\Exception\FileNotReadableException;

class JsonFile implements FlagStorageInterface {

	private string $file_path;

	/**
	 * @param string $file_path Path to JSON file containing feature flags.
	 * @throws \InvalidArgumentException If file path is invalid.
	 */
	public function __construct( string $file_path ) {
		if ( empty( $file_path ) ) {
			throw new \InvalidArgumentException( 'File path cannot be empty' );
		}

		$this->file_path = $file_path;
	}

	public function get_flags(): array {
		$flags_clean = array();
		$flags_dirty = $this->get_flags_from_file();
		foreach ( $flags_dirty as $key => $value ) {
			try {
				if ( ! is_string( $key ) || ! is_string( $value ) ) {
					continue;
				}
				$flag = new FeatureFlag( $key, $value );
				$flags_clean[ $flag->key ] = $flag;
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return $flags_clean;
	}

	/**
	 * @return array<mixed,mixed> Array of contents from JSON file
	 * @throws \UnexpectedValueException If JSON file does not decode to an array.
	 */
	private function get_flags_from_file(): array {
		$file_contents = $this->get_file_contents();

		$flags_dirty = json_decode( $file_contents, true, 512, JSON_THROW_ON_ERROR );

		if ( ! is_array( $flags_dirty ) ) {
			throw new \UnexpectedValueException( 'JSON file must decode to an array' );
		}

		return $flags_dirty;
	}

	/**
	 * @throws FileNotFoundException If file path is invalid.
	 * @throws FileNotReadableException If file is not readable.
	 * @return string The contents of the file.
	 */
	private function get_file_contents(): string {
		if ( ! file_exists( $this->file_path ) ) {
			throw new FileNotFoundException( Functions::escape_output( $this->file_path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! is_readable( $this->file_path ) ) {
			throw new FileNotReadableException( Functions::escape_output( $this->file_path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$file_contents = file_get_contents( $this->file_path );
		if ( false === $file_contents ) {
			throw new FileNotReadableException( Functions::escape_output( $this->file_path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $file_contents;
	}
}
