<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wsvd', false ) ) :
class Wsvd {

    private $loader;

    public function __construct() {
        if ( ! class_exists( 'Wsvd_Loader', false ) && defined( 'WSVD_PATH' ) ) {
            require_once WSVD_PATH . 'includes/class-wsvd-loader.php';
        }

        $this->loader = class_exists( 'Wsvd_Loader', false ) ? new Wsvd_Loader() : null;
    }

    public function run() {
        if ( ! class_exists( '\WSVD\Plugin', false ) && defined( 'WSVD_PATH' ) ) {
            require_once WSVD_PATH . 'includes/class-plugin.php';
        }

        if ( class_exists( '\WSVD\Plugin', false ) ) {
            \WSVD\Plugin::instance()->boot();
        }
    }

    public function get_plugin_name() {
        return 'wsvd';
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return defined( 'WSVD_VERSION' ) ? WSVD_VERSION : '1.2.0';
    }
}
endif;
