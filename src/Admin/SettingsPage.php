<?php
/**
 * Settings screen with tabs.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page dispatcher.
 */
final class SettingsPage {

	/**
	 * Settings group for the plugin settings option.
	 */
	const GROUP_SETTINGS = 'sidrena_cijena_settings';

	/**
	 * Settings group for the legal rule configuration.
	 */
	const GROUP_RULES = 'sidrena_cijena_rules';

	/**
	 * Register settings, sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP_SETTINGS,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		register_setting(
			self::GROUP_RULES,
			RuleConfig::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( RuleConfig::class, 'sanitize' ),
				'default'           => RuleConfig::defaults(),
			)
		);

		DisplaySettingsPage::register_settings();
		LegalSettingsPage::register_settings();
		SaleTypesSettings::register_settings();
		OperationsSettingsPage::register_settings();
	}

	/**
	 * Settings tabs: key => [class, label, option group].
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function tabs() {
		$tabs = array(
			'display'    => array( DisplaySettingsPage::class, __( 'Display', 'sidrena-cijena' ), self::GROUP_SETTINGS ),
			'legal'      => array( LegalSettingsPage::class, __( 'Legal rules', 'sidrena-cijena' ), self::GROUP_RULES ),
			'sales'      => array( SaleTypesSettings::class, __( 'Special sales', 'sidrena-cijena' ), self::GROUP_SETTINGS ),
			'operations' => array( OperationsSettingsPage::class, __( 'Operations', 'sidrena-cijena' ), self::GROUP_SETTINGS ),
		);

		/**
		 * Filters the settings tabs.
		 *
		 * @param array<mixed> $tabs Tabs.
		 */
		return (array) apply_filters( 'sidrena_cijena_settings_tabs', $tabs );
	}

	/**
	 * Current tab key.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'display';

		return isset( $tabs[ $tab ] ) ? $tab : 'display';
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'sidrena-cijena' ) );
		}

		$tabs = self::tabs();
		$tab  = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sidrena cijena - settings', 'sidrena-cijena' ); ?></h1>

			<p class="description">
				<?php echo esc_html__( 'This plugin is a technical compliance aid, not legal advice. Verify the configured rules with a legal or accounting professional.', 'sidrena-cijena' ); ?>
			</p>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $definition ) : ?>
					<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( AdminMenu::url( AdminMenu::PARENT, array( 'tab' => $key ) ) ); ?>">
						<?php echo esc_html( $definition[1] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				$definition = $tabs[ $tab ];
				$class      = $definition[0];

				settings_fields( $definition[2] );

				$class::render_fields();

				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
