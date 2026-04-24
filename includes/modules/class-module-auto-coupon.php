<?php
namespace WSVD\Modules;

use WSVD\Module_Interface;
use Automattic\WooCommerce\Internal\Orders\CouponsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module_Auto_Coupons implements Module_Interface {

	public function register() {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'woocommerce_coupon_options', [ $this, 'coupon_field' ] );
		add_action( 'woocommerce_coupon_options_save', [ $this, 'coupon_field_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'render_admin_order_button' ] );
		add_action( 'wp_ajax_wsvd_apply_customer_auto_coupons', [ $this, 'ajax_apply_customer_auto_coupons' ] );
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_checkout_email' ] );
		add_action( 'template_redirect', [ $this, 'maybe_auto_apply' ] );
	}

	public function add_submenu() {
		add_submenu_page(
			'wsvd-main',
			'Coupon automatici',
			'Coupon automatici',
			'manage_options',
			'wsvd-auto-coupons',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		$coupons = get_posts(
			[
				'post_type'   => 'shop_coupon',
				'post_status' => 'publish',
				'meta_key'    => '_wsvd_auto_apply',
				'meta_value'  => 'yes',
				'numberposts' => -1,
			]
		);
		?>
		<div class="wrap">
			<h1>Gestione coupon automatici</h1>
			<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:20px;width:100%;box-sizing:border-box;">
				<h3>Come funziona</h3>
				<p>Per creare un coupon automatico:</p>
				<ol>
					<li>Vai su <strong>Marketing &gt; Coupon</strong>.</li>
					<li>Crea o modifica un coupon.</li>
					<li>Nella scheda generale, attiva <strong>"Applica automaticamente"</strong>.</li>
					<li>Nella scheda restrizioni, inserisci le <strong>email consentite</strong>.</li>
				</ol>

				<hr>

				<h3>Coupon automatici attivi</h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Codice coupon</th>
							<th>Descrizione</th>
							<th>Email consentite</th>
							<th>Valore</th>
							<th>Azioni</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! empty( $coupons ) ) : ?>
						<?php foreach ( $coupons as $post ) :
							$coupon = new \WC_Coupon( $post->ID );
							$emails = $coupon->get_email_restrictions();
							?>
							<tr>
								<td><strong><?php echo esc_html( $coupon->get_code() ); ?></strong></td>
								<td><?php echo esc_html( $coupon->get_description() ?: '-' ); ?></td>
								<td>
									<?php if ( empty( $emails ) ) : ?>
										<span style="color:red;">Nessuna, attenzione</span>
									<?php else : ?>
										<code><?php echo esc_html( implode( ', ', $emails ) ); ?></code>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $coupon->get_amount() ); ?>
									<?php echo ( 'percent' === $coupon->get_discount_type() ) ? '%' : esc_html( get_woocommerce_currency_symbol() ); ?>
								</td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-small">Modifica</a>
									<details style="margin-top:8px;">
										<summary style="cursor:pointer;">Visualizza dettagli</summary>
										<div style="margin-top:8px;line-height:1.6;">
											<div><strong>Tipo sconto:</strong> <?php echo esc_html( $coupon->get_discount_type() ?: '-' ); ?></div>
											<div><strong>Scadenza:</strong> <?php echo esc_html( $coupon->get_date_expires() ? $coupon->get_date_expires()->date_i18n( 'd/m/Y' ) : '-' ); ?></div>
											<div><strong>Limite utilizzi:</strong> <?php echo esc_html( $coupon->get_usage_limit() ? $coupon->get_usage_limit() : '-' ); ?></div>
											<div><strong>Utilizzi per utente:</strong> <?php echo esc_html( $coupon->get_usage_limit_per_user() ? $coupon->get_usage_limit_per_user() : '-' ); ?></div>
											<div><strong>Spesa minima:</strong> <?php echo esc_html( $coupon->get_minimum_amount() ?: '-' ); ?></div>
											<div><strong>Spesa massima:</strong> <?php echo esc_html( $coupon->get_maximum_amount() ?: '-' ); ?></div>
										</div>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5">Nessun coupon automatico attivo al momento.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public function coupon_field() {
		woocommerce_wp_checkbox(
			[
				'id'          => '_wsvd_auto_apply',
				'label'       => __( 'Applica automaticamente', 'wsvd' ),
				'description' => __( 'Applica automaticamente se l\'email corrisponde alle restrizioni.', 'wsvd' ),
			]
		);
	}

	public function coupon_field_save( $post_id ) {
		$val = isset( $_POST['_wsvd_auto_apply'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wsvd_auto_apply', $val );
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! $this->is_order_admin_screen( $screen->id ) ) {
			return;
		}

		wp_enqueue_script(
			'wsvd-admin',
			WSVD_URL . 'admin/js/wsvd-admin.js',
			[ 'jquery' ],
			defined( 'WSVD_VERSION' ) ? WSVD_VERSION : '1.2.0',
			true
		);

		wp_localize_script(
			'wsvd-admin',
			'wsvdAdminAutoCoupons',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wsvd_apply_customer_auto_coupons' ),
				'missingEmail' => __( 'Inserisci prima l\'email del cliente.', 'wsvd' ),
				'genericError' => __( 'Non sono riuscito ad applicare i coupon automatici.', 'wsvd' ),
			]
		);
	}

	public function render_admin_order_button( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		?>
		<button
			type="button"
			class="button wsvd-apply-customer-coupons"
			data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
		>
			<?php esc_html_e( 'Applica automaticamente i coupon del cliente', 'wsvd' ); ?>
		</button>
		<span class="spinner wsvd-apply-customer-coupons-spinner" style="float:none;margin:0 6px 0 0;"></span>
		<?php
	}

	public function ajax_apply_customer_auto_coupons() {
		check_ajax_referer( 'wsvd_apply_customer_auto_coupons', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Non hai i permessi per modificare gli ordini.', 'wsvd' ),
				],
				403
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error(
				[
					'message' => __( 'Ordine non trovato.', 'wsvd' ),
				],
				404
			);
		}

		if ( ! $email ) {
			$email = $order->get_billing_email();
		}

		if ( ! $email ) {
			wp_send_json_error(
				[
					'message' => __( 'Inserisci prima l\'email del cliente.', 'wsvd' ),
				],
				400
			);
		}

		$taxable_address = [
			'country'  => isset( $_POST['country'] ) ? wc_clean( wp_unslash( $_POST['country'] ) ) : '',
			'state'    => isset( $_POST['state'] ) ? wc_clean( wp_unslash( $_POST['state'] ) ) : '',
			'postcode' => isset( $_POST['postcode'] ) ? wc_clean( wp_unslash( $_POST['postcode'] ) ) : '',
			'city'     => isset( $_POST['city'] ) ? wc_clean( wp_unslash( $_POST['city'] ) ) : '',
		];
		$result          = $this->apply_customer_email_coupons_with_native_logic( $order, $email, $taxable_address );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				],
				400
			);
		}

		wp_send_json_success(
			[
				'html'       => $result['html'],
				'notes_html' => $result['notes_html'],
			]
		);
	}

	public function capture_checkout_email( $post_data ) {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		parse_str( $post_data, $data );

		if ( ! empty( $data['billing_email'] ) ) {
			WC()->session->set( 'wsvd_checkout_email', sanitize_email( $data['billing_email'] ) );
		}
	}

	public function maybe_auto_apply() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! ( is_cart() || is_checkout() ) ) {
			return;
		}

		$email = '';

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$email = $user->user_email;
		} elseif ( isset( WC()->session ) ) {
			$email = WC()->session->get( 'wsvd_checkout_email' );
		}

		if ( ! $email ) {
			return;
		}

		$auto_coupons = $this->get_auto_coupon_ids();

		if ( empty( $auto_coupons ) ) {
			return;
		}

		$applied = WC()->cart->get_applied_coupons();

		foreach ( $applied as $code ) {
			$coupon = new \WC_Coupon( $code );
			if ( get_post_meta( $coupon->get_id(), '_wsvd_auto_apply', true ) === 'yes' ) {
				if ( ! $this->email_allowed( $coupon, $email ) || ! $coupon->is_valid() ) {
					WC()->cart->remove_coupon( $code );
				}
			}
		}

		foreach ( $auto_coupons as $id ) {
			$coupon = new \WC_Coupon( $id );

			if ( ! in_array( wc_strtolower( $coupon->get_code() ), array_map( 'wc_strtolower', $applied ), true ) ) {
				if ( $this->email_allowed( $coupon, $email ) && $coupon->is_valid() ) {
					WC()->cart->add_discount( $coupon->get_code() );
				}
			}
		}
	}

	private function email_allowed( \WC_Coupon $coupon, $email ) {
		$allowed = $coupon->get_email_restrictions();

		if ( empty( $allowed ) ) {
			return false;
		}

		foreach ( $allowed as $pattern ) {
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( strtolower( $pattern ), '/' ) ) . '$/i';
			if ( preg_match( $regex, strtolower( $email ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_order_admin_screen( $screen_id ) {
		if ( ! is_string( $screen_id ) || '' === $screen_id ) {
			return false;
		}

		return false !== strpos( $screen_id, 'shop_order' ) || false !== strpos( $screen_id, 'woocommerce_page_wc-orders' );
	}

	private function get_auto_coupon_ids() {
		return get_posts(
			[
				'post_type'  => 'shop_coupon',
				'fields'     => 'ids',
				'meta_query' => [
					[
						'key'   => '_wsvd_auto_apply',
						'value' => 'yes',
					],
				],
			]
		);
	}

	private function get_email_restricted_coupon_ids() {
		return get_posts(
			[
				'post_type'   => 'shop_coupon',
				'post_status' => 'publish',
				'fields'      => 'ids',
				'numberposts' => -1,
			]
		);
	}

	private function find_customer_email_coupon_codes( $email ) {
		$coupon_ids = $this->get_email_restricted_coupon_ids();

		if ( empty( $coupon_ids ) ) {
			return [
				'matched' => [],
			];
		}

		$matched_codes = [];

		foreach ( $coupon_ids as $coupon_id ) {
			$coupon = new \WC_Coupon( $coupon_id );
			$code   = $coupon->get_code();

			if ( ! $this->email_allowed( $coupon, $email ) ) {
				continue;
			}

			$matched_codes[] = $code;
		}

		return [
			'matched' => $matched_codes,
		];
	}

	private function apply_customer_email_coupons_with_native_logic( \WC_Order $order, $email, array $taxable_address ) {
		$matched_codes  = $this->find_customer_email_coupon_codes( $email );
		$coupon_codes   = isset( $matched_codes['matched'] ) ? $matched_codes['matched'] : [];
		$applied_codes  = [];

		foreach ( $order->get_items( 'coupon' ) as $item ) {
			if ( is_callable( [ $item, 'get_code' ] ) ) {
				$applied_codes[] = wc_strtolower( $item->get_code() );
			}
		}

		if ( empty( $coupon_codes ) ) {
			return [
				'html'       => $this->render_order_items_html( $order ),
				'notes_html' => $this->render_order_notes_html( $order ),
			];
		}

		foreach ( $coupon_codes as $coupon_code ) {
			if ( in_array( wc_strtolower( $coupon_code ), $applied_codes, true ) ) {
				continue;
			}

			$result = $this->apply_single_coupon_with_native_logic(
				[
					'order_id'   => $order->get_id(),
					'coupon'     => $coupon_code,
					'user_id'    => $order->get_customer_id(),
					'user_email' => $email,
					'country'    => $taxable_address['country'],
					'state'      => $taxable_address['state'],
					'postcode'   => $taxable_address['postcode'],
					'city'       => $taxable_address['city'],
				]
			);

			if ( is_wp_error( $result ) ) {
				continue;
			}

			$order         = $result;
			$applied_codes[] = wc_strtolower( $coupon_code );
		}

		return [
			'html'       => $this->render_order_items_html( $order ),
			'notes_html' => $this->render_order_notes_html( $order ),
		];
	}

	private function apply_single_coupon_with_native_logic( array $payload ) {
		if ( class_exists( CouponsController::class ) && function_exists( 'wc_get_container' ) ) {
			try {
				return wc_get_container()->get( CouponsController::class )->add_coupon_discount( $payload );
			} catch ( \Exception $e ) {
				return new \WP_Error( 'wsvd_coupon_apply_failed', $e->getMessage() );
			}
		}

		$order = wc_get_order( isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0 );

		if ( ! $order ) {
			return new \WP_Error( 'wsvd_invalid_order', __( 'Ordine non valido.', 'wsvd' ) );
		}

		if ( ! empty( $payload['user_id'] ) ) {
			$order->set_customer_id( absint( $payload['user_id'] ) );
		}

		if ( ! empty( $payload['user_email'] ) ) {
			$order->set_billing_email( sanitize_email( $payload['user_email'] ) );
		}

		$order->calculate_taxes(
			[
				'country'  => isset( $payload['country'] ) ? wc_strtoupper( $payload['country'] ) : '',
				'state'    => isset( $payload['state'] ) ? wc_strtoupper( $payload['state'] ) : '',
				'postcode' => isset( $payload['postcode'] ) ? wc_strtoupper( $payload['postcode'] ) : '',
				'city'     => isset( $payload['city'] ) ? wc_strtoupper( $payload['city'] ) : '',
			]
		);
		$order->calculate_totals( false );

		$result = $order->apply_coupon( wc_format_coupon_code( $payload['coupon'] ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $order;
	}

	private function render_order_items_html( \WC_Order $order ) {
		ob_start();
		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
		return ob_get_clean();
	}

	private function render_order_notes_html( \WC_Order $order ) {
		ob_start();
		$notes = wc_get_order_notes( [ 'order_id' => $order->get_id() ] );
		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
		return ob_get_clean();
	}
}
