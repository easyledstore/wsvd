<?php
namespace WSVD\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

class Utils {

    public static function clamp_percent( $val ) {
        $val = is_numeric( $val ) ? (float) $val : 0.0;
        if ( $val < 0 ) $val = 0;
        if ( $val > 100 ) $val = 100;

        if ( function_exists( 'wc_format_decimal' ) ) {
            return (float) wc_format_decimal( $val, 2 );
        }
        return round( $val, 2 );
    }

    public static function is_wc_active() {
        return class_exists( 'WooCommerce' );
    }

    public static function is_vip_pricing_active_for_current_user() {
        return self::get_current_user_discount_percent() > 0;
    }

    public static function get_current_user_discount_percent() {
        if ( ! is_user_logged_in() ) {
            return 0.0;
        }

        $global_percent = (float) get_option( 'wsvd_vip_global_percent', 15 );
        $user_custom_val = get_user_meta( get_current_user_id(), '_user_custom_discount', true );

        return ( $user_custom_val !== '' && $user_custom_val !== false )
            ? (float) $user_custom_val
            : $global_percent;
    }

    public static function calculate_percent_from_prices( $regular_price, $final_price ) {
        $regular_price = (float) $regular_price;
        $final_price   = (float) $final_price;

        if ( $regular_price <= 0 ) {
            return 0.0;
        }

        $percent = ( 1 - ( $final_price / $regular_price ) ) * 100;
        return self::clamp_percent( $percent );
    }

    public static function get_effective_price_for_current_user( $product ) {
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
            return 0.0;
        }

        $current_price = (float) $product->get_price();
        if ( ! is_user_logged_in() ) {
            return $current_price;
        }

        $discount_percent = self::get_current_user_discount_percent();
        if ( $discount_percent <= 0 ) {
            return $current_price;
        }

        $regular_price = method_exists( $product, 'get_regular_price' ) ? (float) $product->get_regular_price() : 0.0;
        if ( $regular_price <= 0 ) {
            return $current_price;
        }

        $is_cumulative = get_option( 'wsvd_vip_is_cumulative' );
        $price_from_regular = $regular_price * ( 1 - ( $discount_percent / 100 ) );

        if ( $is_cumulative ) {
            return $current_price * ( 1 - ( $discount_percent / 100 ) );
        }

        return ( $price_from_regular < $current_price ) ? $price_from_regular : $current_price;
    }
}
