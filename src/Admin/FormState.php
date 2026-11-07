<?php
/**
 * Submitted form state kept across redirects.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Transient-backed store for submitted values and per-field errors.
 */
class FormState {

	const PREFIX = 'twbce_cijena_form_';

	const TTL = 300;

	/**
	 * Store what the user submitted together with the field errors found.
	 *
	 * @param string                $form_id Form identifier.
	 * @param array<string, mixed>  $values  Submitted values keyed by field name.
	 * @param array<string, string> $errors Field errors keyed by field name.
	 * @return void
	 */
	public static function store( $form_id, array $values, array $errors = array() ) {
		set_transient(
			self::key( $form_id ),
			array(
				'values' => $values,
				'errors' => $errors,
			),
			self::TTL
		);
	}

	/**
	 * Fetch and clear the stored state of a form.
	 *
	 * @param string $form_id Form identifier.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public static function pull( $form_id ) {
		$key   = self::key( $form_id );
		$state = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $state ) ) {
			return array(
				'values' => array(),
				'errors' => array(),
			);
		}

		return array(
			'values' => isset( $state['values'] ) && is_array( $state['values'] ) ? $state['values'] : array(),
			'errors' => isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array(),
		);
	}

	/**
	 * Transient key for a form of the current user.
	 *
	 * @param string $form_id Form identifier.
	 * @return string
	 */
	private static function key( $form_id ) {
		return self::PREFIX . sanitize_key( (string) $form_id ) . '_' . get_current_user_id();
	}

	/**
	 * Aria attributes marking a control as invalid.
	 *
	 * @param array<string, string> $errors Field errors keyed by field name.
	 * @param string                $field  Field name.
	 * @param string                $id     Control ID.
	 * @return string
	 */
	public static function error_attributes( array $errors, $field, $id ) {
		if ( ! isset( $errors[ $field ] ) ) {
			return '';
		}

		return ' aria-invalid="true" aria-describedby="' . esc_attr( $id . '-error' ) . '"';
	}

	/**
	 * Print validation attributes, escaping their values at the output boundary.
	 *
	 * @param array<string,string> $errors Field errors.
	 * @param string               $field Field name.
	 * @param string               $id Control ID.
	 * @return void
	 */
	public static function render_error_attributes( array $errors, $field, $id ) {
		if ( isset( $errors[ $field ] ) ) {
			echo ' aria-invalid="true" aria-describedby="' . esc_attr( $id . '-error' ) . '"';
		}
	}

	/**
	 * Render the error message of one field.
	 *
	 * @param array<string, string> $errors Field errors keyed by field name.
	 * @param string                $field  Field name.
	 * @param string                $id     Control ID.
	 * @return void
	 */
	public static function render_error( array $errors, $field, $id ) {
		if ( ! isset( $errors[ $field ] ) ) {
			return;
		}

		echo '<p class="description sidrena-cijena-field-error" id="' . esc_attr( $id . '-error' ) . '">' . esc_html( (string) $errors[ $field ] ) . '</p>';
	}
}
