<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFO_Admin {
	const PAGE_SLUG = 'mfo-media-folders';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_list_view_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list_view_query' ) );
		add_action( 'add_meta_boxes_attachment', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post_attachment', array( __CLASS__, 'save_attachment_folder' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'upload.php',
			__( 'Media Folders', 'media-folders-organizer' ),
			__( 'Media Folders', 'media-folders-organizer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'media_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mfo-admin',
			MFO_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MFO_VERSION
		);

		wp_enqueue_script(
			'mfo-admin',
			MFO_PLUGIN_URL . 'assets/js/admin-folders.js',
			array( 'wp-api' ),
			MFO_VERSION,
			true
		);

		wp_localize_script(
			'mfo-admin',
			'MFO_Admin',
			array(
				'root'  => esc_url_raw( rest_url( 'mfo/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'rename'       => __( 'Rename folder', 'media-folders-organizer' ),
					'new_name'     => __( 'New folder name', 'media-folders-organizer' ),
					'delete'       => __( 'Delete folder', 'media-folders-organizer' ),
					'delete_confirm' => __( 'Delete this folder? Items will be moved to its parent when possible.', 'media-folders-organizer' ),
					'create'       => __( 'Create folder', 'media-folders-organizer' ),
					'no_folders'   => __( 'No folders yet.', 'media-folders-organizer' ),
					'name_required' => __( 'Folder name is required.', 'media-folders-organizer' ),
					'created'      => __( 'Folder created.', 'media-folders-organizer' ),
				),
			)
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Media Folders', 'media-folders-organizer' ); ?></h1>
			<div class="mfo-admin-panel">
				<div class="mfo-admin-form">
					<h2><?php esc_html_e( 'Create Folder', 'media-folders-organizer' ); ?></h2>
					<label for="mfo-folder-name"><?php esc_html_e( 'Folder name', 'media-folders-organizer' ); ?></label>
					<input type="text" id="mfo-folder-name" class="regular-text" />
					<label for="mfo-folder-parent"><?php esc_html_e( 'Parent folder', 'media-folders-organizer' ); ?></label>
					<select id="mfo-folder-parent"></select>
					<button type="button" class="button button-primary" id="mfo-create-folder"><?php esc_html_e( 'Create Folder', 'media-folders-organizer' ); ?></button>
					<p class="description" id="mfo-message"></p>
				</div>
				<div class="mfo-admin-list">
					<h2><?php esc_html_e( 'Folders', 'media-folders-organizer' ); ?></h2>
					<div id="mfo-folder-tree"></div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_list_view_filter( $post_type ) {
		if ( 'attachment' !== $post_type ) {
			return;
		}

		$selected = isset( $_GET['mfo_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['mfo_folder'] ) ) : '';

		$terms = MFO_Helpers::get_folder_terms();
		?>
		<select name="mfo_folder" id="mfo_folder" class="postform">
			<option value="" <?php selected( $selected, '' ); ?>><?php esc_html_e( 'All media', 'media-folders-organizer' ); ?></option>
			<option value="uncategorized" <?php selected( $selected, 'uncategorized' ); ?>><?php esc_html_e( 'Uncategorized', 'media-folders-organizer' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<?php
				$depth = count( get_ancestors( $term->term_id, MFO_Helpers::TAXONOMY ) );
				$label = str_repeat( '— ', $depth ) . $term->name;
				?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected, (string) $term->term_id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function filter_list_view_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->base ) {
			return;
		}

		$folder = isset( $_GET['mfo_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['mfo_folder'] ) ) : '';
		if ( '' === $folder ) {
			return;
		}

		if ( 'uncategorized' === $folder ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => MFO_Helpers::TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				)
			);
			return;
		}

		$term_id = (int) $folder;
		if ( $term_id > 0 ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy'         => MFO_Helpers::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => array( $term_id ),
						'include_children' => true,
					),
				)
			);
		}
	}

	public static function register_metabox() {
		add_meta_box(
			'mfo-attachment-folder',
			__( 'Media Folder', 'media-folders-organizer' ),
			array( __CLASS__, 'render_metabox' ),
			'attachment',
			'side',
			'default'
		);
	}

	public static function render_metabox( $post ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_nonce_field( 'mfo_save_folder', 'mfo_folder_nonce' );
		$terms   = MFO_Helpers::get_folder_terms();
		$current = wp_get_object_terms( $post->ID, MFO_Helpers::TAXONOMY, array( 'fields' => 'ids' ) );
		$selected = ! empty( $current ) ? (int) $current[0] : 0;
		?>
		<p>
			<label for="mfo-folder-select"><?php esc_html_e( 'Folder', 'media-folders-organizer' ); ?></label>
			<select name="mfo_folder_id" id="mfo-folder-select" class="widefat">
				<option value="0" <?php selected( 0, $selected ); ?>><?php esc_html_e( 'Uncategorized', 'media-folders-organizer' ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<?php
					$depth = count( get_ancestors( $term->term_id, MFO_Helpers::TAXONOMY ) );
					$label = str_repeat( '— ', $depth ) . $term->name;
					?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected, (int) $term->term_id ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public static function save_attachment_folder( $post_id ) {
		if ( ! isset( $_POST['mfo_folder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfo_folder_nonce'] ) ), 'mfo_save_folder' ) ) {
			return;
		}

		if ( ! current_user_can( 'upload_files', $post_id ) ) {
			return;
		}

		$folder_id = isset( $_POST['mfo_folder_id'] ) ? (int) $_POST['mfo_folder_id'] : 0;
		if ( $folder_id > 0 ) {
			$term = get_term( $folder_id, MFO_Helpers::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}
			wp_set_object_terms( $post_id, array( $folder_id ), MFO_Helpers::TAXONOMY, false );
		} else {
			wp_set_object_terms( $post_id, array(), MFO_Helpers::TAXONOMY, false );
		}
	}
}
