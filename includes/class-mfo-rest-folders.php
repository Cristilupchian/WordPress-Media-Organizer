<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFO_Rest_Folders {
	public static function register_routes() {
		register_rest_route(
			'mfo/v1',
			'/folders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_folders' ),
					'permission_callback' => array( __CLASS__, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_folder' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'name'   => array( 'required' => true ),
						'parent' => array( 'required' => false ),
					),
				),
			)
		);

		register_rest_route(
			'mfo/v1',
			'/folders/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_folder' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_folder' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			'mfo/v1',
			'/folders/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reorder_folders' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	public static function can_read() {
		return current_user_can( 'upload_files' );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function get_folders() {
		$terms = MFO_Helpers::get_folder_terms();
		$tree  = MFO_Helpers::build_tree( $terms );

		return rest_ensure_response(
			array(
				'folders' => $tree,
			)
		);
	}

	public static function create_folder( WP_REST_Request $request ) {
		$name   = sanitize_text_field( $request['name'] );
		$parent = isset( $request['parent'] ) ? (int) $request['parent'] : 0;

		if ( '' === $name ) {
			return new WP_Error( 'mfo_invalid_name', __( 'Folder name is required.', 'media-folders-organizer' ), array( 'status' => 400 ) );
		}

		if ( $parent > 0 ) {
			$parent_term = get_term( $parent, MFO_Helpers::TAXONOMY );
			if ( ! $parent_term || is_wp_error( $parent_term ) ) {
				return new WP_Error( 'mfo_invalid_parent', __( 'Parent folder does not exist.', 'media-folders-organizer' ), array( 'status' => 400 ) );
			}
		}

		$result = wp_insert_term(
			$name,
			MFO_Helpers::TAXONOMY,
			array(
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) $result['term_id'];
		$order   = MFO_Helpers::get_next_order( $parent );
		update_term_meta( $term_id, MFO_Helpers::TERM_ORDER_META, $order );

		return rest_ensure_response(
			array(
				'id'     => $term_id,
				'name'   => $name,
				'parent' => $parent,
				'order'  => $order,
			)
		);
	}

	public static function update_folder( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];
		$term    = get_term( $term_id, MFO_Helpers::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'mfo_term_missing', __( 'Folder not found.', 'media-folders-organizer' ), array( 'status' => 404 ) );
		}

		$args = array();
		if ( isset( $request['name'] ) ) {
			$name = sanitize_text_field( $request['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'mfo_invalid_name', __( 'Folder name is required.', 'media-folders-organizer' ), array( 'status' => 400 ) );
			}
			$args['name'] = $name;
		}

		if ( isset( $request['parent'] ) ) {
			$parent = (int) $request['parent'];
			if ( $parent > 0 ) {
				$parent_term = get_term( $parent, MFO_Helpers::TAXONOMY );
				if ( ! $parent_term || is_wp_error( $parent_term ) ) {
					return new WP_Error( 'mfo_invalid_parent', __( 'Parent folder does not exist.', 'media-folders-organizer' ), array( 'status' => 400 ) );
				}
			}

			if ( ! MFO_Helpers::validate_parent( $term_id, $parent ) ) {
				return new WP_Error( 'mfo_invalid_parent', __( 'Invalid parent folder selection.', 'media-folders-organizer' ), array( 'status' => 400 ) );
			}

			$args['parent'] = $parent;
		}

		if ( empty( $args ) ) {
			return new WP_Error( 'mfo_no_changes', __( 'No updates provided.', 'media-folders-organizer' ), array( 'status' => 400 ) );
		}

		$result = wp_update_term( $term_id, MFO_Helpers::TAXONOMY, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'id'     => $term_id,
				'name'   => isset( $args['name'] ) ? $args['name'] : $term->name,
				'parent' => isset( $args['parent'] ) ? (int) $args['parent'] : (int) $term->parent,
			)
		);
	}

	public static function delete_folder( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];
		$term    = get_term( $term_id, MFO_Helpers::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'mfo_term_missing', __( 'Folder not found.', 'media-folders-organizer' ), array( 'status' => 404 ) );
		}

		$strategy = isset( $request['strategy'] ) ? sanitize_text_field( $request['strategy'] ) : 'parent';
		$move_to  = 0;

		if ( 'parent' === $strategy && $term->parent > 0 ) {
			$move_to = (int) $term->parent;
		}

		$object_ids = get_objects_in_term( $term_id, MFO_Helpers::TAXONOMY );
		if ( ! is_wp_error( $object_ids ) ) {
			foreach ( $object_ids as $object_id ) {
				$object_id = (int) $object_id;
				if ( $move_to > 0 ) {
					wp_set_object_terms( $object_id, array( $move_to ), MFO_Helpers::TAXONOMY, false );
				} else {
					wp_set_object_terms( $object_id, array(), MFO_Helpers::TAXONOMY, false );
				}
			}
		}

		$result = wp_delete_term( $term_id, MFO_Helpers::TAXONOMY );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $term_id,
			)
		);
	}

	public static function reorder_folders( WP_REST_Request $request ) {
		$tree = $request->get_json_params();
		if ( empty( $tree ) || ! is_array( $tree ) ) {
			return new WP_Error( 'mfo_invalid_payload', __( 'Invalid folder ordering payload.', 'media-folders-organizer' ), array( 'status' => 400 ) );
		}

		$map = array();
		MFO_Helpers::flatten_tree( $tree, 0, $map );
		if ( empty( $map ) ) {
			return new WP_Error( 'mfo_invalid_payload', __( 'Folder ordering payload is empty.', 'media-folders-organizer' ), array( 'status' => 400 ) );
		}

		foreach ( $map as $term_id => $data ) {
			$term = get_term( $term_id, MFO_Helpers::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'mfo_term_missing', __( 'Folder not found.', 'media-folders-organizer' ), array( 'status' => 404 ) );
			}

			if ( ! MFO_Helpers::validate_parent( $term_id, (int) $data['parent'] ) ) {
				return new WP_Error( 'mfo_invalid_parent', __( 'Invalid parent folder selection.', 'media-folders-organizer' ), array( 'status' => 400 ) );
			}
		}

		foreach ( $map as $term_id => $data ) {
			wp_update_term(
				$term_id,
				MFO_Helpers::TAXONOMY,
				array(
					'parent' => (int) $data['parent'],
				)
			);
			update_term_meta( $term_id, MFO_Helpers::TERM_ORDER_META, (int) $data['order'] );
		}

		return rest_ensure_response( array( 'updated' => true ) );
	}
}
