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
			'sku'                  => 'sku',
			'sifra'                => 'sku',
			'product_id'           => 'product_id',
			'product'              => 'product_id',
			'proizvod_id'          => 'product_id',
			'variation_id'         => 'variation_id',
			'varijacija_id'        => 'variation_id',
			'reference_date'       => 'reference_date',
			'referentni_datum'     => 'reference_date',
			'datum'                => 'reference_date',
			'datum_sidrene_cijene' => 'reference_date',
			'anchor_price'         => 'anchor_price',
			'sidrena_cijena'       => 'anchor_price',
			'cijena'               => 'anchor_price',
			'category'             => 'category',
			'kategorija'           => 'category',
			'name'                 => 'name',
			'naziv'                => 'name',
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
			return new \WP_Error( 'import_file_not_allowed', __( 'The selected file cannot be read by the plugin.', 'sidrena-cijena-eu' ) );
		}

		$size = filesize( $path );

		if ( false === $size || $size > self::MAX_BYTES ) {
			return new \WP_Error( 'import_file_too_large', __( 'The import file is too large.', 'sidrena-cijena-eu' ) );
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a validated, plugin-owned import file.

		if ( false === $contents ) {
			return new \WP_Error( 'import_file_unreadable', __( 'The import file could not be read.', 'sidrena-cijena-eu' ) );
		}

		return $this->parse_string( self::to_utf8( $contents ) );
	}

	/**
	 * Convert a legacy-encoded file to UTF-8.
	 *
	 * Croatian ERP and accounting systems still export ISO-8859-2 or
	 * Windows-1250 files. Valid UTF-8 is kept untouched; anything else is
	 * converted with the encoding that the byte pattern suggests.
	 *
	 * @param string $contents Raw file contents.
	 * @return string
	 */
	public static function to_utf8( $contents ) {
		$contents = (string) $contents;

		if ( '' === $contents || self::is_utf8( $contents ) ) {
			return $contents;
		}

		// Windows-1250 uses 0x80-0x9F for letters (š, đ, č, ć, ž); ISO-8859-2
		// only defines 0xA1-0xFF, so those bytes identify a CP1250 file.
		$order = preg_match( '/[\x80-\x9F]/', $contents )
			? array( 'Windows-1250', 'ISO-8859-2' )
			: array( 'ISO-8859-2', 'Windows-1250' );

		foreach ( $order as $encoding ) {
			$converted = self::convert( $contents, $encoding );

			if ( null !== $converted && self::is_utf8( $converted ) ) {
				return $converted;
			}
		}

		return $contents;
	}

	/**
	 * Whether the string is valid UTF-8.
	 *
	 * @param string $contents Contents to test.
	 * @return bool
	 */
	protected static function is_utf8( $contents ) {
		return (bool) preg_match( '//u', $contents );
	}

	/**
	 * Convert to UTF-8 with the first available extension.
	 *
	 * @param string $contents Raw contents.
	 * @param string $encoding Source encoding.
	 * @return string|null Converted contents, or null when conversion failed.
	 */
	protected static function convert( $contents, $encoding ) {
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( $encoding, 'UTF-8', $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input bytes are expected for non-UTF-8 files.

			if ( false !== $converted ) {
				return $converted;
			}
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $contents, 'UTF-8', $encoding );
		}

		return null;
	}

	/**
	 * Parse CSV contents.
	 *
	 * @param string $contents Raw file contents.
	 * @return array<mixed>|\WP_Error
	 */
	public function parse_string( $contents ) {
		$contents   = (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $contents );
		$first_line = substr( $contents, 0, (int) strcspn( $contents, "\r\n" ) );
		$delimiter  = $this->detect_delimiter( $first_line );
		$records    = $this->split_records( $contents );

		$header = null;

		foreach ( $records as $index => $record ) {
			if ( '' === trim( $record ) ) {
				unset( $records[ $index ] );
				continue;
			}

			$header = $this->map_header( str_getcsv( $record, $delimiter, '"', '\\' ) );
			unset( $records[ $index ] );
			break;
		}

		if ( ! $header ) {
			return new \WP_Error( 'import_missing_header', __( 'The import file has no header row.', 'sidrena-cijena-eu' ) );
		}

		if ( ! in_array( 'sku', $header, true ) && ! in_array( 'product_id', $header, true ) ) {
			return new \WP_Error( 'import_missing_identifier_column', __( 'The import file must contain an sku or product_id column.', 'sidrena-cijena-eu' ) );
		}

		$rows   = array();
		$errors = array();
		$count  = 0;

		foreach ( $records as $record ) {
			if ( '' === trim( $record ) ) {
				continue;
			}

			++$count;

			if ( $count > self::MAX_ROWS ) {
				$errors[] = array(
					'code'    => 'too_many_rows',
					'message' => __( 'The file contains more rows than the import limit.', 'sidrena-cijena-eu' ),
				);
				break;
			}

			$cells = str_getcsv( $record, $delimiter, '"', '\\' );
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
	 * Split CSV contents into records, honouring quoted newlines.
	 *
	 * `str_getcsv()` handles quoted newlines inside a single record, but the
	 * records themselves still have to be split without breaking quoted
	 * fields. Splitting on `\r\n|\r|\n` first would corrupt any field that
	 * contains a line break, including the plugin's own exports.
	 *
	 * @param string $contents Raw file contents.
	 * @return string[]
	 */
	protected function split_records( $contents ) {
		$records = array();
		$record  = '';
		$quoted  = false;
		$length  = strlen( $contents );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $contents[ $i ];

			if ( '"' === $char ) {
				// A doubled quote escapes a quote inside a quoted field.
				if ( $quoted && $i + 1 < $length && '"' === $contents[ $i + 1 ] ) {
					$record .= '""';
					++$i;
					continue;
				}

				$quoted  = ! $quoted;
				$record .= $char;
				continue;
			}

			if ( ! $quoted && ( "\n" === $char || "\r" === $char ) ) {
				if ( "\r" === $char && $i + 1 < $length && "\n" === $contents[ $i + 1 ] ) {
					++$i;
				}

				$records[] = $record;
				$record    = '';
				continue;
			}

			$record .= $char;
		}

		$records[] = $record;

		return $records;
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
