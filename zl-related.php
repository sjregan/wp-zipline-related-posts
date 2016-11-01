<?php
/*
Plugin Name: Zipline's Related Posts
Plugin URI: https://wearezipline.com
Description: A simple 'related posts' plugin that lets you select related posts manually. Forked from 'Related' plugin by Marcel Pol
Version: 2.1
Author: Zipline
Author URI: https://wearezipline.com
Text Domain: related
Domain Path: /lang/
Original Author: Marcel Pol

Copyright 2010-2012  Matthias Siegel  (email: matthias.siegel@gmail.com)
Copyright 2013-2015  Marcel Pol       (email: marcel@timelord.nl)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



if (!class_exists('ZL_Related')) :
	class ZL_Related {

		/*
		 * __construct
		 * Constructor
		 */
		public function __construct() {

			// Set some helpful constants
			$this->defineConstants();

			// Register hook to save the related posts when saving the post
			add_action('save_post', array(&$this, 'save'));

			// Start the plugin
			add_action('admin_menu', array(&$this, 'start'));

			// Add the related posts to the content, if set in options
			add_filter( 'the_content', array($this, 'related_content_filter') );

			// Add the link to relate content easier
			// This doesn't play well with summaries
			// add_filter( 'the_content', array( &$this, 'add_relate_links' ));
		}


		/*
		 * defineConstants
		 * Defines a few static helper values we might need
		 */
		protected function defineConstants() {
			define('RELATED_VERSION', '2.1');
			define('RELATED_HOME', 'https://wearezipline.com');
			define('RELATED_FILE', plugin_basename(dirname(__FILE__)));
			define('RELATED_ABSPATH', str_replace('\\', '/', WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__))));
			define('RELATED_URLPATH', WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)));
		}

		/*
		 * start
		 * Main function
		 */
		public function start() {

			// Load the scripts
			add_action('admin_enqueue_scripts', array(&$this, 'loadScripts'));

			// Load the CSS
			add_action('admin_enqueue_scripts', array(&$this, 'loadCSS'));

			// Adds a meta box for related posts to the edit screen of each post type in WordPress
			$related_show = get_option('related_show');
			$related_show = json_decode( $related_show );
			if ( empty( $related_show ) ) {
				$related_show = array();
				$related_show[] = 'any';
			}
			if ( in_array( 'any', $related_show ) ) {
				foreach (get_post_types() as $post_type) :
					add_meta_box($post_type . '-related-posts-box', __('Related posts', 'related' ), array(&$this, 'displayMetaBox'), $post_type, 'normal', 'high');
				endforeach;
			} else {
				foreach ($related_show as $post_type) :
					add_meta_box($post_type . '-related-posts-box', __('Related posts', 'related' ), array(&$this, 'displayMetaBox'), $post_type, 'normal', 'high');
				endforeach;
			}

			add_action( 'restrict_manage_posts', array( &$this, 'add_selected_post_to_filter' ));

			if ( $this->get_selected_post_id() ) {
				if ( $this->get_selected_post_action() == 'add' ) {
					$this->add_related_post();
				} else {
					add_action( 'admin_notices', array( &$this, 'add_admin_notice' ));
					add_filter( 'get_edit_post_link', array( &$this, 'add_selected_post_id_to_edit_link' ));
					add_filter( 'bulk_actions-' . 'edit-post', '__return_empty_array' );
					add_filter( 'manage_edit-post_columns', array( &$this, 'remove_checkbox_column'));
					add_filter( 'post_row_actions', array( &$this, 'remove_row_actions'));
				}
			}
		}


		/*
		 * loadScripts
		 * Load Javascript
		 */
		public function loadScripts() {
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('related-scripts', RELATED_URLPATH .'/scripts.js', false, RELATED_VERSION, true);
			wp_enqueue_script('related-chosen', RELATED_URLPATH .'/chosen/chosen.jquery.min.js', false, RELATED_VERSION, true);
		}


		/*
		 * loadCSS
		 * Load CSS
		 */
		public function loadCSS() {
			wp_enqueue_style('related-css', RELATED_URLPATH .'/styles.css', false, RELATED_VERSION, 'all');
			wp_enqueue_style('related-css-chosen', RELATED_URLPATH .'/chosen/chosen.min.css', false, RELATED_VERSION, 'all');
		}


		/*
		 * save
		 * Save related posts when saving the post
		 */
		public function save($id) {
			global $pagenow;

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

			if ( isset($_POST['related-posts']) ) {
				update_post_meta($id, 'related_posts', $_POST['related-posts']);
			}
			/* Only delete on post.php page, not on Quick Edit. */
			if ( empty($_POST['related-posts']) ) {
				if ( $pagenow == 'post.php' ) {
					delete_post_meta($id, 'related_posts');
				}
			}
		}


		/*
		 * displayMetaBox
		 * Creates the output on the post screen
		 */
		public function displayMetaBox() {
			global $post;

			$post_id = $post->ID;

			echo '<p>' . __('Choose related posts. You can drag-and-drop them into the desired order:', 'related' ) . '</p><div id="related-posts">';

			// Get related posts if existing
			$related = get_post_meta($post_id, 'related_posts', true);

			if (!empty($related)) :
				foreach($related as $r) :
					$p = get_post($r);


					echo '
						<div class="related-post" id="related-post-' . $r . '">
							<input type="hidden" name="related-posts[]" value="' . $r . '">
							<span class="related-post-title">' . $p->post_title . ' (' . ucfirst(get_post_type($p->ID)) . ')</span>
							<a href="#">' . __('Delete', 'related' ) . '</a>
						</div>';
				endforeach;
			endif;

			/* First option should be empty with a data placeholder for text.
			 * The jQuery call allow_single_deselect makes it possible to empty the selection
			 */
			echo '
				</div>
				<p>
					<select class="related-posts-select chosen-select" name="related-posts-select" data-placeholder="' . __('Choose a related post... ', 'related' ) . '">';

			echo '<option value="0"></option>';


			$related_list = get_option('related_list');
			$related_list = json_decode( $related_list );

			if ( empty( $related_list ) || in_array( 'any', $related_list ) ) {
				// list all the post_types
				$related_list = array();

				$post_types = get_post_types( '', 'names' );
				foreach ( $post_types as $post_type ) {
					if ( $post_type == "revision" || $post_type == "nav_menu_item" ) {
						continue;
					}
					$related_list[] = $post_type;
				}
			}

			foreach ( $related_list as $post_type ) {

				echo '<optgroup label="'. $post_type .'">';

				/* Use suppress_filters to support WPML, only show posts in the right language. */
				$r = array(
					'nopaging' => true,
					'posts_per_page' => -1,
					'orderby' => 'title',
					'order' => 'ASC',
					'post_type' => $post_type,
					'suppress_filters' => 0,
					'post_status' => 'publish, inherit',
				);

				$posts = get_posts( $r );

				if ( ! empty( $posts ) ) {
					$args = array($posts, 0, $r);

					$walker = new ZL_Walker_RelatedDropdown;
					echo call_user_func_array( array( $walker, 'walk' ), $args );
				}

				echo '</optgroup>';

			} // endforeach

			wp_reset_query();
			wp_reset_postdata();

			echo '
					</select>
				</p>';

		}

		/**
		 * Fetch the templated list of related posts
		 *
		 * @param int $post_id
		 */
		public function fetch_list( $post_id ) {
			/* Compatibility for Qtranslate, Qtranslate-X and MQtranslate, and the get_permalink function */
			$plugin = "qtranslate/qtranslate.php";
			$q_plugin = "qtranslate-x/qtranslate.php";
			$m_plugin = "mqtranslate/mqtranslate.php";
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			if ( is_plugin_active($plugin) || is_plugin_active($q_plugin) || is_plugin_active($m_plugin) ) {
				add_filter('post_type_link', 'qtrans_convertURL');
			}

			if ( empty( $post_id ) || !is_numeric( $post_id )) {
				return;
			}

			$related_ids = get_post_meta( $post_id, 'related_posts', true);

			if ( empty( $related_ids )) {
				return;
			}

			// Get posts
			$related_posts = get_posts(array(
				'post_type' => 'post',
				'post__in' => $related_ids,
				'post_status' => 'publish',
			));

			if ( empty( $related_posts )) {
				return;
			}

			// Get attachments
			foreach ( $related_posts as &$post ) {
				$post->thumbnail_id = get_post_thumbnail_id( $post );
			}

			unset( $post );

			ob_start();
				include( locate_template( 'related_post_list.php' ));
				$template = ob_get_contents();
			ob_end_clean();

			return $template;
		}


		/*
		 * Add the plugin data to the content, if it is set in the options.
		 */
		public function related_content_filter( $content ) {
			if ( (get_option( 'related_content', 0 ) == 1 && is_singular()) || get_option( 'related_content_all', 0 ) == 1 ) {
				global $related;
				return $content.$related->fetch_list( get_the_ID() );
			}
			// otherwise returns the old content
			return $content;
		}

		/**
		 * Add links to the post content for relating posts
		 *
		 * @param string $content
		 * @return string
		 */
		public function add_relate_links ( $content ) {
			$post_id = get_the_ID();

			if ( !current_user_can( 'edit_post', $post_id ) )
				return;

			$divider = '<span class="related-divider"> | </span>';
			$relate_url = admin_url( sprintf( 'edit.php?zl-related-post-id=%s', $post_id ) );
			$link_text = apply_filters( 'zl_related_link_text', 'Add to Curated Post' );

			$links = array(
				sprintf( '<a href="%s" class="zl-relate-link-add">%s</a>', $relate_url, $link_text ),
			);

			$content .= '<div class="zl-relate-links">';
			$content .= implode( $divider, $links ).' ';
			$content .= '</div>';

			return $content;
		}

		/**
		 * Add selected post id to filter form
		 */
		public function add_selected_post_to_filter () {
			$post_id = $this->get_selected_post_id();
			echo sprintf( '<INPUT TYPE="hidden" name="zl-related-post-id" value="%s" />', $post_id );
		}

		/**
		 * Tell user what to do if we have selected a filter form
		 */
		public function add_admin_notice () {
			echo '<div class="notice notice-info"><p>Select post to add related post to.</p></div>';
		}

		/**
		 * Tell user what to do when they try and relate a post to itself
		 */
		public function add_error_admin_notice () {
			echo '<div class="notice notice-error"><p>You cannot relate a post to itself.</p></div>';
		}

		/**
		 * Tell user we added meta
		 */
		public function add_complete_admin_notice () {
			$post_id = $this->get_selected_post_id();

			$link = get_permalink( $post_id );
			$message = sprintf('<p>Selected post has been added to curation list. <a href="%s">Return to post.</a></p>', $link);
			echo '<div class="notice notice-success">'.$message.'</div>';
		}

		/**
		 * Get selected post id from request
		 * @return int
		 */
		public function get_selected_post_id () {
			return filter_input( INPUT_GET, 'zl-related-post-id', FILTER_SANITIZE_NUMBER_INT );
		}

		/**
		 * Get selected post action from request
		 * @return int
		 */
		public function get_selected_post_action () {
			return filter_input( INPUT_GET, 'zl-related-action', FILTER_SANITIZE_STRING );
		}


		/**
		 * Add the selected post id to the edit link
		 *
		 * @param string $link
		 * @return string
		 */
		public function add_selected_post_id_to_edit_link ( $link ) {
			$url = parse_url($link);

			if ( !empty( $url['query'] )) {
				$link .= '&';
			}

			$post_id = $this->get_selected_post_id();
			return $link .= 'zl-related-post-id='.$post_id.'&zl-related-action=add';
		}

		/**
		 * Add the selected post to the post we are editing
		 */
		public function add_related_post () {
			$id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			$selected = $this->get_selected_post_id();

			if ( $id && $selected ) {
				if ( $id == $selected ) {
					add_action( 'admin_notices', array( &$this, 'add_error_admin_notice' ));
				} else {
					$meta = get_post_meta( $id, 'related_posts', true );

					if ( !in_array( $selected, $meta )) {
						$meta[] = $selected;
						update_post_meta( $id, 'related_posts', $meta );
					}

					add_action( 'admin_notices', array( &$this, 'add_complete_admin_notice' ));
				}
			}
		}

		/**
		 * Remove checkbox from admin edit post list
		 * @param array $columns
		 * @return array
		 */
		public function remove_checkbox_column ( $columns ) {
			unset( $columns['cb'] );
			return $columns;
		}

		/*
		 * Remove mouse over row actions from edit post links
		 * @param array $actions
		 * @return array Returns empty
		 */
		public function remove_row_actions ( $actions ) {
			return array();
		}
	}

endif;

if (!class_exists('ZL_Walker_RelatedDropdown')) :

	/**
	 * Create HTML dropdown list of hierarchical post_types.
	 * Returns the list of <option>'s for the select dropdown.
	 */
	class ZL_Walker_RelatedDropdown extends Walker {
		/**
		 * @see Walker::$tree_type
		 * @since 2.1.0
		 * @var string
		 */
		public $tree_type = 'page';

		/**
		 * @see Walker::$db_fields
		 * @since 2.1.0
		 * @todo Decouple this
		 * @var array
		 */
		public $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');

		/**
		 * @see Walker::start_el()
		 * @since 2.1.0
		 *
		 * @param string $output Passed by reference. Used to append additional content.
		 * @param object $page   Page data object.
		 * @param int    $depth  Depth of page in reference to parent pages. Used for padding.
		 * @param int $id
		 */
		public function start_el( &$output, $page, $depth = 0, $args = array(), $id = 0 ) {
			$pad = str_repeat('&nbsp;', $depth * 3);

			$output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $page->ID ) . "\">";

			$title = $page->post_title;
			if ( '' === $title ) {
				$title = sprintf( __( '#%d (no title)', 'related' ), $page->ID );
			}

			/**
			 * Filter the page title when creating an HTML drop-down list of pages.
			 *
			 * @since 3.1.0
			 *
			 * @param string $title Page title.
			 * @param object $page  Page data object.
			 */
			$title = apply_filters( 'list_pages', $title, $page );
			$output .= $pad . esc_html( $title );
			$output .= "</option>\n";
		}
	}

endif;

/*
 * Add Settings link to the main plugin page
 *
 */
function zl_related_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/zl-related.php' ) ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=zl-related.php' ) . '">'.__( 'Settings', 'related' ).'</a>';
	}
	return $links;
}

add_filter( 'plugin_action_links', 'zl_related_links', 10, 2 );


/* Include Settings page */
include( 'zl-page-related.php' );

/* Include widget */
//include( 'related-widget.php' );

/*
 * related_init
 * Function called at initialisation.
 * - Loads language files
 * - Make an instance of Related()
 */

function zl_related_init() {
	load_plugin_textdomain('related', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/');

	// Start the plugin
	global $related;
	$related = new ZL_Related();
}
add_action('plugins_loaded', 'zl_related_init');

/**
 * @param bool $post_id
 * @param string $label_ac
 * @param string $label_de
 *
 * @return string|void
 */
function zlr_get_relate_link ( $post_id ) {

	if ( ! $post_id ) {
		return;
	}

	if ( !current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$relate_url = admin_url( sprintf( 'edit.php?zl-related-post-id=%s', $post_id ) );
	$link_text = apply_filters( 'zl_related_link_text', 'Add to Curated Post' );
	$link = sprintf( '<a href="%s" class="zl-relate-link-add">%s</a>', $relate_url, $link_text );

	return $link;
}

add_image_size( 'related-thumb', 50, 50, true );
