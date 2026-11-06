<?php
namespace SidrenaCijena\Database;

class PriceHistoryRepository {
	protected $table = 'wp_sidrena_price_history';

	public function update_history( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( $this->table, $data, array( 'id' => (int) $id ) );
	}
}
