<?php
/*
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.txt
Copyright 2012-2014 - Jean-Sebastien Morisset - http://surniaulula.com/
*/

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'NgfbSubmenuSharingGplus' ) && class_exists( 'NgfbSubmenuSharing' ) ) {

	class NgfbSubmenuSharingGplus extends NgfbSubmenuSharing {

		public function __construct( &$plugin ) {
			$this->p =& $plugin;
			$this->p->debug->mark();
		}

		protected function get_rows( $metabox, $key ) {
			$rows = array();

			$rows[] = $this->p->util->th( 'Show Button in', 'short' ).'<td>'.
			( $this->show_on_checkboxes( 'gp' ) ).'</td>';

			$rows[] = $this->p->util->th( 'Preferred Order', 'short' ).'<td>'.
			$this->form->get_select( 'gp_order', 
				range( 1, count( $this->p->admin->submenu['sharing']->website ) ), 
					'short' ).'</td>';

			if ( $this->p->options['plugin_display'] == 'all' ) {
				$rows[] = $this->p->util->th( 'JavaScript in', 'short' ).'<td>'.
				$this->form->get_select( 'gp_js_loc', $this->p->cf['form']['js_locations'] ).'</td>';
			}

			$rows[] = $this->p->util->th( 'Default Language', 'short' ).'<td>'.
			$this->form->get_select( 'gp_lang', SucomUtil::get_pub_lang( 'gplus' ) ).'</td>';

			$rows[] = $this->p->util->th( 'Button Type', 'short' ).'<td>'.
			$this->form->get_select( 'gp_action', array( 
				'plusone' => 'G +1', 
				'share' => 'G+ Share',
			) ).'</td>';

			$rows[] = $this->p->util->th( 'Button Size', 'short' ).'<td>'.
			$this->form->get_select( 'gp_size', array( 
				'small' => 'Small [ 15px ]',
				'medium' => 'Medium [ 20px ]',
				'standard' => 'Standard [ 24px ]',
				'tall' => 'Tall [ 60px ]',
			) ).'</td>';

			$rows[] = $this->p->util->th( 'Annotation', 'short' ).'<td>'.
			$this->form->get_select( 'gp_annotation', array( 
				'none' => '',
				'inline' => 'Inline',
				'bubble' => 'Bubble',
				'vertical-bubble' => 'Vertical Bubble',
			) ).'</td>';

			if ( $this->p->options['plugin_display'] == 'all' ) {
				$rows[] = $this->p->util->th( 'Expand to', 'short' ).'<td>'.
				$this->form->get_select( 'gp_expandto', array( 
					'none' => '',
					'top' => 'Top',
					'bottom' => 'Bottom',
					'left' => 'Left',
					'right' => 'Right',
					'top,left' => 'Top Left',
					'top,right' => 'Top Right',
					'bottom,left' => 'Bottom Left',
					'bottom,right' => 'Bottom Right',
				) ).'</td>';
			}
	
			return $rows;
		}
	}
}

if ( ! class_exists( 'NgfbSharingGplus' ) ) {

	class NgfbSharingGplus {

		private static $cf = array(
			'opt' => array(				// options
				'defaults' => array(
					'gp_on_content' => 0,
					'gp_on_excerpt' => 0,
					'gp_on_admin_edit' => 1,
					'gp_on_sidebar' => 0,
					'gp_order' => 2,
					'gp_js_loc' => 'header',
					'gp_lang' => 'en-US',
					'gp_action' => 'plusone',
					'gp_size' => 'medium',
					'gp_annotation' => 'bubble',
					'gp_expandto' => 'none',
				),
			),
		);

		protected $p;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;
			$this->p->util->add_plugin_filters( $this, array( 'get_defaults' => 1 ) );
		}

		public function filter_get_defaults( $opts_def ) {
			return array_merge( $opts_def, self::$cf['opt']['defaults'] );
		}

		public function get_html( $atts = array(), &$opts = array() ) {
			if ( empty( $opts ) ) 
				$opts =& $this->p->options;
			$use_post = array_key_exists( 'use_post', $atts ) ? $atts['use_post'] : true;
			$source_id = $this->p->util->get_source_id( 'gplus', $atts );
			$atts['add_page'] = array_key_exists( 'add_page', $atts ) ? $atts['add_page'] : true;	// get_sharing_url argument
			$atts['url'] = empty( $atts['url'] ) ? 
				$this->p->util->get_sharing_url( $use_post, $atts['add_page'], $source_id ) : 
				apply_filters( $this->p->cf['lca'].'_sharing_url', $atts['url'],
					$use_post, $atts['add_page'], $source_id );
			$gp_class = $opts['gp_action'] == 'share' ? 'class="g-plus" data-action="share"' : 'class="g-plusone"';

			$html = '<!-- GooglePlus Button --><div '.$this->p->sharing->get_css( ( $opts['gp_action'] == 'share' ? 'gplus' : 'gplusone' ), $atts ).'><span '.$gp_class;
			$html .= ' data-size="'.$opts['gp_size'].'" data-annotation="'.$opts['gp_annotation'].'" data-href="'.$atts['url'].'"';
			$html .= empty( $opts['gp_expandto'] ) || $opts['gp_expandto'] == 'none' ? '' : ' data-expandTo="'.$opts['gp_expandto'].'"';
			$html .= '></span></div>';

			$this->p->debug->log( 'returning html ('.strlen( $html ).' chars)' );
			return $html."\n";
		}
		
		public function get_js( $pos = 'id' ) {
			$this->p->debug->mark();
			$prot = empty( $_SERVER['HTTPS'] ) ? 'http:' : 'https:';
			$js_url = $this->p->util->get_cache_url( $prot.'//apis.google.com/js/plusone.js' );

			return '<script type="text/javascript" id="gplus-script-'.$pos.'">'.$this->p->cf['lca'].'_insert_js( "gplus-script-'.$pos.'", "'.$js_url.'" );</script>'."\n";
		}
	}
}

?>
