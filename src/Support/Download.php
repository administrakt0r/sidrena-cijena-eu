<?php
/**
 * Byte-preserving attachment output.
 *
 * @package Titanworks\SidrenaCijena\Support
 */

namespace Titanworks\SidrenaCijena\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Streams machine-readable attachments, whose writers escape CSV/XML fields.
 * HTML escaping would corrupt their data, XML declarations and checksums.
 */
final class Download {

	/**
	 * Write generated file bytes to the response output stream.
	 * Callers must send a non-HTML attachment content type before writing.
	 *
	 * @param string $content Generated attachment bytes.
	 * @return void
	 */
	public static function write( $content ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- HTTP response stream, not a filesystem file.
		$stream = fopen( 'php://output', 'wb' );
		if ( false === $stream ) {
			return;
		}
		$offset = 0;
		$length = strlen( $content );
		while ( $offset < $length ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Preserve the attachment's format-specific escaping.
			$written = fwrite( $stream, substr( $content, $offset ) );
			if ( false === $written || 0 === $written ) {
				break;
			}
			$offset += $written;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the response stream.
		fclose( $stream );
	}
}
