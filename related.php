<?php
/*
Plugin Name: Related
Plugin URI: http://products.zenoweb.nl/free-wordpress-plugins/related/
Description: A simple 'related posts' plugin that lets you select related posts manually.
Version: 1.4.8
Author: Marcel Pol
Author URI: http://zenoweb.nl
Text Domain: related
Domain Path: /lang/


Copyright 2010-2012  Matthias Siegel  (email: matthias.siegel@gmail.com)
Copyright 2013       Marcel Pol       (email: marcel@timelord.nl)

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



if (!class_exists('Related')) :
	class Related {

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

			// Adds an option page for the plugin
			add_action('admin_menu', array(&$this, 'related_options'));
			
			// Add the related posts to the content, if set in options
			add_filter( 'the_content', array($this, 'related_content_filter') );
		}


		/*
		 * defineConstants
		 * Defines a few static helper values we might need
		 */
		protected function defineConstants() {
			define('RELATED_VERSION', '1.4.8');
			define('RELATED_HOME', 'http://zenoweb.nl');
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
			add_action('admin_print_scripts', array(&$this, 'loadScripts'));

			// Load the CSS
			add_action('admin_print_styles', array(&$this, 'loadCSS'));

			// Adds a meta box for related posts to the edit screen of each post type in WordPress
			$related_show = get_option('related_show');
			$related_show = json_decode( $related_show );
			if ( empty( $related_show ) ) {
				$related_show = array();
				$related_show[] = 'any';
			} else {
				foreach ( $related_show as $post_type ) {
					if ( $post_type == 'any' ) {
						$related_show = array();
						$related_show[] = 'any';
						break;
					}
				}
			}
			if ( $related_show[0] == 'any' ) {
				foreach (get_post_types() as $post_type) :
					add_meta_box($post_type . '-related-posts-box', __('Related posts', 'related' ), array(&$this, 'displayMetaBox'), $post_type, 'normal', 'high');
				endforeach;
			} else {
				foreach ($related_show as $post_type) :
					add_meta_box($post_type . '-related-posts-box', __('Related posts', 'related' ), array(&$this, 'displayMetaBox'), $post_type, 'normal', 'high');
				endforeach;
			}

		}


		/*
		 * loadScripts
		 * Load Javascript
		 */
		public function loadScripts() {
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('related-scripts', RELATED_URLPATH .'/scripts.js', false, RELATED_VERSION);
		}


		/*
		 * loadCSS
		 * Load CSS
		 */
		public function loadCSS() {
			wp_enqueue_style('related-css', RELATED_URLPATH .'/styles.css', false, RELATED_VERSION, 'all');
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

			echo '<div id="related-posts">';

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

			echo '
				</div>
				<p>
					<select class="related-posts-select" name="related-posts-select">
						<option value="0">' . __('Select', 'related' ) . '</option>';

			$related_list = get_option('related_list');
			$related_list = json_decode( $related_list );
			if ( empty( $related_list ) ) {
				$related_list = array();
				$related_list[] = 'any';
			} else {
				foreach ( $related_list as $post_type ) {
					if ( $post_type == 'any' ) {
						$related_list = array();
						$related_list[] = 'any';
						break;
					}
				}
			}

			$query = array(
				'nopaging' => true,
				'post__not_in' => array($post_id),
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'post_type' => $related_list,
				'orderby' => 'title',
				'order' => 'ASC'
			);

			$p = new WP_Query($query);

			$count = count($p->posts);
			$counter = 1;
			foreach ($p->posts as $thePost) {
				if ( is_int( $counter / 50 ) ) {
					echo '
						</select>
					</p>
					<p>
						<select class="related-posts-select" name="related-posts-select">
							<option value="0">' . __('Select', 'related' ) . '</option>';
				}
				?>
				<option value="<?php
					echo $thePost->ID; ?>"><?php echo
					$thePost->post_title.' ('.ucfirst(get_post_type($thePost->ID)).')'; ?></option>
				<?php
				$counter++;
			}

			wp_reset_query();
			wp_reset_postdata();

			echo '
					</select>
				</p>
				<p>' .
					__('Select related posts from the list. Drag selected ones to change order.', 'related' )
				. '</p>';
		}


		/*
		 * show
		 * The frontend function that is used to display the related post list
		 */
		public function show($id, $return = false) {

			global $wpdb;

			if (!empty($id) && is_numeric($id)) :
				$related = get_post_meta($id, 'related_posts', true);

				if (!empty($related)) :
					$rel = array();
					foreach ($related as $r) :
						$p = get_post($r);
						$rel[] = $p;
					endforeach;

					// If value should be returned as array, return it
					if ($return) :
						return $rel;

					// Otherwise return a formatted list
					else :
						$list = '<ul class="related-posts">';
						foreach ($rel as $r) :
							$list .= '<li><a href="' . get_permalink($r->ID) . '">' . $r->post_title . '</a></li>';
						endforeach;
						$list .= '</ul>';

						return $list;
					endif;
				else :
					return false;
				endif;
			else :
				return __('Invalid post ID specified', 'related' );
			endif;
		}


		/*
		 * Add the plugin data to the content, if it is set in the options.
		 */ 
		public function related_content_filter( $content ) {
			if ( get_option( 'related_content', 0 ) == 1 ) {
				global $related;
				$content .= '<div class="related_content" style="clear:both;">';
				$content .= $related->show( get_the_ID() );
				$content .= "</div>";
			}
			// otherwise returns the old content
			return $content;
		}


		/*
		 * related_options
		 * Adds an option page to Settings
		 */
		function related_options() {
			add_options_page(__('Related Posts', 'related'), __('Related Posts', 'related'), 'manage_options', 'related.php', array(&$this, 'related_options_page'));
		}
		function related_options_page() {
			// Handle the POST
			if ( isset( $_POST['form'] ) ) {
				if ( function_exists('current_user_can') && !current_user_can('manage_options') ) {
					die(__('Cheatin&#8217; uh?'));
				}
				if ( $_POST['form'] == 'show' ) {
					$showkeys = array();
					foreach ($_POST as $key => $value) {
						if ( $key == 'form' ) {
							continue;
						}
						$showkeys[] = str_replace('show_', '', $key);
					}
					$showkeys = json_encode($showkeys);
					update_option( 'related_show', $showkeys );
				} else if ( $_POST['form'] == 'list' ) {
					$listkeys = array();
					foreach ($_POST as $key => $value) {
						if ( $key == 'form' ) {
							continue;
						}
						$listkeys[] = str_replace('list_', '', $key);
					}
					$listkeys = json_encode($listkeys);
					update_option( 'related_list', $listkeys );
				} else if ( $_POST['form'] == 'content' ) {
					if ( isset( $_POST['related_content'] ) ) {
						if ($_POST['related_content'] == 'on') {
							update_option('related_content', 1);
						} else {						
							update_option('related_content', 0);
						}
					} else {						
						update_option('related_content', 0);
					}
				}
			} ?>

			<div class="wrap">

			<h2 class="nav-tab-wrapper related-nav-tab-wrapper">
				<a href="#" class="nav-tab nav-tab-active" rel="related_post_types"><?php _e('Post types', 'related'); ?></a>
				<a href="#" class="nav-tab" rel="related_form"><?php _e('Form', 'related'); ?></a>
				<a href="#" class="nav-tab" rel="related_content"><?php _e('Content', 'related'); ?></a>
			</h2>

			<div class="related_options related_post_types active">
				<div class="poststuff metabox-holder">
					<div class="related-widget">
						<h3 class="widget-top"><?php _e('Post Types to show the Related Posts form on.', 'related'); ?></h3>
			<?php
			$related_show = get_option('related_show');
			$related_show = json_decode( $related_show );
			$any = '';
			if ( empty( $related_show ) ) {
				$related_show = array();
				$related_show[] = 'any';
				$any = 'checked="checked"';
			} else {
				foreach ( $related_show as $key ) {
					if ( $key == 'any' ) {
						$any = 'checked="checked"';
					}
				}
			}
			?>

			<div class="misc-pub-section">
			<p><?php _e('If Any is selected, it will show on any Post Type. If none are selected, Any will still apply.', 'related'); ?></p>
			<form name="related_options_page_show" action="" method="POST">
				<ul>
				<li><label for="show_any">
					<input name="show_any" type="checkbox" id="show_any" <?php echo $any; ?>  />
					any
				</label></li>
				<?php
				$post_types = get_post_types( '', 'names' );
				$checked = '';
				foreach ( $post_types as $post_type ) {
					if ( $post_type == "revision" || $post_type == "nav_menu_item" ) {
						continue;
					}

					foreach ( $related_show as $key ) {
						if ( $key == $post_type ) {
							$checked = 'checked="checked"';
						}
					}
					?>
					<li><label for="show_<?php echo $post_type; ?>">
						<input name="show_<?php echo $post_type; ?>" type="checkbox" id="show_<?php echo $post_type; ?>" <?php echo $checked; ?>  />
						<?php echo $post_type; ?>
					</label></li>
					<?php
					$checked = ''; // reset
				}
				?>
				<li><input type="hidden" class="form" value="show" name="form" />
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit' ); ?>"/></li>
				</ul>
			</form>
			</div> <!-- .misc-pub-section -->
			</div> <!-- .related-widget -->
			</div> <!-- metabox-holder -->
			</div> <!-- .related_post_types -->


			<div class="related_options related_form">
				<div class="poststuff metabox-holder">
					<div class="related-widget">
						<h3 class="widget-top"><?php _e('Post Types to list on the Related Posts forms.', 'related'); ?></h3>
			<?php
			$any = ''; // reset
			$related_list = get_option('related_list');
			$related_list = json_decode( $related_list );
			if ( empty( $related_list ) ) {
				$related_list = array();
				$related_list[] = 'any';
				$any = 'checked';
			} else {
				foreach ( $related_list as $key ) {
					if ( $key == 'any' ) {
						$any = 'checked="checked"';
					}
				}
			}
			?>

			<div class="misc-pub-section">
			<p><?php _e('If Any is selected, it will list any Post Type. If none are selected, it will still list any Post Type.', 'related'); ?></p>
			<form name="related_options_page_listed" action="" method="POST">
				<ul>
				<li><label for="list_any">
					<input name="list_any" type="checkbox" id="list_any" <?php echo $any; ?>  />
					any
				</label></li>
				<?php
				$post_types = get_post_types( '', 'names' );
				foreach ( $post_types as $post_type ) {
					if ( $post_type == "revision" || $post_type == "nav_menu_item" ) {
						continue;
					}

					foreach ( $related_list as $key ) {
						if ( $key == $post_type ) {
							$checked = 'checked="checked"';
						}
					}
					?>
					<li><label for="list_<?php echo $post_type; ?>">
						<input name="list_<?php echo $post_type; ?>" type="checkbox" id="list_<?php echo $post_type; ?>" <?php echo $checked; ?>  />
						<?php echo $post_type; ?>
					</label></li>
					<?php
					$checked = ''; // reset
				}
				?>
				<li><input type="hidden" class="form" value="list" name="form" />
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit' ); ?>"/></li>
				</ul>
			</form>
			</div>
			</div>
			</div>
			</div> <!-- .related_post_types -->


			<div class="related_options related_content">
				<div class="poststuff metabox-holder">
					<div class="related-widget">
						<h3 class="widget-top"><?php _e('Add the Related Posts to the content.', 'related'); ?></h3>
		
			<div class="misc-pub-section">
			<p><?php _e('If you select to add the Related Posts below the content, it will be added to every display of the content.', 'related'); ?></p>
			<form name="related_options_page_content" action="" method="POST">
				<ul>
					<li><label for="related_content">
						<input name="related_content" type="checkbox" id="related_content" <?php checked(1, get_option('related_content', 0) ); ?> />
						<?php _e('Add to content', 'related'); ?>
					</label></li>
					<li><input type="hidden" class="form" value="content" name="form" />
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit' ); ?>"/></li>
				</ul>
			</form>
			</div>
			</div>
			</div>
			</div> <!-- .related_content -->


			</div> <!-- .wrap -->
			<?php
		}
	}

endif;


/*
 * related_links
 * Add Settings link to the main plugin page
 *
 */

function related_links( $links, $file ) {
        if ( $file == plugin_basename( dirname(__FILE__).'/related.php' ) ) {
                $links[] = '<a href="' . admin_url( 'options-general.php?page=related.php' ) . '">'.__( 'Settings' ).'</a>';
        }
        return $links;
}
add_filter( 'plugin_action_links', 'related_links', 10, 2 );


/* Include widget */
include( 'related-widget.php' );


/*
 * related_init
 * Function called at initialisation.
 * - Loads language files
 * - Make an instance of Related()
 */

function related_init() {
 	load_plugin_textdomain('related', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/');

	// Start the plugin
	global $related;
	$related = new Related();
}
add_action('plugins_loaded', 'related_init');

?>