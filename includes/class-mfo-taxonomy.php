<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFO_Taxonomy {
	public static function register() {
		$labels = array(
			'name'              => __( 'Media Folders', 'media-folders-organizer' ),
			'singular_name'     => __( 'Media Folder', 'media-folders-organizer' ),
			'add_new_item'      => __( 'Add New Folder', 'media-folders-organizer' ),
			'edit_item'         => __( 'Edit Folder', 'media-folders-organizer' ),
			'new_item_name'     => __( 'New Folder Name', 'media-folders-organizer' ),
			'parent_item'       => __( 'Parent Folder', 'media-folders-organizer' ),
			'parent_item_colon' => __( 'Parent Folder:', 'media-folders-organizer' ),
			'not_found'         => __( 'No folders found.', 'media-folders-organizer' ),
		);

		register_taxonomy(
			MFO_Helpers::TAXONOMY,
			'attachment',
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'upload_files',
				),
			)
		);
	}
}
