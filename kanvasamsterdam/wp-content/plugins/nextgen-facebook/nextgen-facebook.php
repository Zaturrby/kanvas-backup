<?php
/**
 * Plugin Name: NextGEN Facebook (NGFB)
 * Plugin URI: http://surniaulula.com/extend/plugins/nextgen-facebook/
 * Author: Jean-Sebastien Morisset
 * Author URI: http://surniaulula.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Description: Make sure social websites present your content correctly, no matter how your webpage is shared - from buttons, browser add-ons, or pasted URLs.
 * Requires At Least: 3.0
 * Tested Up To: 3.9.1
 * Version: 7.6.4
 * 
 * Copyright 2012-2014 - Jean-Sebastien Morisset - http://surniaulula.com/
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'Ngfb' ) ) {

	class Ngfb {
		/**
		 * Class Object Variables
		 */
		public $p;			// Ngfb
		public $admin;			// NgfbAdmin (admin menus and page loader)
		public $cache;			// SucomCache (object and file caching)
		public $debug;			// SucomDebug or NgfbNoDebug
		public $head;			// NgfbHead
		public $loader;			// NgfbLoader
		public $media;			// NgfbMedia (images, videos, etc.)
		public $msgs;			// NgfbMessages (admin tooltip messages)
		public $notice;			// SucomNotice
		public $og;			// NgfbOpenGraph (extends SucomOpengraph)
		public $opt;			// NgfbOptions
		public $reg;			// NgfbRegister
		public $script;			// SucomScript (admin jquery tooltips)
		public $sharing;		// NgfbSharing (wp_head and wp_footer js and buttons)
		public $style;			// SucomStyle (admin styles)
		public $update;			// SucomUpdate
		public $util;			// NgfbUtil (extends SucomUtil)
		public $webpage;		// SucomWebpage (title, desc, etc., plus shortcodes)

		/**
		 * Reference Variables (config, options, addon objects, etc.)
		 */
		public $cf = array();		// config array defined in construct method
		public $is_avail = array();	// assoc array for other plugin checks
		public $options = array();	// individual blog/site options
		public $site_options = array();	// multisite options
		public $addons = array();	// pro and gpl addons

		/**
		 * Ngfb Constructor
		 *
		 * Uses NgfbConfig's static methods to read configuration
		 * values into the $cf array, define constants, and require
		 * essential library files. Instantiates the NgfbRegister
		 * class, to register the activation / deactivation / uninstall
		 * hooks, along with adding the wpmu_new_blog and
		 * wpmu_activate_blog action hooks.
		 *
		 * set_config() is hooked into 'init' at -1 to allow other
		 * plugins to extend the $cf array as early as possible.
		 *
		 * @access public
		 * @return Ngfb
		 */
		public function __construct() {
			require_once( dirname( __FILE__ ).'/lib/config.php' );
			require_once( dirname( __FILE__ ).'/lib/register.php' );

			$this->cf = NgfbConfig::get_config();		// unfiltered - $cf['*'] array is not available
			NgfbConfig::set_constants( __FILE__ );
			NgfbConfig::require_libs( __FILE__ );

			$classname = __CLASS__.'Register';
			$this->reg = new $classname( $this );

			add_action( 'init', array( &$this, 'set_config' ), -1 );
			add_action( 'init', array( &$this, 'init_plugin' ), NGFB_INIT_PRIORITY );
			add_action( 'widgets_init', array( &$this, 'init_widgets' ), 10 );
		}

		// runs at init priority -1
		public function set_config() {
			$this->cf = NgfbConfig::get_config( null, true );	// apply filters - define the $cf['*'] array
		}

		// runs at init priority 1
		public function init_widgets() {
			$opts = get_option( NGFB_OPTIONS_NAME );
			if ( ! empty( $opts['plugin_widgets'] ) ) {
				foreach ( $this->cf['plugin'] as $lca => $info ) {
					if ( isset( $info['lib']['widget'] ) && is_array( $info['lib']['widget'] ) ) {
						foreach ( $info['lib']['widget'] as $id => $name ) {
							$classname = apply_filters( $lca.'_load_lib', false, 'widget/'.$id );
							if ( $classname !== false && class_exists( $classname ) )
								register_widget( $classname );
						}
					}
				}
			}
		}

		// runs at init priority 13 (by default)
		public function init_plugin() {
			if ( is_feed() ) 
				return;	// nothing to do in the feeds

			if ( ! empty( $_SERVER['NGFB_DISABLE'] ) ) 
				return;

			load_plugin_textdomain( NGFB_TEXTDOM, false, dirname( NGFB_PLUGINBASE ).'/languages/' );

			$this->set_objects();	// define the class object variables

			if ( $this->debug->is_on() === true )
				foreach ( array( 'wp_head', 'wp_footer', 'admin_head', 'admin_footer' ) as $action )
					foreach ( array( 1, 9999 ) as $prio ) {
						add_action( $action, create_function( '', 
							'echo "<!-- ngfb add_action( \''.$action.'\' ) priority '.$prio.' test = PASSED -->\n";' ), $prio );
						add_action( $action, array( &$this, 'show_debug_html' ), $prio );
					}
		}

		public function show_debug_html() { 
			$this->debug->show_html();
		}

		// called by activate_plugin() as well
		public function set_objects( $activate = false ) {

			/*
			 * basic plugin setup (settings, check, debug, notices, utils)
			 */
			$this->set_options();	// filter and define the $this->options and $this->site_options properties

			$this->check = new NgfbCheck( $this );
			$this->is_avail = $this->check->get_avail();		// uses $this->options in checks

			// configure the debug class
			$html_debug = ! empty( $this->options['plugin_debug'] ) || 
				( defined( 'NGFB_HTML_DEBUG' ) && NGFB_HTML_DEBUG ) ? true : false;
			$wp_debug = defined( 'NGFB_WP_DEBUG' ) && NGFB_WP_DEBUG ? true : false;
			if ( $html_debug || $wp_debug )
				$this->debug = new SucomDebug( $this, 
					array( 'html' => $html_debug, 'wp' => $wp_debug ) );
			else $this->debug = new NgfbNoDebug();			// fallback to dummy debug class

			$this->notice = new SucomNotice( $this );
			$this->util = new NgfbUtil( $this );
			$this->opt = new NgfbOptions( $this );
			$this->cache = new SucomCache( $this );			// object and file caching
			$this->style = new SucomStyle( $this );			// admin styles
			$this->script = new SucomScript( $this );		// admin jquery tooltips
			$this->webpage = new SucomWebpage( $this );		// title, desc, etc., plus shortcodes
			$this->media = new NgfbMedia( $this );			// images, videos, etc.
			$this->head = new NgfbHead( $this );			// open graph and twitter card meta tags

			if ( is_admin() ) {
				$this->msgs = new NgfbMessages( $this );	// admin tooltip messages
				$this->admin = new NgfbAdmin( $this );		// admin menus and page loader
			}

			if ( $this->is_avail['opengraph'] )
				$this->og = new NgfbOpengraph( $this );		// prepare open graph array
			else $this->og = new SucomOpengraph( $this );		// read open graph html tags

			if ( $this->is_avail['ssb'] )
				$this->sharing = new NgfbSharing( $this );	// wp_head and wp_footer js and buttons

			$this->loader = new NgfbLoader( $this );

			do_action( 'ngfb_init_addon' );

			/*
			 * check and create the default options array
			 *
			 * execute after all objects have been defines, so hooks into 'ngfb_get_defaults' are available
			 */
			if ( is_multisite() && ( ! is_array( $this->site_options ) || empty( $this->site_options ) ) )
				$this->site_options = $this->opt->get_site_defaults();

			if ( $activate == true || ( 
				! empty( $_GET['action'] ) && $_GET['action'] == 'activate-plugin' &&
				! empty( $_GET['plugin'] ) && $_GET['plugin'] == NGFB_PLUGINBASE ) ) {

				$this->debug->log( 'plugin activation detected' );

				if ( ! is_array( $this->options ) || empty( $this->options ) ||
					( defined( 'NGFB_RESET_ON_ACTIVATE' ) && NGFB_RESET_ON_ACTIVATE ) ) {

					$this->options = $this->opt->get_defaults();
					delete_option( NGFB_OPTIONS_NAME );
					add_option( NGFB_OPTIONS_NAME, $this->options, null, 'yes' );
					$this->debug->log( 'default options have been added to the database' );

					if ( defined( 'NGFB_RESET_ON_ACTIVATE' ) && NGFB_RESET_ON_ACTIVATE )
						$this->notice->inf( 'NGFB_RESET_ON_ACTIVATE constant is true &ndash;
							plugin options have been reset to their default values.', true );
				}
				$this->debug->log( 'exiting early: init_plugin() to follow' );
				return;	// no need to continue, init_plugin() will handle the rest
			}

			/*
			 * check and upgrade options if necessary
			 */
			$this->options = $this->opt->check_options( NGFB_OPTIONS_NAME, $this->options );
			if ( is_multisite() )
				$this->site_options = $this->opt->check_options( NGFB_SITE_OPTIONS_NAME, $this->site_options );

			/*
			 * configure class properties based on plugin settings
			 */
			$this->cache->object_expire = $this->options['plugin_object_cache_exp'];
			if ( ! empty( $this->options['plugin_file_cache_hrs'] ) && $this->check->aop() ) {
				if ( $this->debug->is_on( 'wp' ) === true )
					$this->cache->file_expire = NGFB_DEBUG_FILE_EXP;	// reduce to 300 seconds
				else $this->cache->file_expire = $this->options['plugin_file_cache_hrs'] * 60 * 60;
			} else $this->cache->file_expire = 0;	// just in case
			$this->is_avail['cache']['file'] = $this->cache->file_expire > 0 ? true : false;

			// disable the transient and object cache ONLY if the html debug mode is on
			if ( $this->debug->is_on( 'html' ) === true ) {
				foreach ( array( 'object', 'transient' ) as $name ) {
					$constant_name = 'NGFB_'.strtoupper( $name ).'_CACHE_DISABLE';
					$this->is_avail['cache'][$name] = ( defined( $constant_name ) && 
						! constant( $constant_name ) ) ? true : false;
				}
				$cache_msg = 'object cache '.( $this->is_avail['cache']['object'] ? 'could not be' : 'is' ).
					' disabled, and transient cache '.( $this->is_avail['cache']['transient'] ? 'could not be' : 'is' ).' disabled.';
				$this->debug->log( 'HTML debug mode active: '.$cache_msg );
				$this->notice->inf( 'HTML debug mode active &ndash; '.$cache_msg.' '.
					__( 'Informational messages are being added to webpages as hidden HTML comments.', NGFB_TEXTDOM ) );
			}

			if ( ! empty( $this->options['plugin_ngfb_tid'] ) ) {
				$this->util->add_plugin_filters( $this, array( 'installed_version' => 1, 'ua_plugin' => 1 ) );
				$this->update = new SucomUpdate( $this, $this->cf['plugin'], $this->cf['update_check_hours'] );
				if ( is_admin() ) {
					if ( $this->is_avail['aop'] === false ) {
						$shortname = $this->cf['plugin']['ngfb']['short'];
						$this->notice->inf( 'An Authentication ID was entered for '.$shortname.', 
						but the Pro version is not installed yet &ndash; 
						don\'t forget to update the '.$shortname.' plugin to install the Pro version.', true );
					}
					foreach ( $this->cf['plugin'] as $lca => $info ) {
						$last_update = get_option( $lca.'_utime' );
						if ( empty( $last_update ) || 
							( ! empty( $this->cf['update_check_hours'] ) && 
								$last_update + ( $this->cf['update_check_hours'] * 7200 ) < time() ) )
									$this->update->check_for_updates( $lca );
					}
				}
			}
		}

		public function filter_installed_version( $version ) {
			if ( ! $this->is_avail['aop'] )
				$version = '0.'.$version;
			return $version;
		}

		public function filter_ua_plugin( $plugin ) {
			if ( $this->check->aop() ) $plugin .= 'L';
			elseif ( $this->is_avail['aop'] ) $plugin .= 'U';
			else $plugin .= 'G';
			return $plugin;
		}

		public function set_options() {
			$this->options = get_option( NGFB_OPTIONS_NAME );

			// look for alternate options name
			if ( ! is_array( $this->options ) ) {
				if ( defined( 'NGFB_OPTIONS_NAME_ALT' ) && NGFB_OPTIONS_NAME_ALT ) {
					$this->options = get_option( NGFB_OPTIONS_NAME_ALT );
					if ( is_array( $this->options ) ) {
						update_option( NGFB_OPTIONS_NAME, $this->options );
						delete_option( NGFB_OPTIONS_NAME_ALT );
					} else $this->options = array();
				} else $this->options = array();
			}

			if ( is_multisite() ) {
				$this->site_options = get_site_option( NGFB_SITE_OPTIONS_NAME );

				// look for alternate site options name
				if ( ! is_array( $this->site_options ) ) {
					if ( defined( 'NGFB_SITE_OPTIONS_NAME_ALT' ) && NGFB_SITE_OPTIONS_NAME_ALT ) {
						$this->site_options = get_site_option( NGFB_SITE_OPTIONS_NAME_ALT );
						if ( is_array( $this->site_options ) ) {
							update_site_option( NGFB_SITE_OPTIONS_NAME, $this->site_options );
							delete_site_option( NGFB_SITE_OPTIONS_NAME_ALT );
						} else $this->site_options = array();
					} else $this->site_options = array();
				}

				// if multisite options are found, check for overwrite of site specific options
				if ( is_array( $this->options ) && is_array( $this->site_options ) ) {
					foreach ( $this->site_options as $key => $val ) {
						if ( array_key_exists( $key, $this->options ) && 
							array_key_exists( $key.':use', $this->site_options ) ) {

							switch ( $this->site_options[$key.':use'] ) {
								case'force':
									$this->options[$key.':is'] = 'disabled';
									$this->options[$key] = $this->site_options[$key];
									break;
								case 'empty':
									if ( empty( $this->options[$key] ) )
										$this->options[$key] = $this->site_options[$key];
									break;
							}

							// check for constant over-rides
							if ( function_exists( 'get_current_blog_id' ) ) {
								$constant_name = 'NGFB_OPTIONS_'.get_current_blog_id().'_'.strtoupper( $key );
								if ( defined( $constant_name ) )
									$this->options[$key] = constant( $constant_name );
							}
						}
					}
				}
			}
			$this->options = apply_filters( 'ngfb_get_options', $this->options );
			$this->site_options = apply_filters( 'ngfb_get_site_options', $this->site_options );
		}
	}

        global $ngfb;
	$ngfb = new Ngfb();
}

if ( ! class_exists( 'NgfbNoDebug' ) ) {
	class NgfbNoDebug {
		public function mark() { return; }
		public function args() { return; }
		public function log() { return; }
		public function show_html() { return; }
		public function get_html() { return; }
		public function is_on() { return false; }
	}
}

?>
