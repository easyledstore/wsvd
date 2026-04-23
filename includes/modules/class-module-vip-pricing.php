<?php
namespace WSVD\Modules;

use WSVD\Helpers\Utils;
use WSVD\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) exit;

class Module_VIP_Pricing implements Module_Interface {

    public function register() {
        // --- ADMIN ---
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // --- FRONTEND ---
        add_filter( 'woocommerce_get_price_html', [ $this, 'format_price_frontend' ], 99, 2 );
        add_filter( 'woocommerce_product_is_on_sale', [ $this, 'force_sale_badge' ], 99, 2 );
        add_filter( 'woocommerce_sale_flash', [ $this, 'custom_sale_flash' ], 20, 3 );
        add_filter( 'woocommerce_available_variation', [ $this, 'filter_available_variation' ], 20, 3 );
        add_filter( 'flatsome_product_labels', [ $this, 'custom_flatsome_product_labels' ], 20, 4 );

        // --- CART ---
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_cart_price' ], 20, 1 );
        add_action( 'woocommerce_cart_totals_before_order_total', [ $this, 'display_cart_label' ] );
    }

    public function add_admin_menu() {
        // Menu Principale
        add_menu_page(
            'Gestione Sconti VIP',
            'WS VIP Discounts',
            'manage_options',
            'wsvd-main', 
            [ $this, 'render_admin_page' ],
            'dashicons-tag',
            56
        );
        
        // Rinomina la prima sottovoce (per chiarezza)
        add_submenu_page(
            'wsvd-main',
            'Sconti VIP & Clienti',
            'Sconti VIP & Clienti',
            'manage_options',
            'wsvd-main',
            [ $this, 'render_admin_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'wsvd_vip_group', 'wsvd_vip_global_percent' );
        register_setting( 'wsvd_vip_group', 'wsvd_vip_is_cumulative' );
        register_setting( 'wsvd_vip_group', 'wsvd_vip_label' );
    }

    public function render_admin_page() {
        // Logica di salvataggio manuale
        if ( isset( $_POST['ws_update_users_discount'] ) && check_admin_referer( 'ws_update_users_action', 'ws_update_users_nonce' ) ) {
            if ( ! empty( $_POST['user_discounts'] ) ) {
                foreach ( $_POST['user_discounts'] as $u_id => $val ) {
                    update_user_meta( $u_id, '_user_custom_discount', sanitize_text_field( $val ) );
                }
                echo '<div class="notice notice-success is-dismissible"><p>Sconti salvati.</p></div>';
            }
        }

        $global_percent = get_option( 'wsvd_vip_global_percent', 15 );
        $is_cumulative  = get_option( 'wsvd_vip_is_cumulative' );
        $label_text     = get_option( 'wsvd_vip_label', 'Sconto Iscritto' );

        // Ricerca
        $search_term = isset( $_POST['ws_search_customer'] ) ? sanitize_text_field( $_POST['ws_search_customer'] ) : '';
        
        $args = [
            'role__in' => [ 'customer', 'subscriber' ],
            'orderby'  => 'ID', 'order' => 'DESC', 'number' => 50
        ];
        if ( ! empty( $search_term ) ) {
            $args['search'] = '*' . $search_term . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
            $args['number'] = 100;
        }
        $users = get_users( $args );

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Gestione Sconti VIP & Clienti</h1>
            <hr class="wp-header-end">

            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; box-sizing: border-box; width: 100%;">
                
                <h2 class="title">Configurazione Globale</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'wsvd_vip_group' ); do_settings_sections( 'wsvd_vip_group' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Sconto Default (%)</th>
                            <td><input type="number" name="wsvd_vip_global_percent" value="<?php echo esc_attr($global_percent); ?>" step="0.1" min="0" max="100" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Etichetta Frontend</th>
                            <td><input type="text" name="wsvd_vip_label" value="<?php echo esc_attr($label_text); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Modalità</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="wsvd_vip_is_cumulative" value="1" <?php checked( 1, $is_cumulative, true ); ?>>
                                        <strong>Cumulativo</strong> (Somma lo sconto alle offerte esistenti)
                                    </label>
                                    <p class="description">Se deselezionato, il sistema applicherà il "Miglior Prezzo" (il più basso tra listino scontato e offerta).</p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Salva Configurazione' ); ?>
                </form>

                <hr style="margin: 30px 0;">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                    <h2 class="title" style="margin:0;">Lista Clienti <?php echo $search_term ? '(Filtrati)' : ''; ?></h2>
                    <form method="post" action="" style="display:flex; gap:5px;">
                        <input type="search" name="ws_search_customer" value="<?php echo esc_attr($search_term); ?>" placeholder="Cerca cliente...">
                        <input type="submit" class="button" value="Cerca">
                        <?php if($search_term): ?><a href="<?php echo remove_query_arg('ws_search_customer'); ?>" class="button">Reset</a><?php endif; ?>
                    </form>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field( 'ws_update_users_action', 'ws_update_users_nonce' ); ?>
                    <table class="wp-list-table widefat fixed striped table-view-list users">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th>Cliente</th>
                                <th>Città</th>
                                <th>Speso Totale</th>
                                <th width="150">Sconto Personale (%)</th>
                                <th width="100">Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( ! empty( $users ) ) : ?>
                            <?php foreach ( $users as $user ) : 
                                $u_id = $user->ID;
                                $val = get_user_meta( $u_id, '_user_custom_discount', true );
                                $is_custom = ($val !== '' && $val !== false);
                                
                                $spent = '0.00'; $city = '-';
                                if ( class_exists('WC_Customer') ) {
                                    $customer = new \WC_Customer( $u_id );
                                    $spent = wc_price( $customer->get_total_spent() );
                                    $city = $customer->get_billing_city();
                                }
                            ?>
                            <tr>
                                <td>#<?php echo $u_id; ?></td>
                                <td>
                                    <strong><?php echo esc_html( $user->display_name ); ?></strong><br>
                                    <a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a>
                                </td>
                                <td><?php echo esc_html( $city ); ?></td>
                                <td><?php echo $spent; ?></td>
                                <td>
                                    <input type="number" name="user_discounts[<?php echo $u_id; ?>]" value="<?php echo esc_attr( $is_custom ? $val : '' ); ?>" placeholder="<?php echo esc_attr($global_percent); ?>%" min="0" max="100" step="0.1" style="width: 80px;">
                                </td>
                                <td><?php echo $is_custom ? '<span class="badge" style="background:#0073aa;color:#fff;padding:2px 5px;border-radius:3px;">Custom</span>' : '<span style="color:#aaa;">Global</span>'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">Nessun utente trovato.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="submit"><input type="submit" name="ws_update_users_discount" class="button button-primary" value="Salva Sconti Utenti"></p>
                </form>
            </div>
        </div>
        <?php
    }

    // --- HELPER CALCOLO (Identico a prima) ---
    private function get_vip_data( $product ) {
        if ( ! is_user_logged_in() ) return false;
        $user_id = get_current_user_id();

        $global_percent = (float) get_option( 'wsvd_vip_global_percent', 15 );
        $is_cumulative  = get_option( 'wsvd_vip_is_cumulative' );
        $label_text     = get_option( 'wsvd_vip_label', 'Sconto Iscritto' );

        $user_custom_val = get_user_meta( $user_id, '_user_custom_discount', true );
        $discount_percent = ($user_custom_val !== '' && $user_custom_val !== false) ? (float) $user_custom_val : $global_percent;

        if ( $discount_percent <= 0 ) return false;

        if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
            return $this->get_variable_vip_data( $product, $discount_percent, $label_text );
        }

        return $this->get_single_product_vip_data( $product, $discount_percent, $label_text, $is_cumulative );
    }

    private function get_single_product_vip_data( $product, $discount_percent, $label_text, $is_cumulative, $include_unchanged = false ) {
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
            return false;
        }

        $regular_price = (float) $product->get_regular_price();
        $current_price = (float) $product->get_price();

        if ( ! $regular_price ) return false;

        $final_price = $current_price;
        $price_from_regular = $regular_price * ( 1 - ( $discount_percent / 100 ) );

        if ( $is_cumulative ) {
            $final_price = $current_price * ( 1 - ( $discount_percent / 100 ) );
        } else {
            if ( $price_from_regular < $current_price ) $final_price = $price_from_regular;
        }

        $vip_applied = abs( $final_price - $current_price ) >= 0.01;
        if ( ! $vip_applied && ! $include_unchanged ) return false;

        $discount_percent = Utils::calculate_percent_from_prices( $regular_price, $final_price );

        return [
            'final_price'  => $final_price,
            'final_min'    => $final_price,
            'final_max'    => $final_price,
            'regular_price'=> $regular_price,
            'regular_min'  => $regular_price,
            'regular_max'  => $regular_price,
            'percent'      => $discount_percent,
            'label'        => $label_text,
            'is_range'     => false,
            'vip_applied'  => $vip_applied,
        ];
    }

    private function get_variable_vip_data( $product, $discount_percent, $label_text ) {
        $is_cumulative = get_option( 'wsvd_vip_is_cumulative' );
        $variation_ids = method_exists( $product, 'get_visible_children' ) ? $product->get_visible_children() : [];

        if ( empty( $variation_ids ) && method_exists( $product, 'get_children' ) ) {
            $variation_ids = $product->get_children();
        }

        if ( empty( $variation_ids ) ) {
            return false;
        }

        $regular_prices = [];
        $final_prices   = [];
        $percents       = [];
        $has_vip_change = false;

        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation ) {
                continue;
            }

            $vip = $this->get_single_product_vip_data( $variation, $discount_percent, $label_text, $is_cumulative, true );
            if ( ! $vip ) {
                continue;
            }

            $regular_prices[] = (float) $vip['regular_price'];
            $final_prices[]   = (float) $vip['final_price'];
            if ( ! empty( $vip['vip_applied'] ) ) {
                $percents[] = (float) $vip['percent'];
                $has_vip_change = true;
            }
        }

        if ( empty( $final_prices ) || empty( $regular_prices ) || ! $has_vip_change ) {
            return false;
        }

        $regular_min = min( $regular_prices );
        $regular_max = max( $regular_prices );
        $final_min   = min( $final_prices );
        $final_max   = max( $final_prices );

        return [
            'final_price'   => $final_min,
            'final_min'     => $final_min,
            'final_max'     => $final_max,
            'regular_price' => $regular_min,
            'regular_min'   => $regular_min,
            'regular_max'   => $regular_max,
            'percent'       => max( $percents ),
            'label'         => $label_text,
            'is_range'      => abs( $final_max - $final_min ) >= 0.01 || abs( $regular_max - $regular_min ) >= 0.01,
        ];
    }

    public function format_price_frontend( $price, $product ) {
        if ( is_admin() ) return $price;
        $vip = $this->get_vip_data( $product );
        if ( $vip ) {
            return $this->build_price_html( $vip );
        }
        return $price;
    }

    private function build_price_html( $vip ) {
        if ( ! empty( $vip['is_range'] ) ) {
            $regular_html = wc_format_price_range( $vip['regular_min'], $vip['regular_max'] );
            $final_html   = wc_format_price_range( $vip['final_min'], $vip['final_max'] );

            return '<del aria-hidden="true">' . $regular_html . '</del> <ins>' . $final_html . '</ins> <span class="vip-label" style="font-size:0.8em;opacity:0.8;display:block;">' . esc_html( $vip['label'] ) . '</span>';
        }

        return wc_format_sale_price( $vip['regular_price'], $vip['final_price'] ) . ' <span class="vip-label" style="font-size:0.8em;opacity:0.8;display:block;">' . esc_html( $vip['label'] ) . '</span>';
    }

    public function force_sale_badge( $is_on_sale, $product ) {
        if ( is_admin() ) return $is_on_sale;
        if ( $is_on_sale ) return true;
        return (bool) $this->get_vip_data( $product );
    }

    public function custom_sale_flash( $html, $post, $product ) {
        if ( is_admin() ) return $html;
        $vip = $this->get_vip_data( $product );
        if ( ! $vip ) {
            return $html;
        }

        if ( $this->is_flatsome_theme() ) {
            return '';
        }

        if ( ! $product->is_on_sale() ) {
            return '<span class="onsale">- ' . round( $vip['percent'] ) . '%</span>';
        }

        return $html;
    }

    public function filter_available_variation( $data, $product, $variation ) {
        if ( is_admin() || ! is_array( $data ) || ! is_object( $variation ) ) {
            return $data;
        }

        $vip = $this->get_vip_data( $variation );
        if ( ! $vip ) {
            return $data;
        }

        $data['display_price'] = (float) $vip['final_price'];
        $data['display_regular_price'] = (float) $vip['regular_price'];
        $data['price_html'] = $this->build_price_html( $vip );

        return $data;
    }

    public function custom_flatsome_product_labels( $text, $post = null, $product = null, $badge_style = null ) {
        if ( is_admin() || ! is_object( $product ) ) {
            return $text;
        }

        $vip = $this->get_vip_data( $product );
        if ( ! $vip ) {
            return $text;
        }

        $badge_text = '- ' . round( $vip['percent'] ) . '%';
        $updated_text = $this->inject_flatsome_sale_badge_value( $text, $badge_text );

        if ( null !== $updated_text ) {
            return $updated_text;
        }

        $badge = $this->build_flatsome_sale_badge_html( $vip['percent'], $badge_style );

        return $this->prepend_flatsome_badge( $text, $badge );
    }

    private function build_flatsome_sale_badge_html( $percent, $badge_style = null ) {
        $badge_style = $this->normalize_flatsome_badge_style( $badge_style );

        return '<div class="callout badge badge-' . esc_attr( $badge_style ) . '"><div class="badge-inner secondary on-sale"><span class="onsale">- ' . esc_html( round( $percent ) ) . '%</span></div></div>';
    }

    private function inject_flatsome_sale_badge_value( $html, $badge_text ) {
        if ( '' === trim( (string) $html ) ) {
            return null;
        }

        $badge_html = '<span class="onsale">' . esc_html( $badge_text ) . '</span>';

        $updated_html = preg_replace(
            '#(<div\b(?=[^>]*(?:\bon-sale\b|\bonsale-bubble\b))[^>]*>).*?(</div>)#is',
            '$1' . $badge_html . '$2',
            $html,
            1,
            $count
        );

        if ( null !== $updated_html && $count > 0 ) {
            return $updated_html;
        }

        $updated_html = preg_replace(
            '#(<span\b[^>]*\bonsale\b[^>]*>).*?(</span>)#is',
            '$1' . esc_html( $badge_text ) . '$2',
            $html,
            1,
            $count
        );

        if ( null !== $updated_html && $count > 0 ) {
            return $updated_html;
        }

        return null;
    }

    private function prepend_flatsome_badge( $html, $badge ) {
        if ( false === strpos( (string) $html, 'badge-container' ) ) {
            return $badge . $html;
        }

        $merged_html = preg_replace(
            '#(<div\b[^>]*\bbadge-container\b[^>]*>)#i',
            '$1' . $badge,
            $html,
            1
        );

        return null === $merged_html ? $badge . $html : $merged_html;
    }

    private function normalize_flatsome_badge_style( $badge_style ) {
        if ( empty( $badge_style ) ) {
            $badge_style = get_theme_mod( 'bubble_style', 'style1' );
        }

        if ( 'style1' === $badge_style ) {
            return 'circle';
        }

        if ( 'style2' === $badge_style ) {
            return 'square';
        }

        if ( 'style3' === $badge_style ) {
            return 'frame';
        }

        return sanitize_html_class( $badge_style, 'circle' );
    }

    private function is_flatsome_theme() {
        return defined( 'FLATSOME_VERSION' ) || 'flatsome' === get_template();
    }

    public function apply_cart_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( ! is_user_logged_in() || ! empty( $cart->get_applied_coupons() ) ) return;
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( $vip = $this->get_vip_data( $cart_item['data'] ) ) $cart_item['data']->set_price( $vip['final_price'] );
        }
    }

    public function display_cart_label() {
        if ( ! is_user_logged_in() || ! isset( WC()->cart ) || ! empty( WC()->cart->get_applied_coupons() ) ) return;
        $label = get_option( 'wsvd_vip_label', 'Sconto Iscritto' );
        echo '<tr class="cart-discount"><th>' . esc_html( $label ) . '</th><td>Listino riservato</td></tr>';
    }
}
