<?php
/**
 * Validation finding value object.
 *
 * @package Titanworks\SidrenaCijena\Compliance
 */

namespace Titanworks\SidrenaCijena\Compliance;

defined( 'ABSPATH' ) || exit;

/**
 * A single validator result.
 */
final class Finding {

	/**
	 * Blocking compliance error.
	 */
	const ERROR = 'ERROR';

	/**
	 * Issue requiring attention but not blocking.
	 */
	const WARNING = 'WARNING';

	/**
	 * Informational note.
	 */
	const INFO = 'INFO';

	/**
	 * Severity.
	 *
	 * @var string
	 */
	public $severity;

	/**
	 * Stable finding code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Entity type: `catalog_item`, `location` or `publication`.
	 *
	 * @var string
	 */
	public $entity_type;

	/**
	 * Entity ID.
	 *
	 * @var int
	 */
	public $entity_id;

	/**
	 * Plain-language, translatable message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Rule reference (requirement identifier).
	 *
	 * @var string
	 */
	public $rule_reference;

	/**
	 * Additional structured context.
	 *
	 * @var array<mixed>
	 */
	public $context;

	/**
	 * Actionable remediation proposal.
	 *
	 * @var string
	 */
	public $proposal;

	/**
	 * Constructor.
	 *
	 * @param string $severity  Severity constant.
	 * @param string $code      Stable code.
	 * @param string $message   Message.
	 * @param array  $args      Optional `entity_type`, `entity_id`, `rule_reference`, `context`, `proposal`.
	 */
	public function __construct( $severity, $code, $message, array $args = array() ) {
		$this->severity       = in_array( $severity, array( self::ERROR, self::WARNING, self::INFO ), true ) ? $severity : self::INFO;
		$this->code           = (string) $code;
		$this->message        = (string) $message;
		$this->entity_type    = isset( $args['entity_type'] ) ? (string) $args['entity_type'] : 'catalog_item';
		$this->entity_id      = isset( $args['entity_id'] ) ? (int) $args['entity_id'] : 0;
		$this->rule_reference = isset( $args['rule_reference'] ) ? (string) $args['rule_reference'] : '';
		$this->context        = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array();
		$this->proposal       = isset( $args['proposal'] ) && '' !== (string) $args['proposal']
			? (string) $args['proposal']
			: RemediationProposer::propose( $this->code, $this->context );
	}

	/**
	 * Error factory.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param array  $args    Extra fields.
	 * @return Finding
	 */
	public static function error( $code, $message, array $args = array() ) {
		return new self( self::ERROR, $code, $message, $args );
	}

	/**
	 * Warning factory.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param array  $args    Extra fields.
	 * @return Finding
	 */
	public static function warning( $code, $message, array $args = array() ) {
		return new self( self::WARNING, $code, $message, $args );
	}

	/**
	 * Info factory.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param array  $args    Extra fields.
	 * @return Finding
	 */
	public static function info( $code, $message, array $args = array() ) {
		return new self( self::INFO, $code, $message, $args );
	}

	/**
	 * Array representation.
	 *
	 * @return array<mixed>
	 */
	public function to_array() {
		$data = array(
			'severity'       => $this->severity,
			'code'           => $this->code,
			'entity_type'    => $this->entity_type,
			'entity_id'      => $this->entity_id,
			'message'        => $this->message,
			'rule_reference' => $this->rule_reference,
			'context'        => $this->context,
		);

		if ( '' !== $this->proposal ) {
			$data['proposal'] = $this->proposal;
		}

		return $data;
	}
}
