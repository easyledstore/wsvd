<?php
namespace WSVD\Modules;

use WSVD\Helpers\Utils;
use WSVD\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module_Piece_Pricing implements Module_Interface {

	public function register() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'woocommerce_get_price_html', [ $this, 'format_piece_price_shop' ], 999, 2 );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'wsvd-main',
			'Prezzo per pezzo',
			'Prezzo per pezzo',
			'manage_options',
			'wsvd-piece-pricing',
			[ $this, 'render_admin_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'wsvd_piece_group', 'wsvd_piece_pricing_enabled', [ 'sanitize_callback' => [ $this, 'sanitize_checkbox' ] ] );
		register_setting( 'wsvd_piece_group', 'wsvd_piece_pricing_attribute', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'wsvd_piece_group', 'wsvd_piece_pricing_suffix', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'wsvd_piece_group', 'wsvd_piece_pricing_mode', [ 'sanitize_callback' => [ $this, 'sanitize_mode' ] ] );
	}

	public function render_admin_page() {
		$enabled   = get_option( 'wsvd_piece_pricing_enabled', '1' );
		$attribute = get_option( 'wsvd_piece_pricing_attribute', 'pezzi-confezione' );
		$suffix    = get_option( 'wsvd_piece_pricing_suffix', '/ pezzo' );
		$mode      = get_option( 'wsvd_piece_pricing_mode', 'replace' );
		?>
		<div class="wrap">
			<h1>Prezzo per pezzo</h1>
			<p>Gestisci la visualizzazione del prezzo calcolato sul numero di pezzi per confezione nel catalogo WooCommerce.</p>
			<p><strong>Nota:</strong> il prezzo per pezzo usa il prezzo finale dell'utente corrente, quindi rispetta eventuali sconti VIP o iscritti prima di dividere per i pezzi.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wsvd_piece_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Funzionalita attiva</th>
						<td>
							<label>
								<input type="checkbox" name="wsvd_piece_pricing_enabled" value="1" <?php checked( '1', $enabled ); ?>>
								Abilita il prezzo per pezzo nel catalogo
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Attributo prodotto</th>
						<td>
							<input type="text" class="regular-text" name="wsvd_piece_pricing_attribute" value="<?php echo esc_attr( $attribute ); ?>">
							<p class="description">Inserisci lo slug dell'attributo usato per indicare i pezzi per confezione.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Suffisso prezzo</th>
						<td>
							<input type="text" class="regular-text" name="wsvd_piece_pricing_suffix" value="<?php echo esc_attr( $suffix ); ?>">
							<p class="description">Testo mostrato dopo il prezzo, ad esempio "/ pezzo".</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Modalita visualizzazione</th>
						<td>
							<select name="wsvd_piece_pricing_mode">
								<option value="replace" <?php selected( 'replace', $mode ); ?>>Sostituisci il prezzo con il prezzo per pezzo</option>
								<option value="append" <?php selected( 'append', $mode ); ?>>Aggiungi il prezzo per pezzo sotto al prezzo</option>
								<option value="stack" <?php selected( 'stack', $mode ); ?>>Mostra prezzo base e prezzo per pezzo su due righe</option>
							</select>
							<p class="description">Scegli come presentare il prezzo nel catalogo quando la funzionalita e attiva.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Salva impostazioni' ); ?>
			</form>
		</div>
		<?php
	}

	public function format_piece_price_shop( $price_html, $product ) {
		if ( '1' !== (string) get_option( 'wsvd_piece_pricing_enabled', '1' ) ) {
			return $price_html;
		}

		if ( is_admin() || is_product() ) {
			return $price_html;
		}

		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_attribute' ) ) {
			return $price_html;
		}

		$piece_pricing = $this->get_piece_pricing_data( $product );
		if ( ! $piece_pricing ) {
			return $price_html;
		}

		$suffix = get_option( 'wsvd_piece_pricing_suffix', '/ pezzo' );
		$mode = get_option( 'wsvd_piece_pricing_mode', 'replace' );
		$piece_html = '<span class="wsvd-piece-pricing">' . $this->format_piece_price_value( $piece_pricing ) . ' ' . esc_html( $suffix ) . '</span>';

		if ( 'append' === $mode ) {
			return $price_html . '<br>' . $piece_html;
		}

		if ( 'stack' === $mode ) {
			return '<span class="wsvd-piece-pricing-stack">' . $price_html . '<br>' . $piece_html . '</span>';
		}

		return $piece_html;
	}

	private function get_piece_pricing_data( $product ) {
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			return $this->get_variable_piece_pricing_data( $product );
		}

		return $this->get_single_piece_pricing_data( $product );
	}

	private function get_single_piece_pricing_data( $product, $fallback_product = null ) {
		$pieces = $this->get_piece_count( $product );
		if ( $pieces <= 0 && $fallback_product ) {
			$pieces = $this->get_piece_count( $fallback_product );
		}

		if ( $pieces <= 0 ) {
			return false;
		}

		$effective_price = Utils::get_effective_price_for_current_user( $product );
		if ( $effective_price <= 0 ) {
			return false;
		}

		$piece_price = $effective_price / $pieces;

		return array(
			'min'      => $piece_price,
			'max'      => $piece_price,
			'is_range' => false,
		);
	}

	private function get_variable_piece_pricing_data( $product ) {
		$variation_ids = method_exists( $product, 'get_visible_children' ) ? $product->get_visible_children() : array();

		if ( empty( $variation_ids ) && method_exists( $product, 'get_children' ) ) {
			$variation_ids = $product->get_children();
		}

		if ( empty( $variation_ids ) ) {
			return false;
		}

		$piece_prices = array();
		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$piece_pricing = $this->get_single_piece_pricing_data( $variation, $product );
			if ( ! $piece_pricing ) {
				continue;
			}

			$piece_prices[] = (float) $piece_pricing['min'];
		}

		if ( empty( $piece_prices ) ) {
			return false;
		}

		$min_price = min( $piece_prices );
		$max_price = max( $piece_prices );

		return array(
			'min'      => $min_price,
			'max'      => $max_price,
			'is_range' => abs( $max_price - $min_price ) >= 0.01,
		);
	}

	private function format_piece_price_value( $piece_pricing ) {
		if ( ! empty( $piece_pricing['is_range'] ) ) {
			return wc_format_price_range( $piece_pricing['min'], $piece_pricing['max'] );
		}

		return wc_price( $piece_pricing['min'] );
	}

	private function get_piece_count( $product ) {
		$attribute = get_option( 'wsvd_piece_pricing_attribute', 'pezzi-confezione' );
		$raw = trim( (string) $product->get_attribute( $attribute ) );

		if ( $raw === '' ) {
			return 0.0;
		}

		$raw = str_replace( array( ' ', ',' ), array( '', '.' ), $raw );
		if ( ! is_numeric( $raw ) ) {
			return 0.0;
		}

		return (float) $raw;
	}

	public function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? '1' : '0';
	}

	public function sanitize_mode( $value ) {
		$allowed = array( 'replace', 'append', 'stack' );
		return in_array( $value, $allowed, true ) ? $value : 'replace';
	}
}
