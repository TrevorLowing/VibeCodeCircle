<?php
/**
 * BaseField.php
 *
 * This file defines the BaseField class, which serves as a base for custom fields in the Etch plugin.
 *
 * @package Etch\CustomFields\Fields
 */

namespace Etch\CustomFields\Fields;

/**
 * Class BaseField
 *
 * Represents a base class for custom fields.
 *
 * @phpstan-import-type CustomField from \Etch\CustomFields\CustomFieldTypes
 * @phpstan-import-type CustomFieldType from \Etch\CustomFields\CustomFieldTypes
 */
abstract class BaseField {

	/**
	 * The field's label.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * The field's key.
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * The field's type.
	 *
	 * @var CustomFieldType
	 */
	public $type;

	/**
	 * The field's description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * The field's visibility.
	 *
	 * @var bool
	 */
	public bool $required;


	/**
	 * Constructor.
	 *
	 * @param CustomField $field The custom field.
	 */
	protected function __construct( $field ) {
		$this->label       = $field['label'];
		$this->key         = $field['key'];
		$this->type        = $field['type'];
		$this->description = $field['description'] ?? '';
		$this->required    = $field['required'] ?? false;
	}

	/**
	 * Create the correct custom field base on the type of the given definition.
	 *
	 * @param CustomField $field The custom field.
	 * @param mixed       $val The field value.
	 *
	 * @return BaseField The custom field instance.
	 * @throws \InvalidArgumentException If the field type is not recognized.
	 */
	public static function create( $field, $val ): BaseField {
		switch ( $field['type'] ) {
			case 'boolean':
				return new BooleanField( $field, $val );
			case 'text':
				return new TextField( $field, $val );
			case 'number':
				return new NumberField( $field, $val );
			case 'textarea':
				return new TextAreaField( $field, $val );
			default:
				throw new \InvalidArgumentException( 'Unknown field type: ' . esc_html( $field['type'] ) );
		}
	}

	/**
	 * Render the field.
	 *
	 * @return void
	 */
	abstract public function render();

	/**
	 * Sanitize value for the field.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	abstract public function sanitize_value( $value );

	/**
	 * Render the field label.
	 *
	 * @return void
	 */
	public function render_label() {
		echo '<label for="' . esc_attr( $this->key ) . '" class="etch-cf-label">' . esc_html( $this->label ) . ( $this->required ? ' <span class="etch-cf-required">*</span>' : '' ) . '</label>';
	}
}
