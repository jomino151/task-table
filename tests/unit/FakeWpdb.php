<?php
/**
 * Stand-in for $wpdb that returns canned rows. Tests assert on what the plugin returns,
 * never on how it called this object.
 */
class Task_Table_Fake_Wpdb {
	public $prefix = 'wp_';
	public $row;
	public $rows = array();
	public $update_result = 1;

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function get_row( $query ) {
		return $this->row;
	}

	public function get_results( $query, $output = null ) {
		return $this->rows;
	}

	public function update( $table, $data, $where ) {
		return $this->update_result;
	}
}
