<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFO_Helpers {
	const TAXONOMY = 'mfo_folder';
	const TERM_ORDER_META = 'mfo_order';

	public static function get_folder_terms( $args = array() ) {
		$defaults = array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
			'meta_key'   => self::TERM_ORDER_META,
		);

		$args = wp_parse_args( $args, $defaults );

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	public static function build_tree( $terms ) {
		$by_parent = array();
		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $term;
		}

		return self::walk_tree( 0, $by_parent );
	}

	private static function walk_tree( $parent_id, $by_parent ) {
		$branch = array();
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return $branch;
		}

		foreach ( $by_parent[ $parent_id ] as $term ) {
			$branch[] = array(
				'id'       => (int) $term->term_id,
				'name'     => $term->name,
				'parent'   => (int) $term->parent,
				'count'    => (int) $term->count,
				'order'    => (int) get_term_meta( $term->term_id, self::TERM_ORDER_META, true ),
				'children' => self::walk_tree( $term->term_id, $by_parent ),
			);
		}

		return $branch;
	}

	public static function get_next_order( $parent_id ) {
		$terms = self::get_folder_terms(
			array(
				'parent'   => $parent_id,
				'fields'   => 'ids',
				'number'   => 1,
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
				'meta_key' => self::TERM_ORDER_META,
			)
		);

		if ( empty( $terms ) ) {
			return 1;
		}

		$last_id = (int) $terms[0];
		$last    = (int) get_term_meta( $last_id, self::TERM_ORDER_META, true );

		return $last + 1;
	}

	public static function validate_parent( $term_id, $parent_id ) {
		if ( $parent_id <= 0 ) {
			return true;
		}

		if ( $term_id === $parent_id ) {
			return false;
		}

		return ! self::is_descendant( $parent_id, $term_id );
	}

	public static function is_descendant( $term_id, $ancestor_id ) {
		$parent = (int) get_term_field( 'parent', $term_id, self::TAXONOMY );
		while ( $parent > 0 ) {
			if ( $parent === $ancestor_id ) {
				return true;
			}
			$parent = (int) get_term_field( 'parent', $parent, self::TAXONOMY );
		}

		return false;
	}

	public static function flatten_tree( $nodes, $parent_id = 0, &$map = array() ) {
		foreach ( $nodes as $index => $node ) {
			$term_id = isset( $node['id'] ) ? (int) $node['id'] : 0;
			if ( $term_id <= 0 ) {
				continue;
			}
			$map[ $term_id ] = array(
				'parent' => $parent_id,
				'order'  => $index + 1,
			);
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::flatten_tree( $node['children'], $term_id, $map );
			}
		}

		return $map;
	}
}
