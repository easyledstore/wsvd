<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wsvd_i18n', false ) ) :
class Wsvd_i18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'wsvd',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
endif;
