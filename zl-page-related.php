<?php
/*
 * Settings page for Related plugin
 */



/*
 * Adds an option page to Settings
 */
function zl_related_options() {
	add_options_page(__('ZL Related Posts', 'related'), __('ZL Related Posts', 'related'), 'manage_options', 'zl-related.php', 'zl_related_options_page');
}
add_action('admin_menu', 'zl_related_options');


function zl_related_options_page() {
	// Handle the POST
	$active_tab = 'related_show'; /* default tab */
	if ( isset( $_POST['form'] ) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') ) {
			die(__('Cheatin&#8217; uh?','related' ));
		}
		if ( $_POST['form'] == 'related_show' ) {
			$showkeys = array();
			foreach ($_POST as $key => $value) {
				if ( $key == 'form' ) {
					continue;
				}
				$showkeys[] = str_replace('show_', '', sanitize_text_field($key));
			}
			$showkeys = json_encode($showkeys);
			update_option( 'related_show', $showkeys );
		} else if ( $_POST['form'] == 'related_list' ) {
			$listkeys = array();
			foreach ($_POST as $key => $value) {
				if ( $key == 'form' ) {
					continue;
				}
				$listkeys[] = str_replace('list_', '', sanitize_text_field($key));
			}
			$listkeys = json_encode($listkeys);
			update_option( 'related_list', $listkeys );
			$active_tab = 'related_list';
		} else if ( $_POST['form'] == 'related_content' ) {
			if ( isset( $_POST['related_content'] ) ) {
				if ($_POST['related_content'] == 'on') {
					update_option('related_content', 1);
				} else {
					update_option('related_content', 0);
				}
			} else {
				update_option('related_content', 0);
			}
			if ( isset( $_POST['related_content_all'] ) ) {
				if ($_POST['related_content_all'] == 'on') {
					update_option('related_content_all', 1);
				} else {
					update_option('related_content_all', 0);
				}
			} else {
				update_option('related_content_all', 0);
			}
			if ( isset( $_POST['related_content_title'] ) ) {
				if ($_POST['related_content_title'] != '') {
					update_option( 'related_content_title', sanitize_text_field($_POST['related_content_title']) );
				}
			}
			$active_tab = 'related_content';
		}
	} ?>

	<div class="wrap">

	<h1><?php _e("Zipline's Related Posts", 'related'); ?></h1>

	<h2 class="nav-tab-wrapper related-nav-tab-wrapper">
		<a href="#" class="nav-tab <?php if ($active_tab == 'related_show') { echo "nav-tab-active";} ?>" rel="related_post_types"><?php _e('Post types', 'related'); ?></a>
		<a href="#" class="nav-tab <?php if ($active_tab == 'related_list') { echo "nav-tab-active";} ?>" rel="related_form"><?php _e('Form', 'related'); ?></a>
		<a href="#" class="nav-tab <?php if ($active_tab == 'related_content') { echo "nav-tab-active";} ?>" rel="related_content"><?php _e('Content', 'related'); ?></a>
		<a href="#" class="nav-tab" rel="related_about"><?php _e('About', 'related'); ?></a>
	</h2>

	<div class="related_options related_post_types <?php if ($active_tab == 'related_show') { echo "active";} ?>">
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
							} ?>
							<li><input type="hidden" class="form" value="related_show" name="form" />
								<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit','related' ); ?>"/></li>
						</ul>
					</form>
				</div> <!-- .misc-pub-section -->
			</div> <!-- .related-widget -->
		</div> <!-- metabox-holder -->
	</div> <!-- .related_post_types -->


	<div class="related_options related_form <?php if ($active_tab == 'related_list') { echo "active";} ?>">
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
				} ?>

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
						} ?>
						<li><input type="hidden" class="form" value="related_list" name="form" />
							<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit', 'related' ); ?>"/></li>
						</ul>
					</form>
				</div>
			</div>
		</div>
	</div> <!-- .related_post_types -->


	<div class="related_options related_content <?php if ($active_tab == 'related_content') { echo "active";} ?>">
		<div class="poststuff metabox-holder">
			<div class="related-widget">
				<h3 class="widget-top"><?php _e('Add the Related Posts to the content.', 'related'); ?></h3>
				<div class="misc-pub-section">
					<p><?php _e('If you select to add the Related Posts below the content, it will be added to every display of the content.', 'related'); ?></p>
					<form name="related_options_page_content" action="" method="POST">
						<ul>
							<li><label for="related_content">
								<input name="related_content" type="checkbox" id="related_content" <?php checked(1, get_option('related_content', 0) ); ?> />
								<?php _e('Add to content on single view.', 'related'); ?>
							</label></li>
							<li><label for="related_content_all">
								<input name="related_content_all" type="checkbox" id="related_content_all" <?php checked(1, get_option('related_content_all', 0) ); ?> />
								<?php _e('Add to content on all views.', 'related'); ?>
							</label></li>
							<li>
								<?php $related_content_title = get_option('related_content_title'); ?>
								<label for="related_content_title"><?php _e('Title to show above the related posts: ', 'related'); ?><br />
								<input name="related_content_title" type="text" id="related_content_title" value="<?php echo esc_attr(stripslashes(get_option('related_content_title', __('Related Posts', 'related')))); ?>" />
							</label>
							</li>
							<li><input type="hidden" class="form" value="related_content" name="form" />
							<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit', 'related' ); ?>"/></li>
						</ul>
					</form>
				</div>
			</div>
		</div>
	</div> <!-- .related_content -->


	<div class="related_options related_about">
		<div class="poststuff metabox-holder">
			<div class="related-widget">
				<h3 class="widget-top"><?php _e('About this plugin.', 'related'); ?></h3>
				
				<p><?php _e("This plugin is maintained by", 'related'); ?>
					<a href="https://wearezipline.com" target="_blank" title="Zipline">Zipline</a>.
				</p>
				
				<p><?php _e("This plugin is forked from 'Related' by Marcel Pol from", 'related'); ?>
					<a href="http://zenoweb.nl" target="_blank" title="ZenoWeb">ZenoWeb</a>.
				</p>
			</div>
		</div>
	</div> <!-- .related_about -->


	</div> <!-- .wrap -->
	<?php
}

