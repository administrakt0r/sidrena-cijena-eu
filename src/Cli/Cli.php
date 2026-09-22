<?php
/**
 * WP-CLI command registration and output helpers.
 *
 * @package SidrenaCijena\Cli
 */

namespace SidrenaCijena\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `wp sidrena-cijena` subcommands and buffers output for tests.
 */
final class Cli {

	/**
	 * Buffered output lines for tests: each entry is [method, message].
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	private static $buffer = array();

	/**
	 * Register all subcommands with WP-CLI.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'sidrena-cijena status', array( StatusCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena validate', array( ValidateCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena generate', array( GenerateCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena history', array( HistoryCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena import', array( ImportCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena cleanup', array( CleanupCommand::class, 'run' ) );
		\WP_CLI::add_command( 'sidrena-cijena seed-test-data', array( SeedTestDataCommand::class, 'run' ) );
	}

	/**
	 * Print a message via WP-CLI when available and always buffer it.
	 *
	 * @param string $message Message.
	 * @param string $method  One of log, success, warning, error, line.
	 * @return void
	 */
	public static function out( $message, $method = 'log' ) {
		$message = (string) $message;

		self::$buffer[] = array( $method, $message );

		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		switch ( $method ) {
			case 'success':
				\WP_CLI::success( $message );
				break;
			case 'warning':
				\WP_CLI::warning( $message );
				break;
			case 'error':
				\WP_CLI::error( $message );
				break;
			case 'line':
				\WP_CLI::line( $message );
				break;
			default:
				\WP_CLI::log( $message );
				break;
		}
	}

	/**
	 * Buffered output.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function buffer() {
		return self::$buffer;
	}

	/**
	 * Clear the output buffer.
	 *
	 * @return void
	 */
	public static function reset_buffer() {
		self::$buffer = array();
	}

	/**
	 * Encode data as JSON.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function as_json( $data ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $data );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback when WordPress is not loaded.
			$json = json_encode( $data );
		}

		return is_string( $json ) ? $json : '{}';
	}
}
