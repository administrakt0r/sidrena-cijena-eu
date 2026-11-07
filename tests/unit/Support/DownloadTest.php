<?php
/**
 * Attachment output regression tests.
 *
 * @package Titanworks\SidrenaCijena\Tests
 */

namespace Titanworks\SidrenaCijena\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Titanworks\SidrenaCijena\Support\Download;

/**
 * Attachment streams must retain format-specific escaping and exact bytes.
 */
class DownloadTest extends TestCase {

	/**
	 * @return void
	 */
	public function test_csv_and_xml_bytes_are_preserved(): void {
		foreach ( array( "naziv;cijena\n\"Čaj & <kava>\";1,99\n", '<?xml version="1.0"?><name>Čaj &amp; kava</name>', '' ) as $content ) {
			ob_start();
			Download::write( $content );
			$this->assertSame( $content, ob_get_clean() );
		}
	}
}
