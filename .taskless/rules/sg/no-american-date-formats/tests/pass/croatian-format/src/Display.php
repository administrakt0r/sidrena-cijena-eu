<?php
namespace SidrenaCijena;

class Display {
	public function render_date( $timestamp ) {
		return wp_date( 'd.m.Y.', $timestamp );
	}

	public function render_time( $timestamp ) {
		return wp_date( 'H:i', $timestamp );
	}
}
