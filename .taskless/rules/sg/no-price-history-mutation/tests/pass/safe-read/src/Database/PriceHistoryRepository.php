<?php
namespace SidrenaCijena\Database;

class PriceHistoryRepository {
	protected $table = 'wp_sidrena_price_history';

	public function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, (int) $id ) );
	}
}
