<?php
/**
 * Publication and location validation rules.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance\Rules;

use SidrenaCijena\Compliance\Finding;
use SidrenaCijena\Compliance\Validator;
use SidrenaCijena\Export\CsvWriter;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers global findings for locations, archived files and publication
 * freshness (FR-058: malformed CSV/XML, invalid filename, missing store
 * identifier, stale price list, missing archived price list).
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
	public function register() {
		$this->validator->register( 'missing_store_identifier', array( $this, 'missing_store_identifier' ), 'global' );
		$this->validator->register( 'missing_location_address', array( $this, 'missing_location_address' ), 'global' );
		$this->validator->register( 'invalid_filename', array( $this, 'invalid_filename' ), 'global' );
		$this->validator->register( 'missing_archived_price_list', array( $this, 'missing_archived_price_list' ), 'global' );
		$this->validator->register( 'stale_price_list', array( $this, 'stale_price_list' ), 'global' );
		$this->validator->register( 'malformed_csv', array( $this, 'malformed_csv' ), 'global' );
		$this->validator->register( 'malformed_xml', array( $this, 'malformed_xml' ), 'global' );
	}

	/**
	 * Active locations without an identifier.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_store_identifier( array $context ) {
		$findings = array();

		foreach ( $context['locations'] as $location ) {
			if ( '' !== trim( (string) $location['identifier'] ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_store_identifier',
				sprintf(
					/* translators: %s: location name. */
					__( 'The sales location %s has no identifier, so a legally named price-list file cannot be generated.', 'titanworks-sidrena-bazna-cijena' ),
					(string) $location['name']
				),
				array(
					'entity_type'    => 'location',
					'entity_id'      => (int) $location['id'],
					'rule_reference' => 'FR-040',
					'context'        => array( 'name' => (string) $location['name'] ),
				)
			);
		}

		return $findings;
	}

	/**
	 * Active locations without the address required in the filename.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_location_address( array $context ) {
		$findings = array();

		foreach ( $context['locations'] as $location ) {
			$address = '' !== trim( (string) $location['address'] ) ? trim( (string) $location['address'] ) : (string) get_option( 'woocommerce_store_address', '' );
			$city    = '' !== trim( (string) $location['city'] ) ? trim( (string) $location['city'] ) : (string) get_option( 'woocommerce_store_city', '' );

			if ( '' !== $address && '' !== $city ) {
				continue;
			}

			$findings[] = Finding::error(
				'missing_location_address',
				sprintf(
					/* translators: %s: location name. */
					__( 'The sales location %s needs a street address and city for the required price-list filename.', 'titanworks-sidrena-bazna-cijena' ),
					(string) $location['name']
				),
				array(
					'entity_type'    => 'location',
					'entity_id'      => (int) $location['id'],
					'rule_reference' => 'FR-040',
					'context'        => array( 'name' => (string) $location['name'] ),
				)
			);
		}

		return $findings;
	}

	/**
	 * Publication filenames that do not match the required pattern.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function invalid_filename( array $context ) {
		$findings = array();

		foreach ( $context['publications'] as $row ) {
			$filename = isset( $row['filename'] ) ? (string) $row['filename'] : '';

			if ( '' !== $filename && FilenameGenerator::validate( $filename ) ) {
				continue;
			}

			$findings[] = Finding::error(
				'invalid_filename',
				sprintf(
					/* translators: %s: file name. */
					__( 'The published file name %s does not follow the required pattern.', 'titanworks-sidrena-bazna-cijena' ),
					'' === $filename ? '—' : $filename
				),
				array(
					'entity_type'    => 'publication',
					'entity_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'rule_reference' => 'FR-040',
					'context'        => array(
						'filename'    => $filename,
						'location_id' => isset( $row['location_id'] ) ? (int) $row['location_id'] : 0,
						'pattern'     => FilenameGenerator::pattern(),
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Locations missing an archived price list for an expected format.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function missing_archived_price_list( array $context ) {
		$findings = array();

		foreach ( $context['locations'] as $location ) {
			foreach ( $this->expected_formats( $location ) as $format ) {
				$key = (int) $location['id'] . ':' . $format;

				if ( isset( $context['publications'][ $key ] ) && isset( $context['publication_files'][ $key ] ) ) {
					continue;
				}

				$exists = isset( $context['publications'][ $key ] );

				$findings[] = Finding::error(
					'missing_archived_price_list',
					$exists
						? sprintf(
							/* translators: 1: format, 2: location name. */
							__( 'The archived %1$s price list for %2$s is missing from storage.', 'titanworks-sidrena-bazna-cijena' ),
							strtoupper( $format ),
							(string) $location['name']
						)
						: sprintf(
							/* translators: 1: format, 2: location name. */
							__( 'No archived %1$s price list exists for %2$s.', 'titanworks-sidrena-bazna-cijena' ),
							strtoupper( $format ),
							(string) $location['name']
						),
					array(
						'entity_type'    => 'location',
						'entity_id'      => (int) $location['id'],
						'rule_reference' => 'FR-043',
						'context'        => array(
							'format' => $format,
							'name'   => (string) $location['name'],
							'record' => $exists,
						),
					)
				);
			}
		}

		return $findings;
	}

	/**
	 * Retained price lists that were not republished for the current day.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function stale_price_list( array $context ) {
		$findings = array();
		$today    = isset( $context['today'] ) ? (string) $context['today'] : Dates::today();

		foreach ( $context['publications'] as $key => $row ) {
			$published = isset( $row['published_at'] ) ? (string) $row['published_at'] : '';

			if ( '' === $published ) {
				continue;
			}

			$site_date = Dates::from_utc( $published )->format( 'Y-m-d' );

			if ( $site_date >= $today ) {
				continue;
			}

			$findings[] = Finding::warning(
				'stale_price_list',
				sprintf(
					/* translators: 1: format, 2: last publication date. */
					__( 'The %1$s price list was last published on %2$s. Publish the current list before the daily deadline.', 'titanworks-sidrena-bazna-cijena' ),
					strtoupper( (string) $row['format'] ),
					$site_date
				),
				array(
					'entity_type'    => 'publication',
					'entity_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'rule_reference' => 'FR-041',
					'context'        => array(
						'location_id'    => isset( $row['location_id'] ) ? (int) $row['location_id'] : 0,
						'format'         => isset( $row['format'] ) ? (string) $row['format'] : '',
						'last_published' => $published,
						'key'            => (string) $key,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * CSV files whose header does not match the published contract.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function malformed_csv( array $context ) {
		$findings = array();
		$expected = implode( CsvWriter::DELIMITER, CsvWriter::COLUMNS );

		foreach ( $context['publication_files'] as $key => $path ) {
			$row = isset( $context['publications'][ $key ] ) ? $context['publications'][ $key ] : array();

			if ( 'csv' !== ( isset( $row['format'] ) ? $row['format'] : '' ) ) {
				continue;
			}

			$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Read-only check of a plugin-owned file.

			if ( ! $handle ) {
				continue;
			}

			$line = fgets( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets -- Read-only check of a plugin-owned file.
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Read-only check of a plugin-owned file.

			$header = false === $line ? '' : rtrim( $line, "\r\n" );

			if ( $header === $expected ) {
				continue;
			}

			$findings[] = Finding::error(
				'malformed_csv',
				__( 'The published CSV file does not start with the required column header.', 'titanworks-sidrena-bazna-cijena' ),
				array(
					'entity_type'    => 'publication',
					'entity_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'rule_reference' => 'FR-043',
					'context'        => array(
						'filename' => isset( $row['filename'] ) ? (string) $row['filename'] : '',
						'header'   => $header,
						'expected' => $expected,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * XML files that cannot be parsed.
	 *
	 * @param array<mixed> $context Validation context.
	 * @return Finding[]
	 */
	public function malformed_xml( array $context ) {
		$findings = array();

		foreach ( $context['publication_files'] as $key => $path ) {
			$row = isset( $context['publications'][ $key ] ) ? $context['publications'][ $key ] : array();

			if ( 'xml' !== ( isset( $row['format'] ) ? $row['format'] : '' ) ) {
				continue;
			}

			$previous = libxml_use_internal_errors( true );
			$document = simplexml_load_file( $path );
			$errors   = libxml_get_errors();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			if ( false !== $document && empty( $errors ) ) {
				continue;
			}

			$message = empty( $errors ) ? '' : trim( $errors[0]->message );

			$findings[] = Finding::error(
				'malformed_xml',
				__( 'The published XML file cannot be parsed as valid XML.', 'titanworks-sidrena-bazna-cijena' ),
				array(
					'entity_type'    => 'publication',
					'entity_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'rule_reference' => 'FR-043',
					'context'        => array(
						'filename' => isset( $row['filename'] ) ? (string) $row['filename'] : '',
						'error'    => $message,
					),
				)
			);
		}

		return $findings;
	}

	/**
	 * Formats expected for a location.
	 *
	 * @param array<mixed> $location Location row.
	 * @return string[]
	 */
	protected function expected_formats( array $location ) {
		$formats = (array) Settings::get( 'formats', array( 'csv', 'xml' ) );
		$config  = isset( $location['price_list_config'] ) && is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();

		if ( ! empty( $config['formats'] ) && is_array( $config['formats'] ) ) {
			$formats = array_intersect( $formats, $config['formats'] );
		}

		$formats = array_filter( array_map( 'strtolower', array_map( 'strval', $formats ) ) );

		return array_values( array_unique( $formats ) );
	}
}
