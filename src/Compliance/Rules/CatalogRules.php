<?php
/**
 * Catalog data validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Compliance\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers catalog findings (missing SKU, name, barcode, unit, price,
 * availability, invalid currency).
 */
class CatalogRules {

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	protected $validator;

	/**
	 * Constructor.
	 *
	 * @param Validator $validator Validator instance.
	 */
	public function __construct( Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Register the rule callbacks.
	 *
	 * @return void
	 */
	public function register() {}
}
