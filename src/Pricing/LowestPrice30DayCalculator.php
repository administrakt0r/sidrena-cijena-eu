<?php
/**
 * 30-day lowest price calculator.
 *
 * @package Titanworks\SidrenaCijena\Pricing
 */

namespace Titanworks\SidrenaCijena\Pricing;

use Titanworks\SidrenaCijena\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Pure calculation of the lowest price in the 30 calendar days immediately
 * before a special sale start date (start date excluded).
 *
 * The window is evaluated in the site time zone with calendar arithmetic, so
 * daylight-saving transitions never change its length. The calculator is pure
 * and side-effect free; persistence is the service layer's responsibility.
 */
class LowestPrice30DayCalculator {

	/**
	 * Calculation window for a sale start date.
	 *
	 * The window covers the 30 calendar days immediately before the sale start
	 * date: the start date itself is excluded and the day exactly 30 days
	 * before it is included (FR-019).
	 *
	 * @param string $sale_start Sale start date (`Y-m-d`).
	 * @return array{window_start:string,window_end:string}|null
	 */
	public function window( $sale_start ) {
		$start = Dates::parse_site( (string) $sale_start );

		if ( ! $start ) {
			return null;
		}

		$start = $start->setTime( 0, 0, 0 );

		return array(
			'window_start' => $start->modify( '-30 days' )->format( 'Y-m-d' ),
			'window_end'   => $start->modify( '-1 day' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Lowest applicable price in the window.
	 *
	 * @param array<mixed> $history    History rows (as returned by the history repository).
	 * @param string       $sale_start Sale start date (`Y-m-d`).
	 * @return float|null
	 */
	public function calculate( array $history, $sale_start ) {
		$result = $this->calculate_window( $history, $sale_start );

		return $result['price'];
	}

	/**
	 * Lowest applicable price together with the exact window it covers (FR-020).
	 *
	 * Computed `lowest_30_day` snapshots are ignored so a calculation can never
	 * reference itself; every other price type is an applicable consumer price.
	 *
	 * @param array<mixed> $history    History rows.
	 * @param string       $sale_start Sale start date (`Y-m-d`).
	 * @return array{price:float|null,window_start:string|null,window_end:string|null,rows:int}
	 */
	public function calculate_window( array $history, $sale_start ) {
		$audit = $this->audit_window( $history, $sale_start );

		return array(
			'price'        => $audit['price'],
			'window_start' => $audit['window_start'],
			'window_end'   => $audit['window_end'],
			'rows'         => $audit['rows'],
		);
	}

	/**
	 * Detailed audit evaluation of the calculation window and source records.
	 *
	 * @param array<mixed> $history    History rows.
	 * @param string       $sale_start Sale start date (`Y-m-d`).
	 * @return array{price:float|null,window_start:string|null,window_end:string|null,rows:int,source_records:array<int,array<string,mixed>>,determining_record:array<string,mixed>|null}
	 */
	public function audit_window( array $history, $sale_start ) {
		$window = $this->window( $sale_start );

		if ( ! $window ) {
			return array(
				'price'              => null,
				'window_start'       => null,
				'window_end'         => null,
				'rows'               => 0,
				'source_records'     => array(),
				'determining_record' => null,
			);
		}

		$lowest         = null;
		$rows           = 0;
		$starts         = array();
		$source_records = array();

		foreach ( $history as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type = isset( $row['price_type'] ) ? (string) $row['price_type'] : '';

			if ( 'lowest_30_day' === $type ) {
				continue;
			}

			$start = $this->start_datetime( $row );

			if ( '' !== $start ) {
				$starts[ $type ][] = $start;
			}
		}

		foreach ( $starts as $type => $dates ) {
			$dates = array_values( array_unique( $dates ) );
			sort( $dates );
			$starts[ $type ] = $dates;
		}

		foreach ( $history as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type = isset( $row['price_type'] ) ? (string) $row['price_type'] : '';

			if ( 'lowest_30_day' === $type ) {
				continue;
			}

			if ( ! isset( $row['price'] ) || '' === $row['price'] ) {
				continue;
			}

			$from_datetime = $this->start_datetime( $row );
			$from          = '' !== $from_datetime ? substr( $from_datetime, 0, 10 ) : '';

			if ( '' === $from || $from > $window['window_end'] ) {
				continue;
			}

			$has_valid_from = '' !== $this->date_part( isset( $row['valid_from'] ) ? $row['valid_from'] : null );
			$to             = $this->date_part( isset( $row['valid_to'] ) ? $row['valid_to'] : null );

			if ( '' === $to && ! $has_valid_from ) {
				// A record without a validity period applies on its creation day only.
				$to = $from;
			} elseif ( '' === $to && isset( $starts[ $type ] ) ) {
				// A price changed later in a local day was still applied during
				// that day. A transition exactly at midnight leaves no part of the
				// new day at the old price.
				$next = $this->next_start( $starts[ $type ], $from_datetime );

				if ( '' !== $next ) {
					$next_date = substr( $next, 0, 10 );
					$to        = '00:00:00' === substr( $next, 11, 8 ) ? Dates::add_days( $next_date, -1 ) : $next_date;
				}
			}

			if ( '' !== $to && $to < $window['window_start'] ) {
				continue;
			}

			$price = (float) $row['price'];

			if ( null === $lowest || $price < $lowest ) {
				$lowest = $price;
			}

			$source_records[] = array(
				'id'             => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'price'          => $price,
				'price_type'     => $type,
				'source'         => isset( $row['source'] ) ? (string) $row['source'] : 'manual',
				'valid_from'     => isset( $row['valid_from'] ) ? (string) $row['valid_from'] : null,
				'valid_to'       => isset( $row['valid_to'] ) ? (string) $row['valid_to'] : null,
				'created_at'     => isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
				'effective_from' => $from,
				'effective_to'   => $to,
				'is_determining' => false,
				'raw'            => $row,
			);

			++$rows;
		}

		$determining = null;
		foreach ( $source_records as &$record ) {
			$record['is_determining'] = ( null !== $lowest && abs( $record['price'] - $lowest ) < 0.000001 );
			if ( $record['is_determining'] && null === $determining ) {
				$determining = $record;
			}
		}
		unset( $record );

		return array(
			'price'              => $lowest,
			'window_start'       => $window['window_start'],
			'window_end'         => $window['window_end'],
			'rows'               => $rows,
			'source_records'     => $source_records,
			'determining_record' => $determining,
		);
	}

	/**
	 * First observation datetime of a history row, in site-local time.
	 *
	 * `created_at` is stored in UTC, so it has to be converted before it is
	 * compared with the site-local calculation window.
	 *
	 * @param array<mixed> $row History row.
	 * @return string `Y-m-d H:i:s` or an empty string.
	 */
	protected function start_datetime( array $row ) {
		$from = isset( $row['valid_from'] ) ? (string) $row['valid_from'] : '';

		if ( '' !== $from ) {
			$date = substr( $from, 0, 10 );

			if ( ! Dates::is_valid_date( $date ) ) {
				return '';
			}

			$time = strlen( $from ) > 10 ? substr( $from, 11, 8 ) : '00:00:00';

			return preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ? $date . ' ' . $time : '';
		}

		$created = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';

		if ( '' === $created ) {
			return '';
		}

		$local = Dates::from_utc( $created );

		return $local ? $local->format( 'Y-m-d H:i:s' ) : '';
	}

	/**
	 * Earliest observation datetime strictly after a datetime.
	 *
	 * @param string[] $dates Sorted unique observation datetimes.
	 * @param string   $from  Current observation datetime.
	 * @return string `Y-m-d H:i:s` or an empty string when the row is the latest.
	 */
	protected function next_start( array $dates, $from ) {
		foreach ( $dates as $datetime ) {
			if ( $datetime > $from ) {
				return $datetime;
			}
		}

		return '';
	}

	/**
	 * Date portion of a datetime value.
	 *
	 * @param mixed $value Datetime or date.
	 * @return string `Y-m-d` or an empty string.
	 */
	protected function date_part( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$date = substr( $value, 0, 10 );

		return Dates::is_valid_date( $date ) ? $date : '';
	}
}
