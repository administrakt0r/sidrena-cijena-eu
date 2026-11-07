<?php
/**
 * Settings screen with tabs.
 *
 * @package Titanworks\SidrenaCijena\Admin
 */

namespace Titanworks\SidrenaCijena\Admin;

use Titanworks\SidrenaCijena\Compliance\RuleConfig;
use Titanworks\SidrenaCijena\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page dispatcher.
 */
final class SettingsPage {

	/**
	 * Settings screen menu slug.
	 */
	const SLUG = 'sidrena-cijena-settings';

	/**
	 * Settings group for the plugin settings option.
	 */
	const GROUP_SETTINGS = 'twbce_cijena_settings';

	/**
	 * Settings group for the legal rule configuration.
	 */
	const GROUP_RULES = 'twbce_cijena_rules';

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
				'capability'        => Capabilities::capability(),
			)
		);

		register_setting(
			self::GROUP_RULES,
			RuleConfig::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( RuleConfig::class, 'sanitize' ),
				'default'           => RuleConfig::defaults(),
				'capability'        => Capabilities::capability(),
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
			'display'    => array( DisplaySettingsPage::class, __( 'Display', 'sidrena-cijena-eu' ), self::GROUP_SETTINGS ),
			'styling'    => array( StyleSettingsPage::class, __( 'Styling', 'sidrena-cijena-eu' ), self::GROUP_SETTINGS ),
			'legal'      => array( LegalSettingsPage::class, __( 'Legal rules', 'sidrena-cijena-eu' ), self::GROUP_RULES ),
			'sales'      => array( SaleTypesSettings::class, __( 'Special sales', 'sidrena-cijena-eu' ), self::GROUP_SETTINGS ),
			'operations' => array( OperationsSettingsPage::class, __( 'Operations', 'sidrena-cijena-eu' ), self::GROUP_SETTINGS ),
		);

		/**
		 * Filters the settings tabs.
		 *
		 * @param array<mixed> $tabs Tabs.
		 */
		return (array) apply_filters( 'twbce_cijena_settings_tabs', $tabs );
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
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'sidrena-cijena-eu' ) );
		}

		$tabs = self::tabs();
		$tab  = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sidrena cijena - settings', 'sidrena-cijena-eu' ); ?></h1>

			<p class="description">
				<?php echo esc_html__( 'This plugin is a technical compliance aid, not legal advice. Verify the configured rules with a legal or accounting professional.', 'sidrena-cijena-eu' ); ?>
			</p>

			<?php
			// WordPress prints settings errors on its own options screen only, so
			// without this call a saved setting is confirmed by nothing at all.
			settings_errors();
			?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $definition ) : ?>
					<a class="nav-tab <?php echo esc_attr( $key === $tab ? 'nav-tab-active' : '' ); ?>"
						href="<?php echo esc_url( AdminMenu::url( 'sidrena-cijena-settings', array( 'tab' => $key ) ) ); ?>">
						<?php echo esc_html( $definition[1] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="sidrena-busy-form">
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
