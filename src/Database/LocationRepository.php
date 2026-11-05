<?php
/**
 * Sales location data access.
 *
 * @package SidrenaCijena\Database
 */

namespace SidrenaCijena\Database;

use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `{prefix}sidrena_locations`.
 */
class LocationRepository {

	/**
	 * Known location types.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'prodavaonica', 'webshop', 'poslovnica', 'usluzno_mjesto' );

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'sidrena_locations';
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table;
	}

	/**
	 * All locations.
	 *
	 * @param bool $active_only Only active locations.
	 * @return array<mixed>[]
	 */
	public function all( $active_only = false ) {
		global $wpdb;

		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY id ASC', $this->table ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', $this->table ),
				ARRAY_A
			);
		}

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Single location.
	 *
	 * @param int $id Location ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, (int) $id ),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Find by facility identifier.
	 *
	 * @param string $identifier Identifier.
	 * @return array|null
	 */
	public function find_by_identifier( $identifier ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE identifier = %s', $this->table, (string) $identifier ),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Insert a location.
	 *
	 * @param array<mixed> $data Location fields.
	 * @return int Inserted ID.
	 * @throws \InvalidArgumentException When required fields are missing.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = $this->normalize( $data, null );

		if ( $this->find_by_identifier( $row['identifier'] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->table, $row, array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a location.
	 *
	 * @param int          $id   Location ID.
	 * @param array<mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$existing = $this->get( $id );

		if ( ! $existing ) {
			return false;
		}

		$row = $this->normalize( array_merge( $existing, $data ), $existing );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$row,
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a location. Callers must verify that no retained publications exist.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $this->table, array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Increment and return the location's sequence counter.
	 *
	 * @param int $id Location ID.
	 * @return int The sequence number to use for the next file.
	 */
	public function next_sequence( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET sequence_no = LAST_INSERT_ID(sequence_no + 1), updated_at = %s WHERE id = %d',
				$this->table,
				Dates::now_utc(),
				(int) $id
			)
		);

		if ( ! empty( $wpdb->insert_id ) ) {
			return (int) $wpdb->insert_id;
		}

		// Fallback when driver does not populate insert_id on UPDATE:
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT sequence_no FROM %i WHERE id = %d', $this->table, (int) $id )
		);
	}

	/**
	 * Create the default webshop location when none exists.
	 *
	 * @return int Location ID.
	 */
	public function ensure_default_webshop() {
		$existing = $this->all();

		if ( ! empty( $existing ) ) {
			return (int) $existing[0]['id'];
		}

		$address = (string) get_option( 'woocommerce_store_address', '' );
		$city    = (string) get_option( 'woocommerce_store_city', '' );

		return $this->insert(
			array(
				'name'       => __( 'Webshop', 'sidrena-cijena-eu' ),
				'type'       => 'webshop',
				'identifier' => 'P1',
				'address'    => '' !== $address ? $address : __( 'Online', 'sidrena-cijena-eu' ),
				'city'       => '' !== $city ? $city : __( 'Hrvatska', 'sidrena-cijena-eu' ),
				'active'     => true,
			)
		);
	}

	/**
	 * Normalize a location row for persistence.
	 *
	 * @param array      $data     Input.
	 * @param array|null $existing Existing row when updating.
	 * @return array<mixed>
	 * @throws \InvalidArgumentException When required fields are missing.
	 */
	protected function normalize( array $data, $existing ) {
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';

		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'A location requires a name.' );
		}

		$identifier = isset( $data['identifier'] ) ? strtoupper( sanitize_text_field( (string) $data['identifier'] ) ) : '';

		if ( '' === $identifier ) {
			throw new \InvalidArgumentException( 'A location requires an identifier.' );
		}

		$type = isset( $data['type'] ) ? (string) $data['type'] : 'prodavaonica';

		if ( ! in_array( $type, self::TYPES, true ) ) {
			$type = 0 === strpos( $type, 'other:' ) ? 'other:' . sanitize_key( substr( $type, 6 ) ) : 'other:' . sanitize_key( $type );
		}

		return array(
			'name'              => $name,
			'type'              => $type,
			'address'           => isset( $data['address'] ) ? sanitize_text_field( (string) $data['address'] ) : '',
			'city'              => isset( $data['city'] ) ? sanitize_text_field( (string) $data['city'] ) : '',
			'identifier'        => $identifier,
			'active'            => empty( $data['active'] ) ? 0 : 1,
			'applies_to'        => $this->encode_json( isset( $data['applies_to'] ) ? $data['applies_to'] : array() ),
			'price_list_config' => $this->encode_json( isset( $data['price_list_config'] ) ? $data['price_list_config'] : array() ),
			'sequence_no'       => $existing ? (int) $existing['sequence_no'] : ( isset( $data['sequence_no'] ) ? (int) $data['sequence_no'] : 0 ),
			'created_at'        => $existing ? $existing['created_at'] : Dates::now_utc(),
			'updated_at'        => Dates::now_utc(),
		);
	}

	/**
	 * Decode JSON columns.
	 *
	 * @param array<mixed> $row Database row.
	 * @return array<mixed>
	 */
	protected function hydrate( array $row ) {
		$row['id']                = (int) $row['id'];
		$row['active']            = (bool) $row['active'];
		$row['sequence_no']       = (int) $row['sequence_no'];
		$row['applies_to']        = $this->decode_json( $row['applies_to'] );
		$row['price_list_config'] = $this->decode_json( $row['price_list_config'] );

		if ( empty( $row['address'] ) ) {
			$wc_address = (string) get_option( 'woocommerce_store_address', '' );
			if ( '' !== $wc_address ) {
				$row['address'] = $wc_address;
			}
		}

		if ( empty( $row['city'] ) ) {
			$wc_city = (string) get_option( 'woocommerce_store_city', '' );
			if ( '' !== $wc_city ) {
				$row['city'] = $wc_city;
			}
		}

		return $row;
	}

	/**
	 * Encode an array to JSON.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	protected function encode_json( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}

		if ( empty( $value ) ) {
			return null;
		}

		return (string) wp_json_encode( $value );
	}

	/**
	 * Decode a JSON column.
	 *
	 * @param mixed $value Value.
	 * @return array<mixed>
	 */
	protected function decode_json( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
