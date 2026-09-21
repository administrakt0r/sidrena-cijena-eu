<?php
/**
 * Anchor price validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Compliance\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers anchor-price findings (missing anchor, invalid reference date,
 * stored anchor recorded for a different reference date).
 */
class AnchorRules {

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
