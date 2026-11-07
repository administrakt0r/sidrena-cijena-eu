<?php
namespace SidrenaCijena\Database;

class PriceHistoryRepository {
	protected $table = 'wp_sidrena_price_history';

	public function clear_all() {
		global $wpdb;
		return $wpdb->query( "DELETE FROM {$this->table}" );
	}
}
