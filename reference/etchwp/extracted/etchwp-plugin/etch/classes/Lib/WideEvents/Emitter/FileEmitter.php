<?php
/**
 * FileEmitter - Writes wide events to a file with rotation.
 *
 * @package Etch\Lib\WideEvents\Emitter
 */

namespace Etch\Lib\WideEvents\Emitter;

/**
 * FileEmitter class.
 *
 * Writes formatted event data to a file.
 * Implements log rotation when file exceeds max size.
 */
class FileEmitter implements EmitterInterface {

	/**
	 * The path to the log file.
	 *
	 * @var string
	 */
	private string $file_path;

	/**
	 * Maximum file size before rotation (in bytes).
	 *
	 * @var int
	 */
	private int $max_file_size;

	/**
	 * Maximum number of rotated files to keep.
	 *
	 * @var int
	 */
	private int $max_files;

	/**
	 * Constructor.
	 *
	 * @param string $file_path     The path to the log file.
	 * @param int    $max_file_size Maximum file size before rotation (default 5MB).
	 * @param int    $max_files     Maximum rotated files to keep (default 3).
	 */
	public function __construct(
		string $file_path,
		int $max_file_size = 5242880,
		int $max_files = 3
	) {
		$this->file_path = $file_path;
		$this->max_file_size = $max_file_size;
		$this->max_files = $max_files;
	}

	/**
	 * Emit the formatted event data to the log file.
	 *
	 * @param string $formatted_data The formatted event data to write.
	 * @return bool True on success, false on failure.
	 */
	public function emit( string $formatted_data ): bool {
		if ( ! $this->ensure_directory_exists() ) {
			return false;
		}

		$this->rotate_if_needed();

		$result = file_put_contents(
			$this->file_path,
			$formatted_data,
			FILE_APPEND | LOCK_EX
		);

		return false !== $result;
	}

	/**
	 * Ensure the directory for the log file exists.
	 *
	 * @return bool True if directory exists or was created.
	 */
	private function ensure_directory_exists(): bool {
		$dir = dirname( $this->file_path );

		if ( file_exists( $dir ) ) {
			return is_writable( $dir );
		}

		// Use wp_mkdir_p if available, otherwise mkdir.
		if ( function_exists( 'wp_mkdir_p' ) ) {
			return wp_mkdir_p( $dir );
		}

		return mkdir( $dir, 0755, true );
	}

	/**
	 * Rotate log files if the current file exceeds max size.
	 *
	 * Rotation scheme:
	 * - wide-events.log.2 is deleted
	 * - wide-events.log.1 becomes wide-events.log.2
	 * - wide-events.log becomes wide-events.log.1
	 * - A new wide-events.log is created
	 *
	 * @return void
	 */
	private function rotate_if_needed(): void {
		if ( ! file_exists( $this->file_path ) ) {
			return;
		}

		$current_size = filesize( $this->file_path );

		if ( false === $current_size || $current_size < $this->max_file_size ) {
			return;
		}

		// Delete oldest file if it exists.
		$oldest = $this->file_path . '.' . $this->max_files;
		if ( file_exists( $oldest ) ) {
			unlink( $oldest );
		}

		// Rotate existing numbered files.
		for ( $i = $this->max_files - 1; $i >= 1; $i-- ) {
			$old_name = $this->file_path . '.' . $i;
			$new_name = $this->file_path . '.' . ( $i + 1 );

			if ( file_exists( $old_name ) ) {
				rename( $old_name, $new_name );
			}
		}

		// Rotate current file to .1.
		rename( $this->file_path, $this->file_path . '.1' );
	}
}
