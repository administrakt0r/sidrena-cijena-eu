<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Unit tests exercise pure domain logic without loading WordPress. Only the
 * small surface the domain classes touch is stubbed here; behaviour that
 * depends on WordPress itself is covered by the integration suite.
 *
 * @package SidrenaCijena\Tests
 */

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'WEEK_IN_SECONDS', 604800 );
}

$GLOBALS['sidrena_test_options']    = array();
$GLOBALS['sidrena_test_transients'] = array();
$GLOBALS['sidrena_test_scheduled']  = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Echo stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function _e( $text, $domain = 'default' ) {
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Context translation stub.
	 *
	 * @param string $text    Text.
	 * @param string $context Context.
	 * @param string $domain  Text domain.
	 * @return string
	 */
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Plural stub.
	 *
	 * @param string $single Single form.
	 * @param string $plural Plural form.
	 * @param int    $number Number.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Escaped translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Escaped attribute translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Escaped echo stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Escaped attribute echo stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * HTML escape stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Attribute escape stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * URL escape stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * KSES stub.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	function wp_kses_post( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Text sanitization stub.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Key sanitization stub.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Slug sanitization stub.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9\-]+/', '-', $title );
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Unslash stub.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Absolute integer stub.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode stub.
	 *
	 * @param mixed $data Data.
	 * @param int   $flags Flags.
	 * @param int   $depth Depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
		return json_encode( $data, $flags, $depth );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Parse args stub.
	 *
	 * @param mixed $args     Args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter stub.
	 *
	 * @param string $tag   Tag.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'apply_filters_ref_array' ) ) {
	/**
	 * Ref-array filter stub.
	 *
	 * @param string $tag  Tag.
	 * @param array  $args Args.
	 * @return mixed
	 */
	function apply_filters_ref_array( $tag, $args ) {
		return isset( $args[0] ) ? $args[0] : null;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter registration.
	 *
	 * @return bool
	 */
	function add_filter() {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * No-op action registration.
	 *
	 * @return bool
	 */
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * No-op action removal.
	 *
	 * @return bool
	 */
	function remove_action() {
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * No-op filter removal.
	 *
	 * @return bool
	 */
	function remove_filter() {
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * No-op action dispatch.
	 *
	 * @return void
	 */
	function do_action() {
	}
}

if ( ! function_exists( 'has_action' ) ) {
	/**
	 * Has action stub.
	 *
	 * @return bool
	 */
	function has_action() {
		return false;
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	/**
	 * Doing action stub.
	 *
	 * @return bool
	 */
	function doing_action() {
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option read stub.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['sidrena_test_options'] )
			? $GLOBALS['sidrena_test_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Option write stub.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		$GLOBALS['sidrena_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Option add stub.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function add_option( $option, $value ) {
		if ( array_key_exists( $option, $GLOBALS['sidrena_test_options'] ) ) {
			return false;
		}

		$GLOBALS['sidrena_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Option delete stub.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		unset( $GLOBALS['sidrena_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Transient read stub.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['sidrena_test_transients'][ $key ] ) ? $GLOBALS['sidrena_test_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Transient write stub.
	 *
	 * @param string $key        Key.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Expiration.
	 * @return bool
	 */
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['sidrena_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Transient delete stub.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['sidrena_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * Site timezone stub.
	 *
	 * @return DateTimeZone
	 */
	function wp_timezone() {
		return new DateTimeZone( 'Europe/Zagreb' );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Current time stub.
	 *
	 * @param string $type Type.
	 * @return int|string
	 */
	function current_time( $type = 'timestamp' ) {
		if ( 'timestamp' === $type ) {
			return time();
		}

		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		return gmdate( $type );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Date formatting stub.
	 *
	 * @param string          $format    Format.
	 * @param int|null        $timestamp Timestamp.
	 * @param DateTimeZone|null $timezone Timezone.
	 * @return string
	 */
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		$timestamp = null === $timestamp ? time() : $timestamp;
		$timezone  = null === $timezone ? wp_timezone() : $timezone;

		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
	}
}

if ( ! function_exists( 'mysql2date' ) ) {
	/**
	 * MySQL date parse stub.
	 *
	 * @param string $format Format.
	 * @param string $date   Date.
	 * @return string|false
	 */
	function mysql2date( $format, $date ) {
		$timestamp = strtotime( $date );
		return false === $timestamp ? false : date( $format, $timestamp );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * Upload directory stub.
	 *
	 * @return array
	 */
	function wp_upload_dir() {
		$base = sys_get_temp_dir() . '/sidrena-cijena-uploads';

		return array(
			'path'    => $base,
			'url'     => 'http://example.test/wp-content/uploads',
			'subdir'  => '',
			'basedir' => $base,
			'baseurl' => 'http://example.test/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Recursive mkdir stub.
	 *
	 * @param string $target Directory.
	 * @return bool
	 */
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * File delete stub.
	 *
	 * @param string $file File.
	 * @return bool
	 */
	function wp_delete_file( $file ) {
		return is_file( $file ) ? unlink( $file ) : false;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	/**
	 * Path normalization stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Trailing slash stub.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Untrailing slash stub.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip tags stub.
	 *
	 * @param string $string String.
	 * @return string
	 */
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * Locale stub.
	 *
	 * @return string
	 */
	function get_locale() {
		return 'en_US';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Admin context stub.
	 *
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * WP_Error check stub.
	 *
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in for unit tests.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		protected $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		protected $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		protected $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Error data.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Minimal $wpdb stand-in for unit tests.
	 *
	 * Records nothing; repositories are covered by the integration suite.
	 */
	class wpdb {

		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Last insert ID.
		 *
		 * @var int
		 */
		public $insert_id = 0;

		/**
		 * Charset collate.
		 *
		 * @return string
		 */
		public function get_charset_collate() {
			return '';
		}

		/**
		 * Prepare a query string.
		 *
		 * @param string $query Query with placeholders.
		 * @param mixed  ...$args Values (or one array of values).
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}

			$args = array_map(
				function ( $value ) {
					if ( is_int( $value ) || is_float( $value ) ) {
						return $value;
					}

					return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
				},
				$args
			);

			$result = @vsprintf( $query, $args ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			return false === $result ? $query : $result;
		}

		/**
		 * Insert stub.
		 *
		 * @param string     $table  Table.
		 * @param array      $data   Data.
		 * @param array|null $format Formats.
		 * @return int
		 */
		public function insert( $table, $data, $format = null ) {
			unset( $table, $data, $format );

			++$this->insert_id;

			return 1;
		}

		/**
		 * Update stub.
		 *
		 * @return int
		 */
		public function update() {
			return 1;
		}

		/**
		 * Delete stub.
		 *
		 * @return int
		 */
		public function delete() {
			return 1;
		}

		/**
		 * Query stub.
		 *
		 * @return int
		 */
		public function query() {
			return 0;
		}

		/**
		 * get_var stub.
		 *
		 * @return mixed
		 */
		public function get_var() {
			return null;
		}

		/**
		 * get_row stub.
		 *
		 * @return mixed
		 */
		public function get_row() {
			return null;
		}

		/**
		 * get_results stub.
		 *
		 * @return array
		 */
		public function get_results() {
			return array();
		}

		/**
		 * get_col stub.
		 *
		 * @return array
		 */
		public function get_col() {
			return array();
		}
	}

	$GLOBALS['wpdb'] = new wpdb();
}
