<?php
/**
 * CustomFieldTypes.php
 *
 * This file defines the types and structure of custom fields used in the application.
 *
 * @package Etch\CustomFields
 */

namespace Etch\CustomFields;

/**
 * CustomFieldTypes class.
 *
 * This class defines the types and structure of custom fields used in the application.
 *
 * @phpstan-type CustomFieldType 'text' | 'textarea' | 'number' | 'boolean' | 'image'
 *
 * @phpstan-type CustomField array{
 *   label: string,
 *   key: string,
 *   type: CustomFieldType,
 *   description?: string,
 *   required?: bool
 * }
 *
 * @phpstan-type CustomFieldAssignment array{post_types: string[], op: 'isIn'|'isNotIn'}|array{post_ids: int[], op: 'isIn'|'isNotIn'}|array{taxonomies: string[], op: 'isIn'|'isNotIn'}
 *
 * @phpstan-type CustomFieldGroup array{
 *   label: string,
 *   description?: string,
 *   fields: CustomField[],
 *   assigned_to: CustomFieldAssignment
 * }
 */
final class CustomFieldTypes {}
