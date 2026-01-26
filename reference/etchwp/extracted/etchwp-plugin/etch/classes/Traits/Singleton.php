<?php
/**
 * Singleton class file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Traits;

trait Singleton {

	/**
	 * Stores the instance of the class using this trait.
	 *
	 * @var static|null
	 */
	protected static ?self $instance = null;

	/**
	 * The Singleton's constructor should always be protected to prevent direct
	 * construction calls with the `new` operator.
	 */
	protected function __construct() { }

	/**
	 * Singletons should not be cloneable.
	 */
	protected function __clone() { }

	/**
	 * Singletons should not be restorable from strings.
	 *
	 * @throws \Exception Cannot unserialize a singleton.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}

	/**
	 * This is the static method that controls access to the singleton
	 * instance. On the first run, it creates a singleton object and places it
	 * into the static field. On subsequent runs, it returns the existing
	 * instance stored in the static field.
	 *
	 * @return static
	 */
	public static function get_instance(): self {
		if ( null === static::$instance ) {
			static::$instance = ( new \ReflectionClass( static::class ) )->newInstanceWithoutConstructor();
			static::$instance->__construct();
		}
		return static::$instance;
	}
}
