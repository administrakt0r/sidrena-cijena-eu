<?php
/**
 * Price-list publication writer.
 *
 * @package SidrenaCijena\Export
 */

namespace SidrenaCijena\Export;

use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Database\PublicationRepository;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Writes generated price lists under `uploads/sidrena-cijena/`, computes the
 * checksum, archives the publication row and supersedes the previous version.
 */
class Publisher {

	/**
	 * Uploads subdirectory.
	 *
	 * @var string
	 */
	const DIR = 'sidrena-cijena';

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	protected $locations;

	/**
	 * Publication repository.
	 *
	 * @var PublicationRepository
	 */
	protected $publications;

	/**
	 * Filename generator.
	 *
	 * @var FilenameGenerator
	 */
	protected $filenames;

	/**
	 * Constructor.
	 *
	 * @param LocationRepository    $locations    Location repository.
	 * @param PublicationRepository $publications Publication repository.
	 * @param FilenameGenerator     $filenames    Filename generator.
	 */
	public function __construct( LocationRepository $locations, PublicationRepository $publications, FilenameGenerator $filenames ) {
		$this->locations    = $locations;
		$this->publications = $publications;
		$this->filenames    = $filenames;
	}

	/**
	 * Publish a price list.
	 *
	 * @param array<mixed> $location Location row.
	 * @param string       $format   Format (`csv` or `xml`).
	 * @param string       $content  Generated file content.
	 * @param array<mixed> $meta     Meta: `fingerprint`, `item_count`, `rule_version`, `force`.
	 * @return array<mixed>|\WP_Error
	 */
	public function publish( array $location, $format, $content, array $meta = array() ) {
		$format      = strtolower( (string) $format );
		$location_id = isset( $location['id'] ) ? (int) $location['id'] : 0;
		$fingerprint = isset( $meta['fingerprint'] ) ? (string) $meta['fingerprint'] : hash( 'sha256', (string) $content );
		$force       = ! empty( $meta['force'] );

		$latest = $this->publications->latest( $location_id, $format );

		if ( ! $force && $latest && ! empty( $latest['content_fingerprint'] ) && $latest['content_fingerprint'] === $fingerprint && $this->file_exists( $latest['path'] ) ) {
			return array(
				'publication' => $latest,
				'skipped'     => true,
				'filename'    => (string) $latest['filename'],
			);
		}

		$dir = $this->directory( $location );

		if ( ! $dir ) {
			return new \WP_Error( 'publication_directory_failed', __( 'The price-list storage directory could not be created.', 'sidrena-cijena' ) );
		}

		$sequence = 0;
		$filename = '';
		$path     = '';

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$sequence = $this->locations->next_sequence( $location_id );
			$filename = $this->filenames->generate( $location, $format, $sequence );

			if ( is_wp_error( $filename ) ) {
				return $filename;
			}

			$path = $dir['path'] . $filename;

			if ( ! $this->file_exists( $path ) ) {
				break;
			}
		}

		if ( $this->file_exists( $path ) ) {
			return new \WP_Error( 'invalid_filename', __( 'A unique price-list filename could not be generated.', 'sidrena-cijena' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes the generated price list into the plugin's uploads directory.
		if ( false === file_put_contents( $path, (string) $content ) ) {
			return new \WP_Error( 'publication_write_failed', __( 'The price list could not be written.', 'sidrena-cijena' ) );
		}

		$published = Dates::now_utc();
		$days      = max( 30, (int) Settings::get( 'retention_days', 30 ) );
		$expires   = gmdate( 'Y-m-d H:i:s', strtotime( $published . ' +' . $days . ' days' ) );

		$id = $this->publications->insert(
			array(
				'location_id'         => $location_id,
				'format'              => $format,
				'filename'            => $filename,
				'path'                => $path,
				'url'                 => $dir['url'] . $filename,
				'checksum'            => (string) hash_file( 'sha256', $path ),
				'content_fingerprint' => $fingerprint,
				'file_size'           => (int) filesize( $path ),
				'rule_version'        => isset( $meta['rule_version'] ) ? (string) $meta['rule_version'] : '',
				'status'              => 'published',
				'sequence_no'         => (int) $sequence,
				'item_count'          => isset( $meta['item_count'] ) ? (int) $meta['item_count'] : 0,
				'validation_summary'  => isset( $meta['validation_summary'] ) ? $meta['validation_summary'] : null,
				'created_by'          => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : null,
				'published_at'        => $published,
				'expires_at'          => $expires,
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'publication_archive_failed', __( 'The publication archive row could not be stored.', 'sidrena-cijena' ) );
		}

		$this->publications->supersede( $location_id, $format, $id );

		$publication = $this->publications->get( $id );

		/**
		 * Fires after a price list has been published.
		 *
		 * @param int    $id          Publication ID.
		 * @param int    $location_id Location ID.
		 * @param string $format      Format.
		 */
		do_action( 'sidrena_cijena_publication_published', $id, $location_id, $format );

		return array(
			'publication' => $publication,
			'skipped'     => false,
			'filename'    => $filename,
		);
	}

	/**
	 * Uploads directory for a location.
	 *
	 * @param array<mixed> $location Location row.
	 * @return array{path:string,url:string}|null
	 */
	public function directory( array $location ) {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		$slug = $this->slug( $location );
		$path = trailingslashit( $uploads['basedir'] ) . self::DIR . '/' . $slug . '/';
		$url  = trailingslashit( $uploads['baseurl'] ) . self::DIR . '/' . $slug . '/';

		if ( ! wp_mkdir_p( $path ) ) {
			return null;
		}

		$guard = $path . 'index.html';

		if ( ! $this->file_exists( $guard ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Directory-listing guard file.
			file_put_contents( $guard, 'Silence is golden.' );
		}

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}

	/**
	 * Per-location storage slug.
	 *
	 * @param array<mixed> $location Location row.
	 * @return string
	 */
	protected function slug( array $location ) {
		$identifier = isset( $location['identifier'] ) ? (string) $location['identifier'] : '';
		$slug       = sanitize_title( $identifier );

		return '' === $slug ? 'location-' . ( isset( $location['id'] ) ? (int) $location['id'] : 0 ) : $slug;
	}

	/**
	 * Whether a file exists.
	 *
	 * @param mixed $path Path.
	 * @return bool
	 */
	protected function file_exists( $path ) {
		return is_string( $path ) && '' !== $path && file_exists( $path );
	}
}
