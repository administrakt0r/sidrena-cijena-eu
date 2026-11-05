<?php
namespace SidrenaCijena\Database;

class PriceHistoryRepository {
	protected $table = 'wp_sidrena_price_history';

	public function delete_history( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, array( 'id' => (int) $id ) );
	}
}
