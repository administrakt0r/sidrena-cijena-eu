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

$GLOBALS['sidrena_test_options']      = array();
$GLOBALS['sidrena_test_transients']   = array();
$GLOBALS['sidrena_test_scheduled']    = array();
$GLOBALS['sidrena_test_textdomains']  = array();
$GLOBALS['sidrena_test_translations'] = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		if ( ! empty( $GLOBALS['sidrena_test_translations'][ $domain ][ $text ] ) ) {
			return $GLOBALS['sidrena_test_translations'][ $domain ][ $text ];
		}

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

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * URL escape raw stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	/**
	 * JavaScript escape stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_js( $text ) {
		return addcslashes( (string) $text, "\\'\"&\n\r<>" );
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
		$args = func_get_args();
		array_shift( $args );

		if ( empty( $GLOBALS['wp_filter'][ $tag ] ) || ! is_array( $GLOBALS['wp_filter'][ $tag ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['wp_filter'][ $tag ] );

		foreach ( $GLOBALS['wp_filter'][ $tag ] as $priority_callbacks ) {
			foreach ( $priority_callbacks as $entry ) {
				$callback = $entry['function'];
				if ( is_callable( $callback ) ) {
					$call_args = array_slice( $args, 0, $entry['accepted_args'] );
					$value     = call_user_func_array( $callback, $call_args );
					$args[0]   = $value;
				}
			}
		}

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
	 * Register a filter hook.
	 *
	 * @param string   $tag             Tag name.
	 * @param callable $function_to_add Callback.
	 * @param int      $priority        Priority.
	 * @param int      $accepted_args   Accepted arguments.
	 * @return true
	 */
	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( $GLOBALS['wp_filter'] ) || ! is_array( $GLOBALS['wp_filter'] ) ) {
			$GLOBALS['wp_filter'] = array();
		}

		$GLOBALS['wp_filter'][ $tag ][ (int) $priority ][] = array(
			'function'      => $function_to_add,
			'accepted_args' => (int) $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Action registration.
	 *
	 * @param string   $tag             Tag name.
	 * @param callable $function_to_add Callback.
	 * @param int      $priority        Priority.
	 * @param int      $accepted_args   Accepted arguments.
	 * @return true
	 */
	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $tag, $function_to_add, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Register activation hook stub.
	 *
	 * @param string   $file     File.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function register_activation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Register deactivation hook stub.
	 *
	 * @param string   $file     File.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function register_deactivation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * Callback returning true.
	 *
	 * @return true
	 */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * Callback returning false.
	 *
	 * @return false
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( '__return_null' ) ) {
	/**
	 * Callback returning null.
	 *
	 * @return null
	 */
	function __return_null() {
		return null;
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
	 * Action dispatch.
	 *
	 * @param string $tag Action tag.
	 * @return void
	 */
	function do_action( $tag ) {
		$args = func_get_args();
		array_shift( $args );

		if ( empty( $GLOBALS['wp_filter'][ $tag ] ) || ! is_array( $GLOBALS['wp_filter'][ $tag ] ) ) {
			return;
		}

		ksort( $GLOBALS['wp_filter'][ $tag ] );

		foreach ( $GLOBALS['wp_filter'][ $tag ] as $priority_callbacks ) {
			foreach ( $priority_callbacks as $entry ) {
				$callback = $entry['function'];
				if ( is_callable( $callback ) ) {
					$call_args = array_slice( $args, 0, $entry['accepted_args'] );
					call_user_func_array( $callback, $call_args );
				}
			}
		}
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
		$options = isset( $GLOBALS['sidrena_test_options'] ) && is_array( $GLOBALS['sidrena_test_options'] )
			? $GLOBALS['sidrena_test_options']
			: array();

		return array_key_exists( $option, $options )
			? $options[ $option ]
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
		if ( isset( $GLOBALS['sidrena_test_option_sanitizers'][ $option ] ) ) {
			$value = call_user_func( $GLOBALS['sidrena_test_option_sanitizers'][ $option ], $value );
		}

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

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability stub: tests run as an administrator unless overridden.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		if ( array_key_exists( 'sidrena_test_can', $GLOBALS ) && ! $GLOBALS['sidrena_test_can'] ) {
			return false;
		}

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
		if ( isset( $GLOBALS['sidrena_test_timezone'] ) && $GLOBALS['sidrena_test_timezone'] instanceof DateTimeZone ) {
			return $GLOBALS['sidrena_test_timezone'];
		}

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
	 * @param string            $format    Format.
	 * @param int|null          $timestamp Timestamp.
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

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Post object stub.
	 */
	class WP_Post {

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Post status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';

		/**
		 * Post title.
		 *
		 * @var string
		 */
		public $post_title = '';

		/**
		 * Constructor.
		 *
		 * @param array<string,mixed>|object|null $data Initial data.
		 */
		public function __construct( $data = null ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				foreach ( (array) $data as $key => $val ) {
					$this->{$key} = $val;
				}
			}
		}
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Retrieve post data given a post ID or post object.
	 *
	 * @param int|WP_Post|null $post Post ID or post object.
	 * @return WP_Post|null
	 */
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}

		$id = is_object( $post ) ? (int) $post->ID : (int) $post;

		if ( isset( $GLOBALS['sidrena_test_posts'][ $id ] ) ) {
			$data = $GLOBALS['sidrena_test_posts'][ $id ];

			return $data instanceof WP_Post ? $data : new WP_Post( $data );
		}

		if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && (int) $GLOBALS['post']->ID === $id ) {
			$p = $GLOBALS['post'];

			return $p instanceof WP_Post ? $p : new WP_Post( $p );
		}

		return $id > 0 ? new WP_Post( array( 'ID' => $id, 'post_type' => 'product' ) ) : null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Post query stub.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	function get_posts( $args = array() ) {
		if ( isset( $GLOBALS['sidrena_test_posts'] ) && is_array( $GLOBALS['sidrena_test_posts'] ) ) {
			$posts       = array();
			$post_type   = isset( $args['post_type'] ) ? $args['post_type'] : '';
			$post_status = isset( $args['post_status'] ) ? $args['post_status'] : '';
			$post_in     = isset( $args['post__in'] ) ? (array) $args['post__in'] : array();
			$post_not_in = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();

			foreach ( $GLOBALS['sidrena_test_posts'] as $id => $data ) {
				$p = $data instanceof WP_Post ? $data : new WP_Post( $data );
				if ( $post_type && $p->post_type !== $post_type ) {
					continue;
				}
				if ( $post_status && $p->post_status !== $post_status ) {
					continue;
				}
				if ( ! empty( $post_in ) && ! in_array( (int) $p->ID, $post_in, true ) ) {
					continue;
				}
				if ( ! empty( $post_not_in ) && in_array( (int) $p->ID, $post_not_in, true ) ) {
					continue;
				}
				$posts[] = $p;
			}
			return $posts;
		}

		return array();
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Get post meta stub.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;

		if ( isset( $GLOBALS['sidrena_test_post_meta'][ $post_id ][ $key ] ) ) {
			$value = $GLOBALS['sidrena_test_post_meta'][ $post_id ][ $key ];
			return $single ? $value : array( $value );
		}

		if ( isset( $GLOBALS['sidrena_test_wc_products'][ $post_id ]['meta'][ $key ] ) ) {
			$value = $GLOBALS['sidrena_test_wc_products'][ $post_id ]['meta'][ $key ];
			return $single ? $value : array( $value );
		}

		return $single ? '' : array();
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Update post meta stub.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$post_id = (int) $post_id;

		if ( ! isset( $GLOBALS['sidrena_test_post_meta'] ) ) {
			$GLOBALS['sidrena_test_post_meta'] = array();
		}

		if ( ! isset( $GLOBALS['sidrena_test_post_meta'][ $post_id ] ) ) {
			$GLOBALS['sidrena_test_post_meta'][ $post_id ] = array();
		}

		$GLOBALS['sidrena_test_post_meta'][ $post_id ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	/**
	 * Post terms stub.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Arguments.
	 * @return array
	 */
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		$post_id = (int) $post_id;

		if ( isset( $GLOBALS['sidrena_test_post_terms'][ $post_id ][ $taxonomy ] ) ) {
			$terms = $GLOBALS['sidrena_test_post_terms'][ $post_id ][ $taxonomy ];
			if ( isset( $args['fields'] ) && 'slugs' === $args['fields'] ) {
				return array_map( function( $t ) { return is_array( $t ) ? $t['slug'] : (string) $t; }, $terms );
			}
			if ( isset( $args['fields'] ) && 'names' === $args['fields'] ) {
				return array_map( function( $t ) { return is_array( $t ) ? $t['name'] : (string) $t; }, $terms );
			}
			return $terms;
		}

		return array();
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	/**
	 * Set object terms stub.
	 *
	 * @param int          $object_id Object ID.
	 * @param string|array $terms     Terms.
	 * @param string       $taxonomy  Taxonomy.
	 * @return array
	 */
	function wp_set_object_terms( $object_id, $terms, $taxonomy ) {
		$object_id = (int) $object_id;

		if ( ! isset( $GLOBALS['sidrena_test_post_terms'] ) ) {
			$GLOBALS['sidrena_test_post_terms'] = array();
		}

		$GLOBALS['sidrena_test_post_terms'][ $object_id ][ $taxonomy ] = (array) $terms;

		return (array) $terms;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	/**
	 * Delete post stub.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $force_delete Force delete.
	 * @return bool
	 */
	function wp_delete_post( $post_id = 0, $force_delete = false ) {
		unset( $post_id, $force_delete );

		return true;
	}
}

if ( ! function_exists( 'wp_delete_term' ) ) {
	/**
	 * Delete term stub.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Arguments.
	 * @return bool
	 */
	function wp_delete_term( $term_id, $taxonomy, $args = array() ) {
		unset( $term_id, $taxonomy, $args );

		return true;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	/**
	 * Get terms stub.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	function get_terms( $args = array() ) {
		unset( $args );

		if ( isset( $GLOBALS['sidrena_test_taxonomy_terms'] ) ) {
			return $GLOBALS['sidrena_test_taxonomy_terms'];
		}

		return array();
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * WP_Query stub for unit tests.
	 */
	class WP_Query {
		/**
		 * Found posts count.
		 *
		 * @var int
		 */
		public $found_posts = 0;

		/**
		 * Posts array.
		 *
		 * @var array
		 */
		public $posts = array();

		/**
		 * Post count.
		 *
		 * @var int
		 */
		public $post_count = 0;

		/**
		 * Constructor.
		 *
		 * @param array $args Query arguments.
		 */
		public function __construct( $args = array() ) {
			unset( $args );

			if ( isset( $GLOBALS['sidrena_test_wp_query_found_posts'] ) ) {
				$this->found_posts = (int) $GLOBALS['sidrena_test_wp_query_found_posts'];
			} elseif ( isset( $GLOBALS['sidrena_test_posts'] ) && is_array( $GLOBALS['sidrena_test_posts'] ) ) {
				$this->found_posts = count( $GLOBALS['sidrena_test_posts'] );
			}
		}
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * wp_die stub.
	 *
	 * @param string|mixed $message Error message.
	 * @param string|int   $title   Title or HTTP response code.
	 * @param array        $args    Arguments.
	 * @throws \RuntimeException Always thrown for unit test assertions.
	 * @return void
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		$code = is_int( $title ) ? $title : ( is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 500 );
		throw new \RuntimeException( is_scalar( $message ) ? (string) $message : 'wp_die', $code );
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
		return isset( $GLOBALS['sidrena_test_locale'] ) ? (string) $GLOBALS['sidrena_test_locale'] : 'en_US';
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	/**
	 * Determine locale stub.
	 *
	 * @return string
	 */
	function determine_locale() {
		return get_locale();
	}
}

if ( ! function_exists( 'is_textdomain_loaded' ) ) {
	/**
	 * Textdomain loaded stub.
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	function is_textdomain_loaded( $domain ) {
		return ! empty( $GLOBALS['sidrena_test_textdomains'][ $domain ] );
	}
}

if ( ! function_exists( 'load_textdomain' ) ) {
	/**
	 * Load textdomain stub.
	 *
	 * @param string $domain Domain.
	 * @param string $mofile File path.
	 * @return bool
	 */
	function load_textdomain( $domain, $mofile ) {
		$GLOBALS['sidrena_test_textdomains'][ $domain ] = $mofile;

		return true;
	}
}

if ( ! function_exists( 'unload_textdomain' ) ) {
	/**
	 * Unload textdomain stub.
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	function unload_textdomain( $domain ) {
		unset( $GLOBALS['sidrena_test_textdomains'][ $domain ] );
		unset( $GLOBALS['sidrena_test_translations'][ $domain ] );

		return true;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	/**
	 * Load plugin textdomain stub.
	 *
	 * @param string      $domain          Domain.
	 * @param bool        $deprecated      Deprecated.
	 * @param string|bool $plugin_rel_path Plugin relative path.
	 * @return bool
	 */
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		unset( $deprecated );
		$GLOBALS['sidrena_test_textdomains'][ $domain ] = (string) $plugin_rel_path;

		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Admin context stub.
	 *
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['sidrena_test_is_admin'] );
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
		 * Postmeta table name.
		 *
		 * @var string
		 */
		public $postmeta = 'wp_postmeta';

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

			$argument_index = 0;
			$format_args    = array();
			$query          = preg_replace_callback(
				'/%[sdif]/',
				function ( $matches ) use ( &$argument_index, &$args, &$format_args ) {
					$value = isset( $args[ $argument_index ] ) ? $args[ $argument_index ] : '';
					++$argument_index;

					if ( '%i' === $matches[0] ) {
						return '`' . str_replace( '`', '``', (string) $value ) . '`';
					}

					$format_args[] = $value;

					return $matches[0];
				},
				$query
			);

			$args = $format_args;

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
		public function query( $query = '' ) {
			if ( ! isset( $GLOBALS['sidrena_test_db_queries'] ) ) {
				$GLOBALS['sidrena_test_db_queries'] = array();
			}
			$GLOBALS['sidrena_test_db_queries'][] = $query;

			if ( isset( $GLOBALS['sidrena_test_db_query_result'] ) ) {
				return $GLOBALS['sidrena_test_db_query_result'];
			}

			return 0;
		}

		/**
		 * get_var stub.
		 *
		 * @return mixed
		 */
		public function get_var() {
			if ( isset( $GLOBALS['sidrena_test_db_var'] ) ) {
				return $GLOBALS['sidrena_test_db_var'];
			}

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
			if ( isset( $GLOBALS['sidrena_test_db_results'] ) ) {
				return (array) $GLOBALS['sidrena_test_db_results'];
			}

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

if ( ! function_exists( 'wp_cache_get' ) ) {
	/**
	 * Object cache get stub.
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 * @return mixed
	 */
	function wp_cache_get( $key, $group = '' ) {
		if ( wp_using_ext_object_cache() && isset( $GLOBALS['sidrena_test_cache'][ $group ][ $key ] ) ) {
			return $GLOBALS['sidrena_test_cache'][ $group ][ $key ];
		}

		return false;
	}

	/**
	 * Object cache set stub.
	 *
	 * @param string      $key    Cache key.
	 * @param mixed       $data   Data to store.
	 * @param string      $group  Cache group.
	 * @param int         $expire Expiration.
	 * @return bool
	 */
	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		unset( $expire );

		if ( wp_using_ext_object_cache() ) {
			if ( ! isset( $GLOBALS['sidrena_test_cache'][ $group ] ) ) {
				$GLOBALS['sidrena_test_cache'][ $group ] = array();
			}

			$GLOBALS['sidrena_test_cache'][ $group ][ $key ] = $data;
		}

		return true;
	}

	/**
	 * Object cache delete stub.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool
	 */
	function wp_cache_delete( $key, $group = '' ) {
		if ( wp_using_ext_object_cache() ) {
			unset( $GLOBALS['sidrena_test_cache'][ $group ][ $key ] );
		}

		return true;
	}

	/**
	 * Current user ID stub.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return isset( $GLOBALS['sidrena_test_current_user_id'] ) ? (int) $GLOBALS['sidrena_test_current_user_id'] : 0;
	}

	if ( ! function_exists( 'get_userdata' ) ) {
		/**
		 * User data stub.
		 *
		 * @param int $user_id User ID.
		 * @return object|false
		 */
		function get_userdata( $user_id ) {
			$id = (int) $user_id;

			if ( isset( $GLOBALS['sidrena_test_users'][ $id ] ) ) {
				return (object) $GLOBALS['sidrena_test_users'][ $id ];
			}

			if ( $id > 0 ) {
				return (object) array(
					'ID'           => $id,
					'user_login'   => 'user' . $id,
					'display_name' => 'User ' . $id,
				);
			}

			return false;
		}
	}

	/**
	 * Ajax detection stub.
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['sidrena_test_doing_ajax'] );
	}

	/**
	 * Cron detection stub.
	 *
	 * @return bool
	 */
	function wp_doing_cron() {
		return ! empty( $GLOBALS['sidrena_test_doing_cron'] );
	}

	/**
	 * Network admin detection stub.
	 *
	 * @return bool
	 */
	function is_network_admin() {
		return ! empty( $GLOBALS['sidrena_test_is_network_admin'] );
	}

	/**
	 * Checked() helper.
	 *
	 * @param mixed $checked Value.
	 * @param mixed $current Current.
	 * @param bool  $echo    Whether to echo.
	 * @return string
	 */
	function checked( $checked, $current = true, $echo = true ) {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';

		if ( $echo ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}

	/**
	 * Selected() helper.
	 *
	 * @param mixed $selected Value.
	 * @param mixed $current  Current.
	 * @param bool  $echo     Whether to echo.
	 * @return string
	 */
	function selected( $selected, $current = true, $echo = true ) {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';

		if ( $echo ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}

	/**
	 * Shortcode attribute defaults.
	 *
	 * @param array  $pairs     Defaults.
	 * @param array  $atts      Attributes.
	 * @param string $shortcode Shortcode name.
	 * @return array
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		unset( $shortcode );

		$atts = (array) $atts;
		$out  = array();

		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}

		return $out;
	}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP-CLI facade stub used by static analysis and unit tests.
	 */
	class WP_CLI {

		/**
		 * Log a message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function log( $message ) {}

		/**
		 * Log a success message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( $message ) {}

		/**
		 * Log a warning.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function warning( $message ) {}

		/**
		 * Log an error.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function error( $message ) {}

		/**
		 * Print a line.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function line( $message = '' ) {}

		/**
		 * Ask for confirmation.
		 *
		 * @param string $question    Question.
		 * @param array  $assoc_args  Arguments.
		 * @return bool
		 */
		public static function confirm( $question, $assoc_args = array() ) {
			return true;
		}

		/**
		 * Stop execution.
		 *
		 * @param string $message   Message.
		 * @param int    $exit_code Exit code.
		 * @return void
		 */
		public static function halt( $message, $exit_code = 0 ) {}

		/**
		 * Register a command.
		 *
		 * @param string   $name     Command name.
		 * @param callable $callable Callback.
		 * @return void
		 */
		public static function add_command( $name, $callable ) {}
	}

	/**
	 * Base class for WP-CLI commands.
	 */
	class WP_CLI_Command {}
}

if ( ! defined( 'SIDRENA_CIJENA_FILE' ) ) {
	define( 'SIDRENA_CIJENA_FILE', dirname( __DIR__, 2 ) . '/titanworks-bazna-cijena.php' );
}

if ( ! defined( 'SIDRENA_CIJENA_BASENAME' ) ) {
	define( 'SIDRENA_CIJENA_BASENAME', 'titanworks-bazna-cijena/titanworks-bazna-cijena.php' );
}

if ( ! defined( 'SIDRENA_CIJENA_DIR' ) ) {
	define( 'SIDRENA_CIJENA_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'SIDRENA_CIJENA_URL' ) ) {
	define( 'SIDRENA_CIJENA_URL', 'http://example.org/wp-content/plugins/titanworks-bazna-cijena/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

$GLOBALS['sidrena_test_rewrite_rules'] = array();
$GLOBALS['sidrena_test_query_vars']    = array();
$GLOBALS['sidrena_test_shortcodes']    = array();

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	/**
	 * Record an add_rewrite_rule() call.
	 *
	 * @param string $regex Route regex.
	 * @param string $query Query string.
	 * @param string $after Position.
	 * @return void
	 */
	function add_rewrite_rule( $regex, $query, $after = 'top' ) {
		$GLOBALS['sidrena_test_rewrite_rules'][] = array(
			'regex' => $regex,
			'query' => $query,
			'after' => $after,
		);
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * Record a flush_rewrite_rules() call.
	 *
	 * @param bool $hard Whether to rewrite .htaccess.
	 * @return void
	 */
	function flush_rewrite_rules( $hard = true ) {
		unset( $hard );
		if ( ! isset( $GLOBALS['sidrena_test_flushed_rules'] ) ) {
			$GLOBALS['sidrena_test_flushed_rules'] = 0;
		}
		++$GLOBALS['sidrena_test_flushed_rules'];
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	/**
	 * Read a recorded query variable.
	 *
	 * @param string $var     Query var.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function get_query_var( $var, $default = '' ) {
		return isset( $GLOBALS['sidrena_test_query_vars'][ $var ] ) ? $GLOBALS['sidrena_test_query_vars'][ $var ] : $default;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Build a stable site URL.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://shop.example' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse URL stub.
	 *
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Build an admin URL.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://shop.example/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/**
	 * Retrieve the edit post link for post.
	 *
	 * @param int|WP_Post $id      Post ID or post object.
	 * @param string      $context How to write the URL.
	 * @return string|null
	 */
	function get_edit_post_link( $id = 0, $context = 'display' ) {
		unset( $context );
		$post_id = is_object( $id ) ? (int) $id->ID : (int) $id;

		return $post_id > 0 ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : null;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Safe redirect stub.
	 *
	 * @param string $location Location.
	 * @param int    $status   HTTP status code.
	 * @throws \RuntimeException Thrown to halt execution in unit tests like exit.
	 * @return bool
	 */
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['sidrena_test_last_redirect'] = array(
			'location' => $location,
			'status'   => $status,
		);

		throw new \RuntimeException( 'REDIRECT:' . $location, (int) $status );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * Append a nonce to a URL.
	 *
	 * @param string     $actionurl URL.
	 * @param int|string $action    Action.
	 * @param string     $name      Query param.
	 * @return string
	 */
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		unset( $action );
		$sep = false === strpos( $actionurl, '?' ) ? '?' : '&';

		return $actionurl . $sep . $name . '=test-nonce';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Retrieve or display nonce hidden field for forms.
	 *
	 * @param int|string $action  Action name.
	 * @param string     $name    Nonce name.
	 * @param bool       $referer Whether to set referer field.
	 * @param bool       $echo    Whether to echo or return.
	 * @return string
	 */
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		unset( $action, $referer );
		$html = sprintf( '<input type="hidden" name="%s" value="test-nonce" />', esc_attr( $name ) );
		if ( $echo ) {
			echo $html;
		}

		return $html;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Create a cryptographic token tied to an action.
	 *
	 * @param string|int $action Action name.
	 * @return string
	 */
	function wp_create_nonce( $action = -1 ) {
		unset( $action );

		return 'test-nonce';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Verify that a nonce is correct and valid.
	 *
	 * @param string     $nonce  Nonce.
	 * @param string|int $action Action name.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action = -1 ) {
		unset( $action );

		return ! empty( $nonce ) && 'test-nonce' === $nonce ? 1 : false;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Ensure that user was referred from an admin page with a valid nonce.
	 *
	 * @param int|string $action    Action name.
	 * @param string     $query_arg Nonce argument.
	 * @return int|false
	 */
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$nonce = '';
		if ( isset( $_POST[ $query_arg ] ) ) {
			$nonce = (string) $_POST[ $query_arg ];
		} elseif ( isset( $_GET[ $query_arg ] ) ) {
			$nonce = (string) $_GET[ $query_arg ];
		} elseif ( isset( $_REQUEST[ $query_arg ] ) ) {
			$nonce = (string) $_REQUEST[ $query_arg ];
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( 'The link you followed has expired.', 403 );
		}

		return 1;
	}
}

if ( ! function_exists( 'sidrena_test_build_query' ) ) {
	/**
	 * Build a query string without encoding values, matching WordPress' `build_query()`.
	 *
	 * @param array<string,mixed> $data Query data.
	 * @return string
	 */
	function sidrena_test_build_query( array $data ) {
		$parts = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $subkey => $subvalue ) {
					$parts[] = $key . '[' . $subkey . ']=' . $subvalue;
				}
				continue;
			}

			$parts[] = $key . '=' . $value;
		}

		return implode( '&', $parts );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append query arguments to a URL.
	 *
	 * Values are not URL-encoded, matching WordPress, so that placeholders
	 * such as `%#%` survive for `paginate_links()`.
	 *
	 * @param mixed ...$args Arguments.
	 * @return string
	 */
	function add_query_arg( ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$qs  = sidrena_test_build_query( $args[0] );
			$url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		} elseif ( 2 === count( $args ) && is_array( $args[0] ) ) {
			$qs  = sidrena_test_build_query( $args[0] );
			$url = (string) $args[1];
		} elseif ( 2 === count( $args ) && ! is_array( $args[0] ) ) {
			$qs  = sidrena_test_build_query( array( (string) $args[0] => $args[1] ) );
			$url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		} elseif ( 3 === count( $args ) ) {
			$qs  = sidrena_test_build_query( array( (string) $args[0] => $args[1] ) );
			$url = (string) $args[2];
		} else {
			return '';
		}

		$sep = false === strpos( $url, '?' ) ? '?' : '&';

		return $url . $sep . $qs;
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	/**
	 * Remove query arguments from a URL.
	 *
	 * @param string|string[] $key Query key(s) to remove.
	 * @param string|false    $url Optional. URL to modify. Defaults to $_SERVER['REQUEST_URI'].
	 * @return string
	 */
	function remove_query_arg( $key, $url = false ) {
		if ( false === $url ) {
			$url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		}

		$keys = is_array( $key ) ? $key : array( $key );

		$parts = explode( '?', (string) $url, 2 );
		$base  = $parts[0];
		$query = isset( $parts[1] ) ? $parts[1] : '';

		if ( '' === $query ) {
			return $base;
		}

		parse_str( $query, $params );

		foreach ( $keys as $k ) {
			unset( $params[ $k ] );
		}

		if ( empty( $params ) ) {
			return $base;
		}

		return $base . '?' . http_build_query( $params );
	}
}

if ( ! function_exists( 'size_format' ) ) {
	/**
	 * Human-readable file size.
	 *
	 * @param int $bytes Size in bytes.
	 * @return string
	 */
	function size_format( $bytes ) {
		return round( (int) $bytes / 1024, 1 ) . ' KB';
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	/**
	 * No theme override in unit tests.
	 *
	 * @param string[] $template_names Template names.
	 * @return string
	 */
	function locate_template( $template_names ) {
		unset( $template_names );

		return '';
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Record a single scheduled event.
	 *
	 * @param int     $timestamp Run time.
	 * @param string  $hook      Hook name.
	 * @param mixed[] $args      Arguments.
	 * @return bool
	 */
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['sidrena_test_scheduled'][] = array(
			'timestamp' => (int) $timestamp,
			'hook'      => (string) $hook,
			'args'      => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Record a recurring scheduled event.
	 *
	 * @param int     $timestamp  Run time.
	 * @param string  $recurrence Recurrence name.
	 * @param string  $hook       Hook name.
	 * @param mixed[] $args       Arguments.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		$GLOBALS['sidrena_test_recurring'][ (string) $hook ] = (int) $timestamp;
		$GLOBALS['sidrena_test_scheduled'][]                = array(
			'timestamp' => (int) $timestamp,
			'hook'      => (string) $hook,
			'args'      => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Read the next scheduled timestamp for a hook.
	 *
	 * @param string  $hook Hook name.
	 * @param mixed[] $args Arguments.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook, $args = array() ) {
		if ( isset( $GLOBALS['sidrena_test_recurring'][ (string) $hook ] ) ) {
			return (int) $GLOBALS['sidrena_test_recurring'][ (string) $hook ];
		}

		if ( isset( $GLOBALS['sidrena_test_scheduled'] ) && is_array( $GLOBALS['sidrena_test_scheduled'] ) ) {
			foreach ( array_reverse( $GLOBALS['sidrena_test_scheduled'] ) as $item ) {
				if ( is_array( $item ) && isset( $item['hook'] ) && $item['hook'] === $hook ) {
					return (int) $item['timestamp'];
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Clear a scheduled hook.
	 *
	 * @param string  $hook Hook name.
	 * @param mixed[] $args Arguments.
	 * @return int Number of cleared events.
	 */
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		$cleared = 0;

		if ( isset( $GLOBALS['sidrena_test_recurring'][ (string) $hook ] ) ) {
			unset( $GLOBALS['sidrena_test_recurring'][ (string) $hook ] );
			++$cleared;
		}

		if ( isset( $GLOBALS['sidrena_test_scheduled'] ) && is_array( $GLOBALS['sidrena_test_scheduled'] ) ) {
			foreach ( $GLOBALS['sidrena_test_scheduled'] as $k => $item ) {
				if ( is_array( $item ) && isset( $item['hook'] ) && $item['hook'] === $hook ) {
					unset( $GLOBALS['sidrena_test_scheduled'][ $k ] );
					++$cleared;
				}
			}
		}

		return $cleared;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Record a shortcode registration.
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Handler.
	 * @return void
	 */
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['sidrena_test_shortcodes'][ (string) $tag ] = $callback;
	}
}

if ( ! function_exists( 'menu_page_url' ) ) {
	/**
	 * Stub for menu_page_url.
	 *
	 * @param string $menu_slug Menu slug.
	 * @param bool   $echo      Whether to echo or return.
	 * @return string
	 */
	function menu_page_url( $menu_slug, $echo = true ) {
		$url = admin_url( 'admin.php?page=' . rawurlencode( $menu_slug ) );
		if ( $echo ) {
			echo esc_url( $url );
		}
		return $url;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Stub for submit_button.
	 *
	 * @param string|null $text  Button text.
	 * @param string      $type  Button type.
	 * @param string      $name  Button name.
	 * @param bool        $wrap  Whether to wrap.
	 * @param array|null  $other Other attributes.
	 * @return string
	 */
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
		$html = sprintf( '<input type="submit" name="%s" value="%s" class="button button-%s" />', esc_attr( $name ), esc_attr( (string) $text ), esc_attr( $type ) );
		if ( $wrap ) {
			$html = '<p class="submit">' . $html . '</p>';
		}
		echo $html;
		return $html;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Stub for number_format_i18n.
	 *
	 * @param int|float $number Number.
	 * @return string
	 */
	function number_format_i18n( $number ) {
		return (string) $number;
	}
}

if ( ! function_exists( 'paginate_links' ) ) {
	/**
	 * Stub for paginate_links.
	 *
	 * @param array|string $args Arguments.
	 * @return string
	 */
	function paginate_links( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'base'               => '%_%',
				'format'             => '',
				'current'            => 1,
				'total'              => 1,
				'before_page_number' => '',
			)
		);

		$total = (int) $args['total'];

		if ( $total <= 1 ) {
			return '';
		}

		$links = array();

		for ( $page_number = 1; $page_number <= $total; $page_number++ ) {
			$link = (string) $args['before_page_number'] . number_format_i18n( $page_number );

			if ( $page_number === (int) $args['current'] ) {
				$links[] = '<span class="page-numbers current">' . $link . '</span>';
			} else {
				$links[] = '<a class="page-numbers" href="' . esc_url( str_replace( '%#%', (string) $page_number, (string) $args['base'] ) ) . '">' . $link . '</a>';
			}
		}

		return implode( "\n", $links );
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * Capability test stub: a post type exists when the test registers it.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	function post_type_exists( $post_type ) {
		$registered = isset( $GLOBALS['sidrena_test_post_types'] ) && is_array( $GLOBALS['sidrena_test_post_types'] ) ? $GLOBALS['sidrena_test_post_types'] : array();

		return in_array( (string) $post_type, $registered, true );
	}
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * Register post type stub.
	 *
	 * @param string $post_type Post type name.
	 * @param array  $args      Arguments.
	 * @return bool
	 */
	function register_post_type( $post_type, $args = array() ) {
		unset( $args );

		if ( ! isset( $GLOBALS['sidrena_test_post_types'] ) ) {
			$GLOBALS['sidrena_test_post_types'] = array();
		}

		$GLOBALS['sidrena_test_post_types'][] = (string) $post_type;

		return true;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	/**
	 * Register taxonomy stub.
	 *
	 * @param string $taxonomy    Taxonomy.
	 * @param string $object_type Object type.
	 * @param array  $args        Arguments.
	 * @return bool
	 */
	function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
		unset( $object_type, $args );

		if ( ! isset( $GLOBALS['sidrena_test_taxonomies'] ) ) {
			$GLOBALS['sidrena_test_taxonomies'] = array();
		}

		$GLOBALS['sidrena_test_taxonomies'][] = (string) $taxonomy;

		return true;
	}
}

if ( ! function_exists( 'register_taxonomy_for_object_type' ) ) {
	/**
	 * Register taxonomy for object type stub.
	 *
	 * @param string $taxonomy    Taxonomy.
	 * @param string $object_type Object type.
	 * @return bool
	 */
	function register_taxonomy_for_object_type( $taxonomy, $object_type ) {
		unset( $taxonomy, $object_type );

		return true;
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	/**
	 * Term exists stub.
	 *
	 * @param string $term     Term slug or name.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $parent   Parent term ID.
	 * @return mixed
	 */
	function term_exists( $term, $taxonomy = '', $parent = null ) {
		unset( $parent );

		return ! empty( $GLOBALS['sidrena_test_terms'][ $taxonomy ][ $term ] );
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	/**
	 * Insert term stub.
	 *
	 * @param string $term     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Arguments.
	 * @return array
	 */
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		unset( $args );

		if ( ! isset( $GLOBALS['sidrena_test_terms'] ) ) {
			$GLOBALS['sidrena_test_terms'] = array();
		}

		if ( ! isset( $GLOBALS['sidrena_test_terms'][ $taxonomy ] ) ) {
			$GLOBALS['sidrena_test_terms'][ $taxonomy ] = array();
		}

		$data = array(
			'term_id'          => rand( 1, 999 ),
			'term_taxonomy_id' => rand( 1, 999 ),
		);

		$GLOBALS['sidrena_test_terms'][ $taxonomy ][ $term ] = $data;

		return $data;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * Post revision check stub.
	 *
	 * @param int|object $post Post.
	 * @return false
	 */
	function wp_is_post_revision( $post ) {
		unset( $post );

		return false;
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	/**
	 * Capability test stub: records registered meta boxes for assertions.
	 *
	 * @param string             $id       Meta box ID.
	 * @param string             $title    Meta box title.
	 * @param callable           $callback Render callback.
	 * @param string|null        $screen   Screen or post type.
	 * @param string             $context  Context.
	 * @param string             $priority Priority.
	 * @param array<mixed>       $args     Extra arguments.
	 * @return void
	 */
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'side', $priority = 'default', $args = array() ) {
		if ( ! isset( $GLOBALS['sidrena_test_meta_boxes'] ) || ! is_array( $GLOBALS['sidrena_test_meta_boxes'] ) ) {
			$GLOBALS['sidrena_test_meta_boxes'] = array();
		}

		$GLOBALS['sidrena_test_meta_boxes'][] = array(
			'id'      => (string) $id,
			'title'   => (string) $title,
			'callback'=> $callback,
			'screen'  => (string) $screen,
			'context' => (string) $context,
		);
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * Sanitize a CSS class in unit rendering tests.
	 *
	 * @param string $class Class name.
	 * @return string
	 */
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	/**
	 * Settings registration stub.
	 *
	 * @param string       $option_group Option group.
	 * @param string       $option_name  Option name.
	 * @param array<mixed> $args         Arguments.
	 * @return void
	 */
	function register_setting( $option_group, $option_name, $args = array() ) {
		if ( ! isset( $GLOBALS['sidrena_test_registered_settings'] ) ) {
			$GLOBALS['sidrena_test_registered_settings'] = array();
		}

		$GLOBALS['sidrena_test_registered_settings'][ $option_group ][ $option_name ] = $args;
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	/**
	 * Settings section stub.
	 *
	 * @param string   $id       Section ID.
	 * @param string   $title    Section title.
	 * @param callable $callback Render callback.
	 * @param string   $page     Page.
	 * @return void
	 */
	function add_settings_section( $id, $title, $callback, $page ) {
		if ( ! isset( $GLOBALS['sidrena_test_settings_sections'] ) ) {
			$GLOBALS['sidrena_test_settings_sections'] = array();
		}

		$GLOBALS['sidrena_test_settings_sections'][ $page ][ $id ] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	/**
	 * Settings field stub.
	 *
	 * @param string       $id       Field ID.
	 * @param string       $title    Field title.
	 * @param callable     $callback Render callback.
	 * @param string       $page     Page.
	 * @param string       $section  Section.
	 * @param array<mixed> $args     Extra arguments.
	 * @return void
	 */
	function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
		if ( ! isset( $GLOBALS['sidrena_test_settings_fields'] ) ) {
			$GLOBALS['sidrena_test_settings_fields'] = array();
		}

		$GLOBALS['sidrena_test_settings_fields'][ $page ][] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
			'section'  => $section,
			'args'     => $args,
		);
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	/**
	 * Settings sections rendering stub.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	function do_settings_sections( $page ) {
		// No-op stub for unit tests.
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	/**
	 * Settings errors rendering stub.
	 *
	 * @param string $setting Optional.
	 * @param bool   $sanitize Optional.
	 * @param bool   $hide_on_update Optional.
	 * @return void
	 */
	function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
		// No-op stub for unit tests.
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	/**
	 * Settings fields rendering stub.
	 *
	 * @param string $option_group Option group name.
	 * @return void
	 */
	function settings_fields( $option_group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Enqueue style stub.
	 *
	 * @param string      $handle Handle.
	 * @param string      $src    Source.
	 * @param string[]    $deps   Dependencies.
	 * @param string|bool $ver    Version.
	 * @param string      $media  Media.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		if ( ! isset( $GLOBALS['sidrena_test_enqueued_styles'] ) ) {
			$GLOBALS['sidrena_test_enqueued_styles'] = array();
		}

		$GLOBALS['sidrena_test_enqueued_styles'][ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Enqueue script stub.
	 *
	 * @param string      $handle    Handle.
	 * @param string      $src       Source.
	 * @param string[]    $deps      Dependencies.
	 * @param string|bool $ver       Version.
	 * @param bool        $in_footer In footer.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		if ( ! isset( $GLOBALS['sidrena_test_enqueued_scripts'] ) ) {
			$GLOBALS['sidrena_test_enqueued_scripts'] = array();
		}

		$GLOBALS['sidrena_test_enqueued_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * Script is stub.
	 *
	 * @param string $handle Handle.
	 * @param string $list   List.
	 * @return bool
	 */
	function wp_script_is( $handle, $list = 'enqueued' ) {
		if ( 'registered' === $list && isset( $GLOBALS['sidrena_test_registered_scripts'][ $handle ] ) ) {
			return true;
		}

		return ! empty( $GLOBALS['sidrena_test_enqueued_scripts'][ $handle ] );
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	/**
	 * Whether external object cache is in use.
	 *
	 * @param bool|null $using New state, or null to check.
	 * @return bool
	 */
	function wp_using_ext_object_cache( $using = null ) {
		if ( null !== $using ) {
			$GLOBALS['sidrena_test_ext_object_cache'] = (bool) $using;
		}

		return ! empty( $GLOBALS['sidrena_test_ext_object_cache'] );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * WP_REST_Request stub for unit tests.
	 */
	class WP_REST_Request {
		protected $method;
		protected $route;
		protected $params = array();

		public function __construct( $method = '', $route = '', $attributes = array() ) {
			unset( $attributes );
			$this->method = $method;
			$this->route  = $route;
		}

		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_params() {
			return $this->params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * WP_REST_Response stub for unit tests.
	 */
	class WP_REST_Response {
		protected $data;
		protected $status;
		protected $headers = array();

		public function __construct( $data = null, $status = 200, $headers = array() ) {
			$this->data    = $data;
			$this->status  = (int) $status;
			$this->headers = (array) $headers;
		}

		public function get_data() {
			return $this->data;
		}

		public function set_data( $data ) {
			$this->data = $data;
		}

		public function get_status() {
			return $this->status;
		}

		public function set_status( $status ) {
			$this->status = (int) $status;
		}

		public function header( $key, $value, $replace = true ) {
			unset( $replace );
			$this->headers[ $key ] = $value;
		}

		public function get_headers() {
			return $this->headers;
		}
	}
}

