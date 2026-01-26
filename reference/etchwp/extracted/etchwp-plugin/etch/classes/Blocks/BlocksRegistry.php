<?php
/**
 * Blocks Registry
 *
 * Central registry for all custom Gutenberg blocks.
 *
 * @package Etch
 */

namespace Etch\Blocks;

use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\ConditionBlock\ConditionBlock;
use Etch\Blocks\LoopBlock\LoopBlock;
use Etch\Blocks\DynamicElementBlock\DynamicElementBlock;
use Etch\Blocks\RawHtmlBlock\RawHtmlBlock;
use Etch\Blocks\ElementBlock\ElementBlock;
use Etch\Blocks\Global\ContentWrapper;
use Etch\Blocks\SvgBlock\SvgBlock;
use Etch\Blocks\DynamicImageBlock\DynamicImageBlock;
use Etch\Blocks\TextBlock\TextBlock;
use Etch\Blocks\SlotContentBlock\SlotContentBlock;
use Etch\Blocks\SlotPlaceholderBlock\SlotPlaceholderBlock;
use Etch\Blocks\Global\StylesRegister;
use Etch\Blocks\Global\ScriptRegister;

/**
 * BlocksRegistry class
 *
 * Handles registration of all custom blocks in the plugin.
 */
class BlocksRegistry {

	/**
	 * Array of block instances
	 *
	 * @var array<object>
	 */
	private array $blocks = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_blocks();
		$this->initialize_global_services();
	}

	/**
	 * Register all blocks
	 *
	 * @return void
	 */
	private function register_blocks() {
		// Register all custom blocks here
		$this->blocks[] = new ElementBlock();
		$this->blocks[] = new TextBlock();
		$this->blocks[] = new ComponentBlock();
		$this->blocks[] = new ConditionBlock();
		$this->blocks[] = new LoopBlock();
		$this->blocks[] = new DynamicElementBlock();
		$this->blocks[] = new SvgBlock();
		$this->blocks[] = new SlotContentBlock();
		$this->blocks[] = new SlotPlaceholderBlock();
		$this->blocks[] = new RawHtmlBlock();
		$this->blocks[] = new DynamicImageBlock();
		// Future blocks can be added here:
		// $this->blocks[] = new AnotherBlock();
	}

	/**
	 * Initialize global services
	 *
	 * @return void
	 */
	private function initialize_global_services() {
		new StylesRegister();
		new ScriptRegister();
		new ContentWrapper();
	}

	/**
	 * Get all registered blocks
	 *
	 * @return array<object>
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}
}
