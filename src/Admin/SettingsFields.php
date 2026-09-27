<?php
/**
 * Reusable Settings API field renderers.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders option inputs bound to a settings array.
 */
final class SettingsFields {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option;

	/**
	 * Constructor.
	 *
	 * @param string $option Option name.
	 */
	public function __construct( $option ) {
		$this->option = $option;
	}

	/**
	 * Input name for a key.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function name( $key ) {
		return $this->option . '[' . $key . ']';
	}

	/**
	 * Text input.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Current value.
	 * @param array  $args  `class`, `type`, `description`, `placeholder`.
	 * @return void
	 */
	public function text( $key, $value, array $args = array() ) {
		printf(
			'<input type="%1$s" class="%2$s" id="%3$s" name="%4$s" value="%5$s" placeholder="%6$s" />',
			esc_attr( isset( $args['type'] ) ? $args['type'] : 'text' ),
			esc_attr( isset( $args['class'] ) ? $args['class'] : 'regular-text' ),
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			esc_attr( (string) $value ),
			esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
		);

		$this->description( $args );
	}

	/**
	 * Number input.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Current value.
	 * @param array  $args  `min`, `max`, `step`, `description`.
	 * @return void
	 */
	public function number( $key, $value, array $args = array() ) {
		printf(
			'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" />',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			esc_attr( (string) $value ),
			esc_attr( isset( $args['min'] ) ? (string) $args['min'] : '' ),
			esc_attr( isset( $args['max'] ) ? (string) $args['max'] : '' ),
			esc_attr( isset( $args['step'] ) ? (string) $args['step'] : '1' )
		);

		$this->description( $args );
	}

	/**
	 * Checkbox input.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Current value.
	 * @param array  $args  `description`.
	 * @return void
	 */
	public function checkbox( $key, $value, array $args = array() ) {
		printf(
			'<input type="hidden" name="%1$s" value="0" />'
			. '<label for="%2$s"><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s /> %4$s</label>',
			esc_attr( $this->name( $key ) ),
			esc_attr( $key ),
			checked( (bool) $value, true, false ),
			esc_html( isset( $args['label'] ) ? $args['label'] : '' )
		);

		$this->description( $args );
	}

	/**
	 * Select input.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $value   Current value.
	 * @param array  $choices Value => label pairs.
	 * @param array  $args    `description`.
	 * @return void
	 */
	public function select( $key, $value, array $choices, array $args = array() ) {
		printf( '<select id="%1$s" name="%2$s">', esc_attr( $key ), esc_attr( $this->name( $key ) ) );

		foreach ( $choices as $choice_value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $choice_value ),
				selected( (string) $value, (string) $choice_value, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select>';

		$this->description( $args );
	}

	/**
	 * Textarea input.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Current value.
	 * @param array  $args  `description`, `rows`.
	 * @return void
	 */
	public function textarea( $key, $value, array $args = array() ) {
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$s" class="large-text">%4$s</textarea>',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			esc_attr( isset( $args['rows'] ) ? (string) $args['rows'] : '3' ),
			esc_textarea( (string) $value )
		);

		$this->description( $args );
	}

	/**
	 * Checkbox group bound to an array value.
	 *
	 * @param string $key     Setting key.
	 * @param array  $value   Current values.
	 * @param array  $choices Value => label pairs.
	 * @param array  $args    `description`.
	 * @return void
	 */
	public function checkboxes( $key, $value, array $choices, array $args = array() ) {
		$value = array_map( 'strval', (array) $value );

		$legend = isset( $args['legend'] ) ? (string) $args['legend'] : ( isset( $args['label'] ) ? (string) $args['label'] : '' );

		echo '<fieldset>';
		if ( '' !== $legend ) {
			echo '<legend class="screen-reader-text"><span>' . esc_html( $legend ) . '</span></legend>';
		}

		foreach ( $choices as $choice_value => $label ) {
			printf(
				'<label class="sidrena-checkbox-label"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $this->name( $key ) ),
				esc_attr( (string) $choice_value ),
				checked( in_array( (string) $choice_value, $value, true ), true, false ),
				esc_html( (string) $label )
			);
		}

		echo '</fieldset>';

		$this->description( $args );
	}

	/**
	 * Render an optional description.
	 *
	 * @param array<mixed> $args Arguments.
	 * @return void
	 */
	private function description( array $args ) {
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}
}
