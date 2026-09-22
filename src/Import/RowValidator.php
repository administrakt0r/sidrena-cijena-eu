<?php
/**
 * Import row validation.
 *
 * @package SidrenaCijena\Import
 */

namespace SidrenaCijena\Import;

use SidrenaCijena\Catalog\CatalogItem;
use SidrenaCijena\Catalog\CatalogProvider;
use SidrenaCijena\Database\PriceHistoryRepository;
use SidrenaCijena\Support\Currency;
use SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Validates parsed import rows against the catalog and the stored history.
 */
final class RowValidator {

	/**
	 * Catalog provider.
	 *
	 * @var CatalogProvider
	 */
	private $catalog;

	/**
	 * Price history repository.
	 *
	 * @var PriceHistoryRepository
	 */
	private $history;

	/**
	 * Lazy catalog index.
	 *
	 * @var array<mixed>|null
	 */
	private $index = null;

	/**
	 * Constructor.
	 *
	 * @param CatalogProvider        $catalog Catalog provider.
	 * @param PriceHistoryRepository $history Price history repository.
	 */
	public function __construct( CatalogProvider $catalog, PriceHistoryRepository $history ) {
		$this->catalog = $catalog;
		$this->history = $history;
	}

	/**
	 * Validate rows and classify them.
	 *
	 * @param array<int,array<mixed>> $rows Parsed rows.
	 * @return array<mixed>
	 */
	public function validate_rows( array $rows ) {
		$results = array();
		$seen    = array();
		$summary = array(
			'valid'     => 0,
			'invalid'   => 0,
			'duplicate' => 0,
			'overwrite' => 0,
			'unchanged' => 0,
			'total'     => 0,
		);

		foreach ( $rows as $index => $row ) {
			$result = $this->validate_row( $row, (int) $index, $seen );
			$status = $result['status'];

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			}

			++$summary['total'];

			$results[] = $result;
		}

		return array(
			'rows'    => $results,
			'summary' => $summary,
		);
	}

	/**
	 * Validate a single row.
	 *
	 * @param array<mixed> $row  Parsed row.
	 * @param int          $index Row index.
	 * @param array<mixed> $seen Duplicate tracker (by reference).
	 * @return array<mixed>
	 */
	protected function validate_row( array $row, $index, array &$seen ) {
		$values = array(
			'sku'            => isset( $row['sku'] ) ? (string) $row['sku'] : '',
			'product_id'     => isset( $row['product_id'] ) ? (int) $row['product_id'] : 0,
			'variation_id'   => isset( $row['variation_id'] ) ? (int) $row['variation_id'] : 0,
			'reference_date' => $this->normalize_date( isset( $row['reference_date'] ) ? (string) $row['reference_date'] : '' ),
			'price'          => $this->normalize_price( isset( $row['anchor_price'] ) ? (string) $row['anchor_price'] : '' ),
			'category'       => isset( $row['category'] ) ? (string) $row['category'] : '',
			'name'           => isset( $row['name'] ) ? (string) $row['name'] : '',
		);

		$errors   = array();
		$warnings = array();

		if ( '' === $values['sku'] && $values['product_id'] <= 0 ) {
			$errors[] = array(
				'code'    => 'missing_identifier',
				'message' => __( 'The row has neither an SKU nor a product ID.', 'sidrena-cijena' ),
			);
		}

		if ( '' === $values['reference_date'] ) {
			$errors[] = array(
				'code'    => 'invalid_reference_date',
				'message' => __( 'The reference date is missing or not a valid date.', 'sidrena-cijena' ),
			);
		}

		if ( null === $values['price'] || $values['price'] < 0 ) {
			$errors[] = array(
				'code'    => 'invalid_anchor_price',
				'message' => __( 'The anchor price is missing or not a valid non-negative number.', 'sidrena-cijena' ),
			);
		}

		$item = $this->resolve_item( $values );

		if ( ! $item ) {
			$errors[] = array(
				'code'    => 'unknown_product',
				'message' => __( 'No catalog item matches this SKU or product ID.', 'sidrena-cijena' ),
			);
		}

		$key = $item ? $item->key() : 'row:' . $index;

		if ( isset( $seen[ $key ] ) ) {
			$errors[] = array(
				'code'    => 'duplicate_row',
				'message' => __( 'The same item appears more than once in this file.', 'sidrena-cijena' ),
			);
		}

		$seen[ $key ] = true;

		if ( $errors ) {
			return $this->result( $index, $values, $item, 'invalid', $errors, $warnings, null );
		}

		if ( '' !== $values['category'] && $item->categories && ! in_array( $values['category'], $item->categories, true ) ) {
			$warnings[] = array(
				'code'    => 'category_mismatch',
				'message' => __( 'The category in the file does not match the catalog item category.', 'sidrena-cijena' ),
			);
		}

		$existing = $this->history->latest( $item->id, $item->variation_id, 'anchor' );

		if ( ! $existing ) {
			return $this->result( $index, $values, $item, 'valid', $errors, $warnings, null );
		}

		$same_price = abs( (float) $existing['price'] - (float) $values['price'] ) < 0.000001;
		$same_date  = (string) $existing['reference_date'] === $values['reference_date'];

		if ( $same_price && $same_date ) {
			$warnings[] = array(
				'code'    => 'already_recorded',
				'message' => __( 'An identical anchor record already exists; the row will be skipped.', 'sidrena-cijena' ),
			);

			return $this->result( $index, $values, $item, 'unchanged', $errors, $warnings, $existing );
		}

		$warnings[] = array(
			'code'    => 'would_overwrite',
			'message' => __( 'This row would replace an existing anchor record and requires explicit confirmation.', 'sidrena-cijena' ),
		);

		return $this->result( $index, $values, $item, 'overwrite', $errors, $warnings, $existing );
	}

	/**
	 * Build a result row.
	 *
	 * @param int               $index    Row index.
	 * @param array<mixed>      $values   Normalized values.
	 * @param CatalogItem|null  $item     Resolved item.
	 * @param string            $status   Status.
	 * @param array<mixed>      $errors   Errors.
	 * @param array<mixed>      $warnings Warnings.
	 * @param array<mixed>|null $existing Existing anchor record.
	 * @return array<mixed>
	 */
	protected function result( $index, array $values, $item, $status, array $errors, array $warnings, $existing ) {
		return array(
			'index'    => (int) $index,
			'values'   => $values,
			'item'     => $item,
			'status'   => $status,
			'errors'   => $errors,
			'warnings' => $warnings,
			'existing' => $existing,
		);
	}

	/**
	 * Resolve a catalog item for the row values.
	 *
	 * @param array<mixed> $values Normalized values.
	 * @return CatalogItem|null
	 */
	protected function resolve_item( array $values ) {
		$index = $this->index();

		if ( '' !== $values['sku'] && isset( $index['sku'][ strtolower( $values['sku'] ) ] ) ) {
			return $index['sku'][ strtolower( $values['sku'] ) ];
		}

		if ( $values['product_id'] > 0 ) {
			$key = $values['product_id'] . ':' . $values['variation_id'];

			if ( isset( $index['items'][ $key ] ) ) {
				return $index['items'][ $key ];
			}
		}

		return null;
	}

	/**
	 * Build the catalog index lazily.
	 *
	 * @return array<mixed>
	 */
	protected function index() {
		if ( is_array( $this->index ) ) {
			return $this->index;
		}

		$index = array(
			'sku'   => array(),
			'items' => array(),
		);

		foreach ( $this->catalog->iterate( array( 'per_page' => 200 ) ) as $item ) {
			$index['items'][ $item->key() ] = $item;

			if ( '' !== (string) $item->sku ) {
				$index['sku'][ strtolower( (string) $item->sku ) ] = $item;
			}
		}

		$this->index = $index;

		return $this->index;
	}

	/**
	 * Normalize a date value to Y-m-d.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$value = rtrim( trim( (string) $value ), '.' );

		if ( '' === $value ) {
			return '';
		}

		if ( Dates::is_valid_date( $value ) ) {
			return $value;
		}

		$parsed = \DateTimeImmutable::createFromFormat( '!d.m.Y', $value );

		if ( $parsed && $parsed->format( 'd.m.Y' ) === $value ) {
			return $parsed->format( 'Y-m-d' );
		}

		return '';
	}

	/**
	 * Normalize a price value.
	 *
	 * @param string $value Raw value.
	 * @return float|null
	 */
	protected function normalize_price( $value ) {
		$value = str_replace( array( ' ', "\xC2\xA0" ), '', trim( (string) $value ) );
		$value = str_replace( ',', '.', $value );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return Currency::to_storage( $value );
	}
}
