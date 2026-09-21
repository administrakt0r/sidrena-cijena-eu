<?php
/**
 * Applicable reference-date resolution.
 *
 * General reference date applies to every item unless the item belongs to an
 * explicitly mapped legacy category and legacy treatment is enabled.
 *
 * @package SidrenaCijena\Compliance
 */

namespace SidrenaCijena\Compliance;

use SidrenaCijena\Catalog\CatalogItem;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the reference date for a catalog item.
 */
final class ReferenceDateResolver {

	/**
	 * General rule identifier.
	 */
	const RULE_GENERAL = 'general';

	/**
	 * Legacy rule identifier.
	 */
	const RULE_LEGACY = 'legacy';

	/**
	 * Resolve the applicable reference date for an item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return array{date:string,rule:string,legacy:bool}
	 */
	public static function resolve( CatalogItem $item ) {
		$config = RuleConfig::all();

		if ( self::is_legacy( $item, $config ) ) {
			return array(
				'date'   => (string) $config['legacy_reference_date'],
				'rule'   => self::RULE_LEGACY,
				'legacy' => true,
			);
		}

		return array(
			'date'   => (string) $config['general_reference_date'],
			'rule'   => self::RULE_GENERAL,
			'legacy' => false,
		);
	}

	/**
	 * Resolved reference date string.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return string
	 */
	public static function date( CatalogItem $item ) {
		$resolved = self::resolve( $item );

		return $resolved['date'];
	}

	/**
	 * Whether an item receives the legacy treatment.
	 *
	 * @param CatalogItem $item   Catalog item.
	 * @param array|null  $config Rule configuration.
	 * @return bool
	 */
	public static function is_legacy( CatalogItem $item, ?array $config = null ) {
		$config = null === $config ? RuleConfig::all() : $config;

		$legacy = false;

		if ( ! empty( $config['legacy_enabled'] ) ) {
			$mapped = array_filter( array_map( 'strval', (array) $config['legacy_category_slugs'] ) );
			$legacy = (bool) array_intersect( $mapped, array_map( 'strval', $item->categories ) );
		}

		/**
		 * Filters whether an item receives the legacy reference-date treatment.
		 *
		 * @param bool        $legacy Whether legacy applies.
		 * @param CatalogItem $item   Catalog item.
		 * @param array       $config Rule configuration.
		 */
		return (bool) apply_filters( 'sidrena_cijena_is_legacy_item', $legacy, $item, $config );
	}

	/**
	 * Rule identifier for an item.
	 *
	 * @param CatalogItem $item Catalog item.
	 * @return string
	 */
	public static function rule( CatalogItem $item ) {
		$resolved = self::resolve( $item );

		return $resolved['rule'];
	}
}
