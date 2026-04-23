<?php
namespace WSVD;

use WSVD\Modules\Module_Auto_Coupons;
use WSVD\Modules\Module_Piece_Pricing;
use WSVD\Modules\Module_VIP_Pricing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

    private static $instance = null;
    private $modules = [];
    private $booted = false;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot() {
        if ( $this->booted ) {
            return;
        }

        $this->booted = true;
        $this->load_dependencies();

        if ( ! \WSVD\Helpers\Utils::is_wc_active() ) {
            return;
        }

        $this->init_modules();
    }

    private function load_dependencies() {
        require_once WSVD_PATH . 'includes/class-module-interface.php';
        require_once WSVD_PATH . 'includes/helpers/class-utils.php';
        require_once WSVD_PATH . 'includes/modules/class-module-vip-pricing.php';
        require_once WSVD_PATH . 'includes/modules/class-module-auto-coupon.php';
        require_once WSVD_PATH . 'includes/modules/class-module-piece-pricing.php';
    }

    private function init_modules() {
        $this->modules[] = new Module_VIP_Pricing();
        $this->modules[] = new Module_Auto_Coupons();
        $this->modules[] = new Module_Piece_Pricing();

        foreach ( $this->modules as $module ) {
            $module->register();
        }
    }
}
