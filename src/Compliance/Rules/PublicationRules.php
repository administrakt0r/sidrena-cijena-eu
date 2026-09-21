<?php
/**
 * Price-list publication validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Compliance\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers publication findings (missing publication, stale price list,
 * invalid filename, missing location identifier, malformed export file).
 */
class PublicationRules {

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
