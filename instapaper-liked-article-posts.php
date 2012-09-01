<?php
/*
Plugin Name: Instapaper Liked Article Posts
Plugin URI: http://www.jeremyfelt.com/wordpress/plugins/instapaper-liked-article-posts/
Description: Checks your Instapaper 'Liked' article RSS feed and creates new posts with that data. Another step towards owning your data.
Version: 0.3
Author: Jeremy Felt
Author URI: http://www.jeremyfelt.com
License: GPL2
*/

/*  Copyright 2011 Jeremy Felt (email: jeremy.felt@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Instapaper_Liked_Article_Posts_Foghlaim {

	var $post_type = 'ilap_instapaper';

	var $post_status = 'publish';

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'admin_init', array( $this, 'upgrade' ) );
		add_action( 'admin_head', array( $this, 'modify_admin_icon' ) );
		add_action( 'admin_menu', array( $this, 'add_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 2 );
		add_action( 'init', array( $this, 'create_content_type' ) );
		add_action( 'ilap_process_feed', array( $this, 'process_feed' ) );
	}

	public function activate() {
	}

	/**
	 * When the plugin is deactivated, make sure to clear the scheduled hook we have set.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'ilap_process_feed' );
		wp_clear_scheduled_hook( 'ilap_hourly_action' );
	}

	function upgrade() {

		global $wpdb;
		$db_version = get_option( 'ilap_version', '0.3' );

		/**
		 * Before version 0.4, we used the hook ilap_hourly_action for our scheduled event.
		 * This only really makes sense when an hourly event is scheduled, so instead we're
		 * renaming it to ilap_process_feed. When doing this, we should make sure any old
		 * settings are transferred over before clearing the old hook. We should definitely
		 * clear the old hook to avoid DB pollution.
		 *
		 * We also used a hash based on the item's title to try and register uniqueness of
		 * an item when storing. This upgrade script pushes some stuff around with how we
		 * used to handle the hash. Instapaper doesn't send a GUID with the RSS feed, so it's
		 * up to us to determine uniqueness the best we can.
		 */
		if ( '0.3' == $db_version ) {
			if ( $prev_time = wp_next_scheduled( 'ilap_hourly_action' ) ) {
				$prev_interval = wp_get_schedule( 'ilap_hourly_action' );
				wp_clear_scheduled_hook( 'ilap_hourly_action' );
				wp_schedule_event( $prev_time, $prev_interval, 'ilap_process_feed' );
			}

			$existing_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta
				WHERE $wpdb->postmeta.meta_key = 'ilap_hash'" ) );

			foreach ( $existing_post_ids as $post_id ) {
				$ilap_post = get_post( $post_id );

				if ( ! $ilap_post )
					continue;

				$unique_hash = md5( $ilap_post->post_title );
				add_post_meta( $post_id, '_ilap_unique_hash', $unique_hash, true );
				delete_post_meta( $post_id, 'ilap_hash' );
			}

			update_option( 'ilap_version', '0.4' );
		}
	}

	/**
	 * Add a settings action link to the plugins page.
	 *
	 * Function gratefully forked from Pippin Williamson's WPMods article:
	 * http://www.wpmods.com/adding-plugin-action-links/
	 *
	 * @param $links array of link data associated with the plugin row
	 * @param $file string representing the current plugin's filename
	 *
	 * @return array of link data
	 */
	public function add_plugin_action_links( $links, $file ) {
		static $this_plugin;

		if ( ! $this_plugin )
			$this_plugin = plugin_basename( __FILE__ );

		if ( $file == $this_plugin ){
			$settings_link = '<a href="' . site_url( '/wp-admin/options-general.php?page=instapaper-liked-article-posts-settings' ) . '">' . __( 'Settings', 'instapaper-liked-article-posts' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Add an icon to the edit screen for our post type
	 */
	public function modify_admin_icon() {
		global $post_type;

		if ( $this->post_type == $post_type )
			echo '<style>#icon-edit { background: url("' . plugins_url( 'images/instapaper-48.png', __FILE__ ) . '") no-repeat; background-size: 32px 32px; }</style>';
	}

	/**
	 * Add our general settings page for the plugin
	 */
	public function add_settings() {
		add_options_page( __('Instapaper Posts', 'instapaper-liked-article-posts' ), __('Instapaper Posts', 'instapaper-liked-article-posts'), 'manage_options', 'instapaper-liked-article-posts-settings', array( $this, 'view_settings' ) );
	}

	/**
	 * The view of the settings page for the plugin
	 */
	public function view_settings() {
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"></div>
			<h2><?php _e( 'Instapaper Liked Article Posts', 'instapaper-liked-article-posts' ); ?></h2>
			<h3><?php _e( 'Overview', 'instapaper-liked-article-posts' ); ?>:</h3>
            <p style="margin-left:12px;max-width:640px;"><?php _e( 'The settings below will help determine where to check for your Instapaper Liked items, how often to look for them, and how they should be stored once new items are found.', 'instapaper-liked-article-posts' ); ?></p>
			<p style="margin-left: 12px; max-width: 640px;"><?php _e( 'The most important part of this process will be to determine the RSS feed for your Instapaper Liked items.', 'instapaper-liked-article-posts' ); ?></p>
			<ol style="margin-left:36px;">
				<li><?php _e( 'Visit your <a href="http://www.instapaper.com/liked">Instapaper Liked items</a> page. <em>(http://www.instapaper.com/liked)</em>', 'instapaper-liked-article-posts' ); ?></li>
				<li><?php _e( 'Scroll to the bottom of that page.', 'instapaper-liked-article-posts' ); ?></li>
				<li><?php _e( 'Look for the link labeled "This folder\'s RSS" next to the orange RSS icon.', 'instapaper-liked-article-posts' ); ?> <img src="<?php echo plugins_url( '/images/rss.png', __FILE__ ); ?>"></li>
				<li><?php _e( 'Copy this link and paste it into the "Instapaper RSS Feed" setting below these instructions.', 'instapaper-liked-article-posts' ); ?></li>
			</ol>
			<form method="post" action="options.php">
		<?php
		settings_fields( 'ilap_options' );
		do_settings_sections( 'ilap' );
		?>
				<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'instapaper-liked-article-posts' ); ?>" /></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the various settings displayed in view_settings
	 */
	public function register_settings() {
		register_setting( 'ilap_options', 'ilap_options', array( $this, 'validate_options' ) );
		add_settings_section( 'ilap_section_main', '', array( $this, 'display_main_section_text' ), 'ilap' );
		add_settings_section( 'ilap_section_post_type', '', array( $this, 'display_post_type_section_text' ), 'ilap' );
		add_settings_section( 'ilap_section_interval', '', array( $this, 'display_interval_section_text' ), 'ilap' );
		add_settings_field( 'ilap_instapaper_rss_feed', __( 'Instapaper RSS Feed:', 'instapaper-liked-article-posts' ), array( $this, 'display_rss_feed_text' ), 'ilap', 'ilap_section_main' );
		add_settings_field( 'ilap_max_fetch_items', __( 'Max Items To Fetch:', 'instapaper-liked-article-posts' ), array( $this, 'display_max_items_text' ), 'ilap', 'ilap_section_main' );
		add_settings_field( 'ilap_post_type', __( 'Post Type:', 'instapaper-liked-article-posts' ), array( $this, 'display_post_type_text' ), 'ilap', 'ilap_section_post_type' );
		add_settings_field( 'ilap_post_status', __( 'Default Post Status:', 'instapaper-liked-article-posts' ) , array( $this, 'display_post_status_text' ), 'ilap', 'ilap_section_post_type' );
		add_settings_field( 'ilap_fetch_interval', __( 'Feed Fetch Interval: ', 'instapaper-liked-article-posts' ), array( $this, 'display_fetch_interval_text' ), 'ilap', 'ilap_section_interval' );
	}

	/**
	 * We don't have any default section text to display, so this stays empty.
	 */
	public function display_main_section_text() {}

	public function display_post_type_section_text() {
		?>
		<h3>Custom Or Default Post Type</h3>
		<p style="margin-left:12px; max-width: 640px;"><?php _e( 'A new custom post type that adds an \'Instapaper\' slug to new items has been added and selected by default. You can change this to any other available post type if you would like.', 'instapaper-liked-article-posts' ); ?></p>
		<?php
	}

	public function display_interval_section_text() {
		?>
		<h3>RSS Fetch Frequency</h3>
		<p style="margin-left:12px;max-width: 630px;"><?php _e( 'This plugin currently depends on WP Cron operating fully as expected. In most cases, you should be able to select one of the intervals below and things will work as expected. If not, please post <a href="http://wordpress.org/support/plugin/instapaper-liked-article-posts">a question on the forum</a>. By default, we check for new items on an hourly basis.', 'instapaper-liked-article-posts' ); ?></p>
	<?php
		$seconds_till_cron = wp_next_scheduled( 'ilap_process_feed' ) - current_time( 'timestamp', true );
		$user_next_cron = date( 'H:i:sA', wp_next_scheduled( 'ilap_process_feed' ) + ( get_option( 'gmt_offset' ) * 3600 ) );
		?>
		<p style="margin-left:12px;"><?php printf( __( 'The next check is scheduled to run at %1$s, which occurs in %2$s seconds', 'instapaper-liked-article-posts' ), $user_next_cron, $seconds_till_cron ); ?></p>
		<?php
	}

	public function display_rss_feed_text() {
		$ilap_options = get_option( 'ilap_options' );
		?>
		<input style="width: 400px;" type="text" id="ilap_instapaper_rss_feed" name="ilap_options[instapaper_rss_feed]" value="<?php if ( isset( $ilap_options['instapaper_rss_feed'] ) ) : echo esc_url( $ilap_options['instapaper_rss_feed'] ); endif; ?>" />
		<br><em>http://www.instapaper.com/starred/rss/######/YYYYYYYYYYYYYY</em>
		<?php
	}

	public function display_post_type_text() {
		$ilap_options = get_option( 'ilap_options', array() );

		if ( empty( $ilap_options['post_type'] ) )
			$ilap_options['post_type'] = $this->post_type;

		$all_post_types = get_post_types( array( '_builtin' => false ) );

		$post_types = array_merge( $all_post_types, array( 'post', 'link' ) );

		echo '<select id="ilap_post_type" name="ilap_options[post_type]">';

		foreach( $post_types as $pt ){
			echo '<option value="' . esc_attr( $pt ) . '" ' . selected( $ilap_options['post_type'], $pt, false ) . '" >' . esc_attr( $pt ) . '</option>';
		}

		echo '</select>';
	}

	public function display_post_status_text() {
		$ilap_options = get_option( 'ilap_options', array() );

		if ( empty( $ilap_options['post_status'] ) )
			$ilap_options['post_status'] = $this->post_status;

		$post_stati = array( 'draft', 'publish', 'private' );

		echo '<select id="ilap_post_status" name="ilap_options[post_status]">';

		foreach( $post_stati as $ps ) {
			echo '<option value="' . esc_attr( $ps ) . '" ' . selected( $ilap_options['post_status'], $ps, false ) . '" >' . esc_attr( $ps ) . '</option>';
		}

		echo '</select>';
	}

	public function display_fetch_interval_text() {

		$intervals = wp_get_schedules();

		$ilap_options = get_option( 'ilap_options' );

		if ( empty( $ilap_options['fetch_interval'] ) )
			$ilap_options['fetch_interval'] = 'hourly';

		echo '<select id="ilap_fetch_interval" name="ilap_options[fetch_interval]">';

		foreach( $intervals as $input_value => $description ){
			echo '<option value="' . esc_attr( $input_value ) . '" ' . selected( $ilap_options['fetch_interval'], $input_value, false ) . '">' . esc_attr( $description['display'] ) . '</option>';
		}

		echo '</select>';
	}

	public function display_max_items_text() {
		$ilap_options = get_option( 'ilap_options' );
		?>
		<input type="text" id="ilap_max_fetch_items" name="ilap_options[max_fetch_items]" value="<?php if ( isset( $ilap_options['max_fetch_items'] ) ) : echo esc_attr( $ilap_options['max_fetch_items'] ); endif; ?>" />
		<?php
	}

	public function validate_options( $input ) {
		/*  Validation of a drop down. Hmm. Well, if it isn't on our list, we'll force it onto our list. */
		$valid_post_status_options = array( 'draft', 'publish', 'private' );
		$valid_post_type_options = array( 'post', 'link' );
		$valid_fetch_interval_options = array();

		// Build the list of valid schedules
		$schedules = wp_get_schedules();
		foreach ( $schedules as $k => $v ) {
			$valid_fetch_interval_options[] = $k;
		}

		$all_post_types = get_post_types( array( '_builtin' => false ) );
		foreach( $all_post_types as $p=>$k ){
			$valid_post_type_options[] = $p;
		}

		if( ! in_array( $input['post_status'], $valid_post_status_options ) )
			$input['post_status'] = 'draft';

		if( ! in_array( $input['post_type'], $valid_post_type_options ) )
			$input['post_type'] = 'ilap_instapaper';

		if( ! in_array( $input['fetch_interval'], $valid_fetch_interval_options ) )
			$input['fetch_interval'] = 'hourly';

		/*  This seems to be the only place we can reset the scheduled Cron if the frequency is changed, so here goes. */
		wp_clear_scheduled_hook( 'ilap_process_feed' );
		wp_schedule_event( ( time() + 30 ) , $input['fetch_interval'], 'ilap_process_feed' );

		$input['max_fetch_items'] = absint( $input['max_fetch_items'] );

		return $input;
	}

	public function create_content_type() {
		/*  Add the custom post type 'ilap_instapaper' to WordPress. */
		register_post_type( 'ilap_instapaper',
			array(
			     'labels' => array(
				     'name' => __( 'Instapaper' ),
				     'singular_name' => __( 'Instapaper Like' ),
				     'all_items' => __( 'All Instapaper Likes' ),
				     'add_new_item' => __( 'Add Instapaper Like' ),
				     'edit_item' => __( 'Edit Instapaper Like' ),
				     'new_item' => __( 'New Instapaper Like' ),
				     'view_item' => __( 'View Instapaper Like' ),
				     'search_items' => __( 'Search Instapaper Likes' ),
				     'not_found' => __( 'No Instapaper Likes found' ),
				     'not_found_in_trash' => __( 'No Instapaper Likes found in trash' ),
			     ),
			     'description' => 'Instapaper posts created by the Instapaper Liked Article Posts plugin.',
			     'public' => true,
			     'menu_icon' => plugins_url( '/images/instapaper-16.png', __FILE__ ),
			     'menu_position' => 5,
			     'hierarchical' => false,
			     'supports' => array (
				     'title',
				     'editor',
				     'author',
				     'custom-fields',
				     'comments',
				     'revisions',
				     'post-formats',
			     ),
			     'has_archive' => true,
			     'rewrite' => array(
				     'slug' => 'instapaper',
				     'with_front' => false
			     ),
			)
		);
	}

	/* We know we'll want the freshest version of the feed every time we check, so there's
	   no reason to set a cache higher than hourly. Set to 30 seconds just in case we
	   update the settings in order to trigger capture without causing any confusion. */
	public function modify_feed_cache() {
		return 30;
	}

	public function process_feed() {
		global $wpdb;
		/*  Grab the configured Instapaper Liked RSS feed and create new posts based on that. */

		/*  Go get some options! */
		$instapaper_options = get_option( 'ilap_options' );

		if ( empty( $instapaper_options['instapaper_rss_feed'] ) )
			return;

		$instapaper_feed_url = $instapaper_options['instapaper_rss_feed'];

		if ( empty( $instapaper_options['post_type'] ) )
			$instapaper_options['post_type'] = 'ilap_instapaper';

		if ( empty( $instapaper_options['post_status'] ) )
			$instapaper_options['post_status'] = 'publish';

		if ( empty( $instapaper_options['max_fetch_items'] ) )
			$instapaper_options['max_fetch_items'] = 10;

		/*  Add a quick filter to change the default SimplePie cache time */
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_feed_cache' ) );

		/*  Now fetch with the WordPress SimplePie function. */
		$instapaper_feed = fetch_feed( esc_url( $instapaper_options['instapaper_rss_feed'] ) );

		/*  We don't want to change anybody else's feed caching, so remove the filter. */
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_feed_cache' ) );

		if ( ! is_wp_error( $instapaper_feed ) ) {

			$feed_item_count = $instapaper_feed->get_item_quantity( absint( $instapaper_options['max_fetch_items'] ) );
			$instapaper_items = $instapaper_feed->get_items( 0, $feed_item_count );

			foreach( $instapaper_items as $item ) {

				$item_link = $item->get_link();
				$item_title = $item->get_title();
				$item_description = $item->get_description();

				$unique_hash = md5( $item_title );

				$existing_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta
					WHERE $wpdb->postmeta.meta_key = '_ilap_unique_hash' AND $wpdb->postmeta.meta_value = %s", $unique_hash ) );

				if ( $existing_post_id )
					continue;

				$item_content = '<p><a href="' . esc_url_raw( $item_link ) . '">' . $item_title . '</a></p>
                <p>' . $item_description . '</p>';

				$item_content = apply_filters( 'ilap_content_filter', $item_content, $item_link, $item_title, $item_description );

				$insta_post = array(
					'post_title' => $item_title,
					'post_content' => $item_content,
					'post_author' => 1,
					'post_status' => $post_status,
					'post_type' => $post_type,
				);

				$item_post_id = wp_insert_post( $insta_post );
				add_post_meta( $item_post_id, '_ilap_unique_hash', $unique_hash, true );
			}
		}
	}
}
new Instapaper_Liked_Article_Posts_Foghlaim();