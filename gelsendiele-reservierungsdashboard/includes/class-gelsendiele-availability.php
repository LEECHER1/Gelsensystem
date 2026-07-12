<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wertet Sondertage aus, ohne das reguläre Wochenmodell zu verändern.
 *
 * Regeln werden als versionierte Einstellungen gespeichert. Dadurch bleiben sie
 * exportierbar und können später ohne Datenverlust in ein Kalender-Modul oder
 * eine eigene Tabelle migriert werden.
 */
final class Gelsendiele_Availability {
	public static function rules_for_date( $date, $types = array() ) {
		$matches = array();
		foreach ( (array) Gelsendiele_Settings::get( 'availability', 'rules', array() ) as $rule ) {
			if ( empty( $rule['enabled'] ) || $date < $rule['start_date'] || $date > $rule['end_date'] ) {
				continue;
			}
			if ( ! empty( $types ) && ! in_array( $rule['type'], $types, true ) ) {
				continue;
			}
			$matches[] = $rule;
		}
		return $matches;
	}

	/** Wendet Schließungen, Sonderöffnungen und zeitliche Sperren an. */
	public static function apply_to_ranges( $date, $regular_ranges ) {
		if ( ! self::valid_date( $date ) ) {
			return array();
		}

		if ( self::rules_for_date( $date, array( 'closed', 'vacation' ) ) ) {
			return array();
		}

		$special = self::rules_for_date( $date, array( 'special_open' ) );
		$ranges  = empty( $special ) ? self::normalize_ranges( $regular_ranges ) : self::ranges_from_rules( $special );

		// Eine Sonderöffnung des Vortages kann über Mitternacht in den aktuellen
		// Kalendertag hineinreichen.
		$previous_date = self::date( $date )->modify( '-1 day' )->format( 'Y-m-d' );
		foreach ( self::rules_for_date( $previous_date, array( 'special_open' ) ) as $rule ) {
			if ( $rule['end_time'] < $rule['start_time'] ) {
				$ranges[] = array( 'start' => '00:00', 'end' => $rule['end_time'] );
			}
		}

		$blocks = self::ranges_from_rules( self::rules_for_date( $date, array( 'blocked_time' ) ) );
		foreach ( self::rules_for_date( $previous_date, array( 'blocked_time' ) ) as $rule ) {
			if ( $rule['end_time'] < $rule['start_time'] ) {
				$blocks[] = array( 'start' => '00:00', 'end' => $rule['end_time'] );
			}
		}

		return self::subtract_ranges( $date, self::normalize_ranges( $ranges ), $blocks );
	}

	/** Liefert reduzierte Kapazitätsgrenzen für genau einen Reservierungsstart. */
	public static function capacity_limits( DateTimeImmutable $start ) {
		$limits = array( 'max_bookings' => 0, 'max_people' => 0 );
		$date   = $start->format( 'Y-m-d' );
		$rules  = self::rules_for_date( $date, array( 'capacity' ) );

		$previous_date = $start->modify( '-1 day' )->format( 'Y-m-d' );
		foreach ( self::rules_for_date( $previous_date, array( 'capacity' ) ) as $rule ) {
			if ( $rule['start_time'] && $rule['end_time'] < $rule['start_time'] ) {
				$rules[] = $rule;
			}
		}

		foreach ( $rules as $rule ) {
			if ( ! self::capacity_rule_matches_time( $rule, $start ) ) {
				continue;
			}
			foreach ( array( 'max_bookings', 'max_people' ) as $key ) {
				$value = absint( $rule[ $key ] );
				if ( $value > 0 && ( 0 === $limits[ $key ] || $value < $limits[ $key ] ) ) {
					$limits[ $key ] = $value;
				}
			}
		}
		return $limits;
	}

	/** Öffentlicher Hinweis eines Sondertags, ohne interne Kommentare auszugeben. */
	public static function public_notice( $date ) {
		$messages = array();
		foreach ( self::rules_for_date( $date ) as $rule ) {
			$message = trim( (string) $rule['public_message'] );
			if ( '' !== $message ) {
				$messages[] = $message;
			}
		}
		return implode( ' ', array_values( array_unique( $messages ) ) );
	}

	private static function capacity_rule_matches_time( $rule, DateTimeImmutable $start ) {
		if ( empty( $rule['start_time'] ) || empty( $rule['end_time'] ) ) {
			return true;
		}
		$anchor = self::date( $rule['start_date'] );
		if ( ! $anchor ) {
			return false;
		}

		$time = $start->format( 'H:i' );
		if ( $rule['end_time'] > $rule['start_time'] ) {
			return $time >= $rule['start_time'] && $time < $rule['end_time'];
		}
		return $time >= $rule['start_time'] || $time < $rule['end_time'];
	}

	private static function ranges_from_rules( $rules ) {
		$ranges = array();
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['start_time'] ) && ! empty( $rule['end_time'] ) ) {
				$ranges[] = array( 'start' => $rule['start_time'], 'end' => $rule['end_time'] );
			}
		}
		return self::normalize_ranges( $ranges );
	}

	private static function subtract_ranges( $date, $ranges, $blocks ) {
		$open_intervals  = self::to_intervals( $date, $ranges );
		$block_intervals = self::to_intervals( $date, self::normalize_ranges( $blocks ) );
		foreach ( $block_intervals as $block ) {
			$remaining = array();
			foreach ( $open_intervals as $open ) {
				if ( $block[1] <= $open[0] || $block[0] >= $open[1] ) {
					$remaining[] = $open;
					continue;
				}
				if ( $block[0] > $open[0] ) {
					$remaining[] = array( $open[0], min( $block[0], $open[1] ) );
				}
				if ( $block[1] < $open[1] ) {
					$remaining[] = array( max( $block[1], $open[0] ), $open[1] );
				}
			}
			$open_intervals = $remaining;
		}

		$result = array();
		foreach ( $open_intervals as $interval ) {
			if ( $interval[1] <= $interval[0] ) {
				continue;
			}
			$result[] = array(
				'start' => $interval[0]->format( 'H:i' ),
				'end'   => $interval[1]->format( 'H:i' ),
			);
		}
		return self::normalize_ranges( $result );
	}

	private static function to_intervals( $date, $ranges ) {
		$intervals = array();
		foreach ( $ranges as $range ) {
			$start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $range['start'], wp_timezone() );
			$end   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $range['end'], wp_timezone() );
			if ( ! $start || ! $end ) {
				continue;
			}
			if ( $end <= $start ) {
				$end = $end->modify( '+1 day' );
			}
			$intervals[] = array( $start, $end );
		}
		return $intervals;
	}

	private static function normalize_ranges( $ranges ) {
		$unique = array();
		foreach ( (array) $ranges as $range ) {
			if ( ! is_array( $range ) || ! self::valid_time( isset( $range['start'] ) ? $range['start'] : '' ) || ! self::valid_time( isset( $range['end'] ) ? $range['end'] : '' ) || $range['start'] === $range['end'] ) {
				continue;
			}
			$unique[ $range['start'] . '-' . $range['end'] ] = array( 'start' => $range['start'], 'end' => $range['end'] );
		}
		ksort( $unique );
		return array_values( $unique );
	}

	private static function valid_time( $value ) {
		return is_string( $value ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value );
	}

	private static function valid_date( $value ) {
		$date = self::date( $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	private static function date( $value ) {
		return DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value, wp_timezone() );
	}
}
