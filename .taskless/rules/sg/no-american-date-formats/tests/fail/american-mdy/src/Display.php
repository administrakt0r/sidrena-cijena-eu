<?php
namespace SidrenaCijena;

class Display {
	public function render_date( $timestamp ) {
		return wp_date( 'm/d/Y', $timestamp );
	}
}
