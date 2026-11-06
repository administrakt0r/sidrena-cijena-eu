<?php
namespace SidrenaCijena\Database;

class PriceHistoryRepository {
	protected $table = 'wp_sidrena_price_history';

	public function insert( array $data ) {
		global $wpdb;
		return (int) $wpdb->insert( $this->table, $data );
	}
}
