<?php
/**
 * Special sale validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Compliance\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers special-sale findings (missing sale type, missing 30-day lowest
 * price, malformed sale window).
 */
class SaleRules {

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
