<?php
/**
 * Disable WordPress comment system
 *
 * @package     Mo\Disable_Comments
 * @author      MONTAGMORGENS GmbH
 * @copyright   2021 MONTAGMORGENS GmbH
 *
 * @wordpress-plugin
 * Plugin Name: MONTAGMORGENS Disable Comments
 * Description: Dieses Plugin deaktiviert das WordPress-Kommentarsystem.
 * Version:     1.0.2
 * Author:      MONTAGMORGENS GmbH
 * Author URI:  https://www.montagmorgens.com/
 * License:     GNU General Public License v.2
 * Text Domain: mo-disable-comments
 * GitHub Plugin URI: montagmorgens/mo-disable-comments
 * Primary Branch: main
 */

namespace Mo\Disable_Comments;

// Don't call this file directly.
defined( 'ABSPATH' ) || die();

// Bail if not on admin screen.
if ( ! is_admin() ) {
	return;
}

// Init plugin instance.
\add_action( 'plugins_loaded', '\Mo\Disable_Comments\Disable_Comments::get_instance' );

/**
 * Plugin code.
 *
 * @var object|null $instance The plugin singleton.
 */
final class Disable_Comments {
	const PLUGIN_VERSION       = '1.0.2';
	protected static $instance = null;
	private $modified_types    = [];


	/**
	 * Gets a singelton instance of our plugin.
	 *
	 * @return Disable_Comments
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'widgets_init', [ $this, 'disable_rc_widget' ] );
		add_filter( 'wp_headers', [ $this, 'filter_wp_headers' ] );
		add_action( 'template_redirect', [ $this, 'filter_query' ], 9 );
		add_action( 'template_redirect', [ $this, 'filter_admin_bar' ] );
		add_action( 'admin_init', [ $this, 'filter_admin_bar' ] );
		add_action( 'wp_loaded', [ $this, 'wp_load_hooks' ] );
	}

	private function get_disabled_post_types() {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		foreach ( array_keys( $types ) as $type ) {
			if ( ! in_array( $type, $this->modified_types, true ) && ! post_type_supports( $type, 'comments' ) ) {
				unset( $types[ $type ] );
			}
		}

		$disabled_post_types = array_keys( $types );
		$disabled_post_types = array_intersect( $disabled_post_types, array_keys( $types ) );

		return $disabled_post_types;
	}

	public function wp_load_hooks() {
		$disabled_post_types = $this->get_disabled_post_types();

		if ( ! empty( $disabled_post_types ) ) {
			foreach ( $disabled_post_types as $type ) {
				if ( post_type_supports( $type, 'comments' ) ) {
					$this->modified_types[] = $type;
					remove_post_type_support( $type, 'comments' );
					remove_post_type_support( $type, 'trackbacks' );
				}
			}
			add_filter( 'comments_array', [ $this, 'filter_existing_comments' ], 20, 2 );
			add_filter( 'comments_open', [ $this, 'filter_comment_status' ], 20, 2 );
			add_filter( 'pings_open', [ $this, 'filter_comment_status' ], 20, 2 );
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'filter_admin_menu' ], 9999 );
			add_action( 'admin_print_footer_scripts-index.php', [ $this, 'dashboard_js' ] );
			add_action( 'wp_dashboard_setup', [ $this, 'filter_dashboard' ] );
			add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
		} else {
			add_action( 'template_redirect', [ $this, 'check_comment_template' ] );

			add_filter( 'feed_links_show_comments_feed', '__return_false' );
			add_action( 'wp_footer', [ $this, 'hide_meta_widget_link' ], 100 );
		}
	}

	public function check_comment_template() {
		if ( is_singular() ) {
			wp_deregister_script( 'comment-reply' );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	public function empty_template_for_comments() {
		return '';
	}

	public function filter_wp_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public function filter_query() {
		if ( is_comment_feed() ) {
			wp_die( __( 'Comments are closed.' ), '', [ 'response' => 403 ] );
		}
	}

	public function filter_admin_bar() {
		if ( is_admin_bar_showing() ) {
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		}
	}

	public function filter_admin_menu() {
		global $pagenow;

		if ( 'comment.php' === $pagenow || 'edit-comments.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
			wp_die( __( 'Comments are closed.' ), '', [ 'response' => 403 ] ); // phpcs:ignore
		}

		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	public function filter_dashboard() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	public function dashboard_js() {
		echo '<script>
		jQuery(function($){
			$("#dashboard_right_now .comment-count, #latest-comments").hide();
		 	$("#welcome-panel .welcome-comments").parent().hide();
		});
		</script>';
	}

	public function hide_meta_widget_link() {
		if ( is_active_widget( false, false, 'meta', true ) && wp_script_is( 'jquery', 'enqueued' ) ) {
			echo '<script> jQuery(function($){ $(".widget_meta a[href=\'' . esc_url( get_bloginfo( 'comments_rss2_url' ) ) . '\']").parent().remove(); }); </script>';
		}
	}

	public function filter_existing_comments( $comments, $post_id ) {
		return [];
	}

	public function filter_comment_status( $open, $post_id ) {
		return false;
	}

	public function disable_rc_widget() {
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}
}
