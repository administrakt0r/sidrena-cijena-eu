<?php
/**
 * CSV parser for anchor price imports.
 *
 * @package SidrenaCijena\Import
 */

namespace SidrenaCijena\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Reads import files and normalizes their headers and values.
 */
final class CsvParser {

	const MAX_ROWS = 20000;

	const MAX_BYTES = 5242880;

	/**
	 * Accepted header aliases mapped to canonical field names.
	 *
	 * @return array<string,string>
	 */
	public static function aliases() {
		return array(
			'sku'              => 'sku',
			'sifra'            => 'sku',
			'product_id'       => 'product_id',
			'product'          => 'product_id',
			'proizvod_id'      => 'product_id',
			'variation_id'     => 'variation_id',
			'varijacija_id'    => 'variation_id',
			'reference_date'   => 'reference_date',
			'referentni_datum' => 'reference_date',
			'datum'            => 'reference_date',
			'anchor_price'     => 'anchor_price',
			'sidrena_cijena'   => 'anchor_price',
			'cijena'           => 'anchor_price',
			'category'         => 'category',
			'kategorija'       => 'category',
			'name'             => 'name',
			'naziv'            => 'name',
		);
	}

	/**
	 * Read a file and return parsed rows.
	 *
	 * @param string $path Absolute file path.
	 * @return array<mixed>|\WP_Error
	 */
	public function parse_file( $path ) {
		$path = (string) $path;

		if ( ! $this->is_allowed_path( $path ) ) {
			return new \WP_Error( 'import_file_not_allowed', __( 'The selected file cannot be read by the plugin.', 'sidrena-cijena' ) );
		}

		$size = filesize( $path );

		if ( false === $size || $size > self::MAX_BYTES ) {
			return new \WP_Error( 'import_file_too_large', __( 'The import file is too large.', 'sidrena-cijena' ) );
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a validated, plugin-owned import file.

		if ( false === $contents ) {
			return new \WP_Error( 'import_file_unreadable', __( 'The import file could not be read.', 'sidrena-cijena' ) );
		}

		return $this->parse_string( $contents );
	}

	/**
	 * Parse CSV contents.
	 *
	 * @param string $contents Raw file contents.
	 * @return array<mixed>|\WP_Error
	 */
	public function parse_string( $contents ) {
		$contents = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $contents );
		$lines    = preg_split( '/\r\n|\r|\n/', (string) $contents );
		$lines    = is_array( $lines ) ? $lines : array();

		$header    = null;
		$delimiter = ';';

		foreach ( $lines as $index => $line ) {
			if ( '' === trim( (string) $line ) ) {
				unset( $lines[ $index ] );
				continue;
			}

			$delimiter = $this->detect_delimiter( (string) $line );
			$header    = $this->map_header( str_getcsv( (string) $line, $delimiter, '"', '\\' ) );
			unset( $lines[ $index ] );
			break;
		}

		if ( ! $header ) {
			return new \WP_Error( 'import_missing_header', __( 'The import file has no header row.', 'sidrena-cijena' ) );
		}

		if ( ! in_array( 'sku', $header, true ) && ! in_array( 'product_id', $header, true ) ) {
			return new \WP_Error( 'import_missing_identifier_column', __( 'The import file must contain an sku or product_id column.', 'sidrena-cijena' ) );
		}

		$rows   = array();
		$errors = array();
		$count  = 0;

		foreach ( $lines as $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}

			++$count;

			if ( $count > self::MAX_ROWS ) {
				$errors[] = array(
					'code'    => 'too_many_rows',
					'message' => __( 'The file contains more rows than the import limit.', 'sidrena-cijena' ),
				);
				break;
			}

			$cells = str_getcsv( (string) $line, $delimiter, '"', '\\' );
			$row   = array();

			foreach ( $header as $column => $field ) {
				$value         = isset( $cells[ $column ] ) ? trim( (string) $cells[ $column ] ) : '';
				$row[ $field ] = $value;
			}

			$rows[] = $row;
		}

		return array(
			'rows'      => $rows,
			'errors'    => $errors,
			'headers'   => array_values( $header ),
			'delimiter' => $delimiter,
		);
	}

	/**
	 * Whether the path may be read by the importer.
	 *
	 * @param string $path Candidate path.
	 * @return bool
	 */
	protected function is_allowed_path( $path ) {
		if ( '' === $path || false !== strpos( $path, '..' ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$real = realpath( $path );

		if ( false === $real ) {
			return false;
		}

		$upload = wp_upload_dir();
		$roots  = array( realpath( sys_get_temp_dir() ) );

		if ( ! empty( $upload['basedir'] ) ) {
			$roots[] = realpath( $upload['basedir'] );
		}

		foreach ( $roots as $root ) {
			if ( $root && ( $real === $root || 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect the delimiter of the header line.
	 *
	 * @param string $line Header line.
	 * @return string
	 */
	protected function detect_delimiter( $line ) {
		$candidates = array( ';', ',', "\t", '|' );
		$best       = ';';
		$best_count = 0;

		foreach ( $candidates as $candidate ) {
			$count = substr_count( $line, $candidate );

			if ( $count > $best_count ) {
				$best       = $candidate;
				$best_count = $count;
			}
		}

		return $best;
	}

	/**
	 * Map raw header cells to canonical field names.
	 *
	 * @param array<int,string|null> $cells Raw header cells.
	 * @return array<int,string>
	 */
	protected function map_header( array $cells ) {
		$aliases = self::aliases();
		$header  = array();

		foreach ( $cells as $index => $cell ) {
			$key = $this->normalize_key( (string) $cell );

			if ( '' !== $key && isset( $aliases[ $key ] ) ) {
				$header[ $index ] = $aliases[ $key ];
			}
		}

		return $header;
	}

	/**
	 * Normalize a header cell.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function normalize_key( $value ) {
		$value = strtolower( trim( $value ) );
		$value = strtr(
			$value,
			array(
				'š' => 's',
				'đ' => 'd',
				'č' => 'c',
				'ć' => 'c',
				'ž' => 'z',
			)
		);
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );

		return trim( (string) $value, '_' );
	}
}
