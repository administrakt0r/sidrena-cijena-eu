<?php
/**
 * Sales locations screen.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Database\LocationRepository;
use SidrenaCijena\Export\FilenameGenerator;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;
use SidrenaCijena\Support\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * Manages sales locations and their price-list settings.
 */
class LocationsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'sidrena-cijena-locations';

	/**
	 * Save action.
	 *
	 * @var string
	 */
	const ACTION_SAVE = 'sidrena_cijena_save_location';

	/**
	 * Delete action.
	 *
	 * @var string
	 */
	const ACTION_DELETE = 'sidrena_cijena_delete_location';

	/**
	 * Location types offered in the form.
	 *
	 * @return array<string,string>
	 */
	protected static function types() {
		$types = array();

		foreach ( LocationRepository::TYPES as $type ) {
			$types[ $type ] = Labels::location_type( $type );
		}

		return $types;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$edit_id = isset( $_GET['location'] ) ? absint( wp_unslash( $_GET['location'] ) ) : 0;
		$edit    = $edit_id > 0 ? Plugin::instance()->locations()->get( $edit_id ) : null;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Sales locations', 'sidrena-cijena-eu' ) . '</h1>';

		self::notice();

		echo '<p>' . esc_html__( 'All locations share one price catalog. Per-location settings only control which products are listed and how the file is named.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<p>' . esc_html__( 'Filenames follow the pattern:', 'sidrena-cijena-eu' ) . ' <code>' . esc_html( FilenameGenerator::pattern() ) . '</code></p>';

		self::render_form( $edit );
		self::render_list();

		echo '</div>';
	}

	/**
	 * Render the add/edit form.
	 *
	 * @param array<mixed>|null $location Location row when editing.
	 * @return void
	 */
	protected static function render_form( $location ) {
		$state        = FormState::pull( 'location-form' );
		$values       = $state['values'];
		$errors       = $state['errors'];
		$config       = $location && is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();
		$applies      = $location && is_array( $location['applies_to'] ) ? $location['applies_to'] : array();
		$formats      = isset( $values['formats'] ) ? (array) $values['formats'] : ( isset( $config['formats'] ) ? (array) $config['formats'] : array( 'csv', 'xml' ) );
		$mode         = isset( $values['applies_mode'] ) ? (string) $values['applies_mode'] : ( isset( $applies['mode'] ) ? (string) $applies['mode'] : 'all' );
		$cats         = isset( $values['applies_categories'] ) ? (string) $values['applies_categories'] : ( isset( $applies['categories'] ) ? implode( ', ', array_map( 'strval', (array) $applies['categories'] ) ) : '' );
		$edit_id      = $location ? (int) $location['id'] : 0;
		$heading      = $edit_id > 0 ? __( 'Edit location', 'sidrena-cijena-eu' ) : __( 'Add location', 'sidrena-cijena-eu' );
		$sep          = isset( $values['separator'] ) ? (string) $values['separator'] : ( isset( $config['separator'] ) ? (string) $config['separator'] : '' );
		$case         = isset( $values['case'] ) ? (string) $values['case'] : ( isset( $config['case'] ) ? (string) $config['case'] : '' );
		$name         = isset( $values['name'] ) ? (string) $values['name'] : ( $location ? (string) $location['name'] : '' );
		$identifier   = isset( $values['identifier'] ) ? (string) $values['identifier'] : ( $location ? (string) $location['identifier'] : '' );
		$address      = isset( $values['address'] ) ? (string) $values['address'] : ( $location ? (string) $location['address'] : '' );
		$city         = isset( $values['city'] ) ? (string) $values['city'] : ( $location ? (string) $location['city'] : '' );
		$current_type = isset( $values['type'] ) ? (string) $values['type'] : ( $location ? (string) $location['type'] : 'prodavaonica' );
		$active       = isset( $values['active'] ) ? ! empty( $values['active'] ) : ( $location ? (bool) $location['active'] : true );

		echo '<h2>' . esc_html( $heading ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';
		echo '<input type="hidden" name="location_id" value="' . esc_attr( (string) $edit_id ) . '" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="sidrena-location-name">' . esc_html__( 'Name', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="sidrena-location-name" name="name" required value="' . esc_attr( $name ) . '"' . FormState::error_attributes( $errors, 'name', 'sidrena-location-name' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		echo '<p class="description">' . esc_html__( 'A staff-facing name to recognize this shop, for example “Zagreb — Vlaška 68”.', 'sidrena-cijena-eu' ) . '</p>';
		FormState::render_error( $errors, 'name', 'sidrena-location-name' );
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-type">' . esc_html__( 'Facility form', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><select id="sidrena-location-type" name="type">';

		foreach ( self::types() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select><p class="description">' . esc_html__( 'Select the facility form that matches this sales location’s legal/store classification.', 'sidrena-cijena-eu' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-address">' . esc_html__( 'Address', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="sidrena-location-address" name="address" value="' . esc_attr( $address ) . '" placeholder="' . esc_attr__( 'e.g. Vlaška 68', 'sidrena-cijena-eu' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Enter the official street address and house number; this is used in the generated price-list filename.', 'sidrena-cijena-eu' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-city">' . esc_html__( 'City', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="sidrena-location-city" name="city" value="' . esc_attr( $city ) . '" placeholder="' . esc_attr__( 'e.g. Zagreb', 'sidrena-cijena-eu' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Enter the city name as it should appear in the published filename.', 'sidrena-cijena-eu' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-identifier">' . esc_html__( 'Facility identifier', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="sidrena-location-identifier" name="identifier" required value="' . esc_attr( $identifier ) . '" placeholder="' . esc_attr__( 'e.g. P1, W1', 'sidrena-cijena-eu' ) . '"' . FormState::error_attributes( $errors, 'identifier', 'sidrena-location-identifier' ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute markup escaped in FormState::error_attributes().
		echo '<p class="description">' . esc_html__( 'Required internal store/outlet code, unique across locations and used in the filename (e.g. P1, P2, W1).', 'sidrena-cijena-eu' ) . '</p>';
		FormState::render_error( $errors, 'identifier', 'sidrena-location-identifier' );
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Active', 'sidrena-cijena-eu' ) . '</th>';
		echo '<td><label><input type="checkbox" name="active" value="1"' . checked( $active, true, false ) . ' /> ' . esc_html__( 'Generate a price list for this location.', 'sidrena-cijena-eu' ) . '</label></td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-applies">' . esc_html__( 'Applicable products', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><select id="sidrena-location-applies" name="applies_mode">';

		foreach ( array(
			'all'     => __( 'All products and services', 'sidrena-cijena-eu' ),
			'include' => __( 'Only the selected categories', 'sidrena-cijena-eu' ),
			'exclude' => __( 'All except the selected categories', 'sidrena-cijena-eu' ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Form field option, not WP_Query.
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $mode, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose whether this location receives every catalog item, only selected categories, or all except selected categories.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<p><label class="screen-reader-text" for="sidrena-location-categories">' . esc_html__( 'Category slugs', 'sidrena-cijena-eu' ) . '</label>';
		echo '<input type="text" class="regular-text" id="sidrena-location-categories" name="applies_categories" value="' . esc_attr( $cats ) . '" placeholder="' . esc_attr__( 'category-slug, other-slug', 'sidrena-cijena-eu' ) . '" /></p>';
		echo '<p class="description">' . esc_html__( 'Category slugs, comma separated. Leave empty to include everything.', 'sidrena-cijena-eu' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Formats', 'sidrena-cijena-eu' ) . '</th><td>';
		foreach ( array( 'csv', 'xml' ) as $format ) {
			echo '<label class="sidrena-label-inline"><input type="checkbox" name="formats[]" value="' . esc_attr( $format ) . '"' . checked( in_array( $format, $formats, true ), true, false ) . ' /> ' . esc_html( strtoupper( $format ) ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Choose the file formats accepted by this location. The generated files remain available in the publication archive.', 'sidrena-cijena-eu' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-separator">' . esc_html__( 'Filename separator', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><select id="sidrena-location-separator" name="separator">';
		foreach ( array(
			''  => __( 'Use the global setting', 'sidrena-cijena-eu' ),
			'_' => '_',
			'-' => '-',
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $sep, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="sidrena-location-case">' . esc_html__( 'Filename letter case', 'sidrena-cijena-eu' ) . '</label></th>';
		echo '<td><select id="sidrena-location-case" name="case">';
		foreach ( array(
			''      => __( 'Use the global setting', 'sidrena-cijena-eu' ),
			'upper' => __( 'UPPERCASE', 'sidrena-cijena-eu' ),
			'lower' => __( 'lowercase', 'sidrena-cijena-eu' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $case, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '</tbody></table>';

		submit_button( $edit_id > 0 ? __( 'Save location', 'sidrena-cijena-eu' ) : __( 'Add location', 'sidrena-cijena-eu' ) );

		if ( $edit_id > 0 ) {
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Cancel editing', 'sidrena-cijena-eu' ) . '</a></p>';
		}

		echo '</form>';
	}

	/**
	 * Render the location list.
	 *
	 * @return void
	 */
	protected static function render_list() {
		$locations    = Plugin::instance()->locations()->all();
		$publications = Plugin::instance()->publications();

		echo '<h2>' . esc_html__( 'Locations', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Sales locations', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr>';
		foreach ( array(
			__( 'Name', 'sidrena-cijena-eu' ),
			__( 'Facility form', 'sidrena-cijena-eu' ),
			__( 'Address', 'sidrena-cijena-eu' ),
			__( 'Identifier', 'sidrena-cijena-eu' ),
			__( 'Sequence', 'sidrena-cijena-eu' ),
			__( 'Active', 'sidrena-cijena-eu' ),
			__( 'Formats', 'sidrena-cijena-eu' ),
			__( 'Last published', 'sidrena-cijena-eu' ),
			__( 'Actions', 'sidrena-cijena-eu' ),
		) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $locations ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No sales locations have been configured.', 'sidrena-cijena-eu' ) . '</td></tr>';
		}

		foreach ( $locations as $location ) {
			$id       = (int) $location['id'];
			$config   = is_array( $location['price_list_config'] ) ? $location['price_list_config'] : array();
			$formats  = isset( $config['formats'] ) ? array_map( 'strval', (array) $config['formats'] ) : array( 'csv', 'xml' );
			$last     = $publications->last_successful( $id );
			$retained = $publications->for_location( $id, 1 );

			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) $location['name'] ) . '</strong></td>';
			echo '<td>' . esc_html( Labels::location_type( (string) $location['type'] ) ) . '</td>';
			echo '<td>' . esc_html( trim( (string) $location['address'] . ' ' . (string) $location['city'] ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) $location['identifier'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) (int) $location['sequence_no'] ) . '</td>';
			echo '<td>' . esc_html( $location['active'] ? __( 'Yes', 'sidrena-cijena-eu' ) : __( 'No', 'sidrena-cijena-eu' ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', array_map( 'strtoupper', $formats ) ) ) . '</td>';
			echo '<td>' . esc_html( $last && ! empty( $last['published_at'] ) ? Dates::format_datetime_display( (string) $last['published_at'] ) : '—' ) . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( self::url( array( 'location' => $id ) ) ) . '">' . esc_html__( 'Edit', 'sidrena-cijena-eu' ) . '</a> | ';

			if ( ! empty( $retained ) ) {
				echo '<a href="' . esc_url( self::delete_url( $id ) ) . '" data-sidrena-confirm="' . esc_attr__( 'This location has published price lists. It will be deactivated instead of deleted. Continue?', 'sidrena-cijena-eu' ) . '">' . esc_html__( 'Deactivate', 'sidrena-cijena-eu' ) . '</a>';
			} else {
				echo '<a href="' . esc_url( self::delete_url( $id ) ) . '" data-sidrena-confirm="' . esc_attr__( 'Delete this location?', 'sidrena-cijena-eu' ) . '">' . esc_html__( 'Delete', 'sidrena-cijena-eu' ) . '</a>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Save a location.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		check_admin_referer( self::ACTION_SAVE );

		$locations  = Plugin::instance()->locations();
		$id         = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';

		$applies_categories = isset( $_POST['applies_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['applies_categories'] ) ) : '';
		$split_cats         = preg_split( '/[\s,;]+/', $applies_categories );
		$categories         = is_array( $split_cats )
			? array_values( array_filter( array_map( 'sanitize_title', $split_cats ) ) )
			: array();

		$formats = isset( $_POST['formats'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['formats'] ) ) : array();
		$formats = array_values( array_intersect( array( 'csv', 'xml' ), $formats ) );

		$separator    = isset( $_POST['separator'] ) ? sanitize_text_field( wp_unslash( $_POST['separator'] ) ) : '';
		$case         = isset( $_POST['case'] ) ? sanitize_text_field( wp_unslash( $_POST['case'] ) ) : '';
		$type         = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'prodavaonica';
		$address      = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$active       = ! empty( $_POST['active'] ) ? 1 : 0;
		$applies_mode = isset( $_POST['applies_mode'] ) ? sanitize_key( wp_unslash( $_POST['applies_mode'] ) ) : 'all';

		$values = array(
			'name'               => $name,
			'type'               => $type,
			'address'            => $address,
			'city'               => $city,
			'identifier'         => $identifier,
			'active'             => $active,
			'applies_mode'       => $applies_mode,
			'applies_categories' => $applies_categories,
			'formats'            => $formats,
			'separator'          => $separator,
			'case'               => $case,
		);

		if ( '' === $name || '' === $identifier ) {
			$errors = array();

			if ( '' === $name ) {
				$errors['name'] = __( 'Enter a name.', 'sidrena-cijena-eu' );
			}

			if ( '' === $identifier ) {
				$errors['identifier'] = __( 'Enter a facility identifier.', 'sidrena-cijena-eu' );
			}

			self::redirect_error( __( 'The name and the facility identifier are required.', 'sidrena-cijena-eu' ), $values, $errors );
		}

		$existing = $locations->find_by_identifier( $identifier );

		if ( $existing && (int) $existing['id'] !== $id ) {
			self::redirect_error(
				__( 'Another location already uses this facility identifier.', 'sidrena-cijena-eu' ),
				$values,
				array( 'identifier' => __( 'Another location already uses this facility identifier.', 'sidrena-cijena-eu' ) )
			);
		}

		$data = array(
			'name'              => $name,
			'type'              => $type,
			'address'           => $address,
			'city'              => $city,
			'identifier'        => $identifier,
			'active'            => $active,
			'applies_to'        => array(
				'mode'       => $applies_mode,
				'categories' => $categories,
			),
			'price_list_config' => array(
				'formats'   => $formats,
				'separator' => $separator,
				'case'      => $case,
			),
		);

		$result = $id > 0 ? $locations->update( $id, $data ) : $locations->insert( $data );

		if ( false === $result || 0 === $result ) {
			self::redirect_error( __( 'The location could not be saved.', 'sidrena-cijena-eu' ), $values );
		}

		Plugin::instance()->flush_caches();

		self::redirect( 'success', __( 'Location saved.', 'sidrena-cijena-eu' ) );
	}

	/**
	 * Delete (or deactivate) a location.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sidrena-cijena-eu' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Required to build the per-location nonce checked below.
		$id = isset( $_GET['location'] ) ? absint( wp_unslash( $_GET['location'] ) ) : 0;

		check_admin_referer( self::ACTION_DELETE . '_' . $id );

		$locations = Plugin::instance()->locations();
		$location  = $locations->get( $id );

		if ( ! $location ) {
			self::redirect( 'error', __( 'The location could not be found.', 'sidrena-cijena-eu' ) );
		}

		$retained = Plugin::instance()->publications()->for_location( $id, 1 );

		if ( ! empty( $retained ) ) {
			$locations->update( $id, array( 'active' => 0 ) );
			Plugin::instance()->flush_caches();
			self::redirect( 'success', __( 'The location has published price lists, so it was deactivated instead of deleted.', 'sidrena-cijena-eu' ) );
		}

		$locations->delete( $id );

		Plugin::instance()->flush_caches();

		self::redirect( 'success', __( 'Location deleted.', 'sidrena-cijena-eu' ) );
	}

	/**
	 * Screen URL.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return string
	 */
	protected static function url( array $args = array() ) {
		return AdminMenu::url( self::SLUG, $args );
	}

	/**
	 * Delete URL with nonce.
	 *
	 * @param int $id Location ID.
	 * @return string
	 */
	protected static function delete_url( $id ) {
		$url = add_query_arg(
			array(
				'action'   => self::ACTION_DELETE,
				'location' => (int) $id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, self::ACTION_DELETE . '_' . (int) $id );
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $type    `success` or `error`.
	 * @param string $message Notice text.
	 * @return void
	 */
	protected static function redirect( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'                       => self::SLUG,
				'sidrena_cijena_notice'      => $type,
				'sidrena_cijena_notice_text' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect back to the form keeping the submitted values and field errors.
	 *
	 * @param string                $message Notice text.
	 * @param array<string, mixed>  $values  Submitted values.
	 * @param array<string, string> $errors  Field errors.
	 * @return void
	 */
	private static function redirect_error( $message, array $values, array $errors = array() ) {
		FormState::store( 'location-form', $values, $errors );

		self::redirect( 'error', $message );
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	protected static function notice() {
		Notice::from_request();
	}
}
