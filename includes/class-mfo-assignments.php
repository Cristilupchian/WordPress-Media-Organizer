<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFO_Assignments {
	private static $syncing = false;

	public static function init() {
		add_action( 'set_object_terms', array( __CLASS__, 'enforce_single_folder' ), 10, 6 );
	}

	public static function enforce_single_folder( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( MFO_Helpers::TAXONOMY !== $taxonomy ) {
			return;
		}

		if ( self::$syncing ) {
			return;
		}

		$object_id = (int) $object_id;
		if ( $object_id <= 0 ) {
			return;
		}

		$term_ids = wp_get_object_terms( $object_id, MFO_Helpers::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( empty( $term_ids ) ) {
			return;
		}

		$keep = array( (int) $term_ids[0] );
		self::$syncing = true;
		wp_set_object_terms( $object_id, $keep, MFO_Helpers::TAXONOMY, false );
		self::$syncing = false;
	}
}
