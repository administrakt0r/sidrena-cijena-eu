<?php
namespace SidrenaCijena;

class Display {
	public function render_time( $timestamp ) {
		return wp_date( 'h:i a', $timestamp );
	}
}
