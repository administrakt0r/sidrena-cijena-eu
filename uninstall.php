<?php
/**
 * Uninstall routine.
 *
 * Data is removed only when the administrator explicitly enabled deletion
 * (`twbce_cijena_delete_data_on_uninstall`) before uninstalling. Historical
 * and audit data is otherwise preserved (FR-077). Catalog data belonging to
 * WooCommerce or WordPress is never deleted.
 *
 * @package SidrenaCijena
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'TWBCE_CIJENA_UNINSTALL', true );

require_once __DIR__ . '/src/Support/Autoloader.php';
\Titanworks\SidrenaCijena\Support\Autoloader::register();

\Titanworks\SidrenaCijena\Support\Uninstaller::run();
