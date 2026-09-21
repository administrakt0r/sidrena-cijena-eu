<?php
/**
 * Filename generator tests.
 *
 * @package SidrenaCijena\Tests\Unit\Export
 */

namespace SidrenaCijena\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Support\Settings;

/**
 * @covers \SidrenaCijena\Export\FilenameGenerator
 */
class FilenameGeneratorTest extends TestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sidrena_test_options'] = array();
	}

	/**
	 * Location fixture.
	 *
	 * @param array<mixed> $overrides Overrides.
	 * @return array<mixed>
	 */
	protected function location( array $overrides = array() ) {
		return array_merge(
			array(
				'id'         => 7,
				'name'       => 'Prodavaonica Vlaška',
				'type'       => 'prodavaonica',
				'address'    => 'Vlaška 68',
				'city'       => 'Zagreb',
				'identifier' => 'P1',
			),
			$overrides
		);
	}

	/**
	 * The documented component order and example.
	 *
	 * @return void
	 */
	public function test_generate_builds_contract_filename() {
		$generator = new FilenameGenerator();

		$when = new \DateTimeImmutable( '2026-10-01 07:50:00', new \DateTimeZone( 'Europe/Zagreb' ) );

		$this->assertSame(
			'PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV',
			$generator->generate( $this->location(), 'csv', 1, $when )
		);
	}

	/**
	 * Diacritics are transliterated.
	 *
	 * @return void
	 */
	public function test_generate_transliterates_diacritics() {
		$generator = new FilenameGenerator();

		$when = new \DateTimeImmutable( '2026-10-01 07:50:00', new \DateTimeZone( 'Europe/Zagreb' ) );

		$filename = $generator->generate(
			$this->location(
				array(
					'address' => 'Šubićeva 12',
					'city'    => 'Čakovec',
				)
			),
			'xml',
			3,
			$when
		);

		$this->assertSame( 'PRODAVAONICA_SUBICEVA12_CAKOVEC_P1_03_01102026_0750.XML', $filename );
	}

	/**
	 * Separator and letter case are configurable.
	 *
	 * @return void
	 */
	public function test_separator_and_case_configuration() {
		update_option(
			Settings::OPTION,
			array(
				'filename_separator' => '-',
				'filename_case'      => 'lower',
			),
			false
		);

		$generator = new FilenameGenerator();

		$when = new \DateTimeImmutable( '2026-10-01 07:50:00', new \DateTimeZone( 'Europe/Zagreb' ) );

		$this->assertSame(
			'prodavaonica-vlaska68-zagreb-p1-02-01102026-0750.csv',
			$generator->generate( $this->location(), 'csv', 2, $when )
		);
	}

	/**
	 * Sequence numbers are padded to at least two digits.
	 *
	 * @return void
	 */
	public function test_sequence_padding() {
		$generator = new FilenameGenerator();

		$when = new \DateTimeImmutable( '2026-10-01 07:50:00', new \DateTimeZone( 'Europe/Zagreb' ) );

		$this->assertStringContainsString( '_100_', (string) $generator->generate( $this->location(), 'csv', 100, $when ) );
		$this->assertStringContainsString( '_09_', (string) $generator->generate( $this->location(), 'csv', 9, $when ) );
	}

	/**
	 * Missing identifiers block generation.
	 *
	 * @return void
	 */
	public function test_missing_identifier_is_rejected() {
		$generator = new FilenameGenerator();

		$result = $generator->generate( $this->location( array( 'identifier' => '' ) ), 'csv', 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_filename', $result->get_error_code() );
	}

	/**
	 * Unknown formats are rejected.
	 *
	 * @return void
	 */
	public function test_unknown_format_is_rejected() {
		$generator = new FilenameGenerator();

		$this->assertInstanceOf( \WP_Error::class, $generator->generate( $this->location(), 'pdf', 1 ) );
	}

	/**
	 * Webshop locations without an address still generate a valid name.
	 *
	 * @return void
	 */
	public function test_webshop_without_address() {
		$generator = new FilenameGenerator();

		$when = new \DateTimeImmutable( '2026-10-01 07:50:00', new \DateTimeZone( 'Europe/Zagreb' ) );

		$this->assertSame(
			'WEBSHOP_P1_01_01102026_0750.CSV',
			$generator->generate(
				$this->location(
					array(
						'type'    => 'webshop',
						'address' => '',
						'city'    => '',
					)
				),
				'csv',
				1,
				$when
			)
		);
	}

	/**
	 * Custom location types become normalized tokens.
	 *
	 * @return void
	 */
	public function test_custom_type_token() {
		$this->assertSame( 'SKLADISTE', FilenameGenerator::facility_form( 'other:skladište' ) );
		$this->assertSame( 'PRODAVAONICA', FilenameGenerator::facility_form( 'prodavaonica' ) );
	}

	/**
	 * Validation rejects illegal characters and extensions.
	 *
	 * @return void
	 */
	public function test_validate_rejects_invalid_names() {
		$this->assertTrue( FilenameGenerator::validate( 'PRODAVAONICA_VLASKA68_ZAGREB_P1_01_01102026_0750.CSV' ) );
		$this->assertFalse( FilenameGenerator::validate( 'PRODAVAONICA/ETC_PASSWD.CSV' ) );
		$this->assertFalse( FilenameGenerator::validate( 'PRODAVAONICA_P1_01_01102026_0750.PDF' ) );
		$this->assertFalse( FilenameGenerator::validate( 'PRODAVAONICA_P1_01102026.CSV' ) );
	}
}
