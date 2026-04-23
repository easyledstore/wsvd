<?php
namespace WSVD\Modules;

use WSVD\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module_Auto_Coupons implements Module_Interface {

	public function register() {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'woocommerce_coupon_options', [ $this, 'coupon_field' ] );
		add_action( 'woocommerce_coupon_options_save', [ $this, 'coupon_field_save' ] );
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

		$auto_coupons = get_posts(
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
}
