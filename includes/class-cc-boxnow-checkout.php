<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CC_BoxNow_Checkout {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',                      array( $this, 'enqueue_assets' ) );

        // Classic checkout
        add_action( 'woocommerce_review_order_after_shipping', array( $this, 'render_widget_html_div' ) );
        add_action( 'woocommerce_checkout_process',            array( $this, 'validate_locker_selection' ) );
        add_action( 'woocommerce_checkout_update_order_meta',  array( $this, 'save_locker_meta' ) );

        // Block checkout
        add_action( 'wp_footer',                               array( $this, 'render_widget_for_block_checkout' ) );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_locker_meta_blocks' ), 10, 2 );

        // AJAX session save (block checkout)
        add_action( 'wp_ajax_cc_boxnow_save_session',          array( $this, 'ajax_save_session' ) );
        add_action( 'wp_ajax_nopriv_cc_boxnow_save_session',   array( $this, 'ajax_save_session' ) );
    }

    private function is_block_checkout() {
        global $post;
        return $post && has_block( 'woocommerce/checkout', $post->post_content );
    }

    public function enqueue_assets() {
        if ( ! is_checkout() ) return;

        wp_enqueue_style(
            'cc-boxnow-checkout',
            CC_WC_PLUGIN_URL . 'assets/css/cc-boxnow-checkout.css',
            array(),
            CC_WC_VERSION
        );

        wp_enqueue_script(
            'cc-boxnow-checkout',
            CC_WC_PLUGIN_URL . 'assets/js/cc-boxnow-checkout.js',
            array( 'jquery' ),
            CC_WC_VERSION,
            true
        );

        wp_localize_script( 'cc-boxnow-checkout', 'ccBoxNow', array(
            'partnerId'       => 10853,
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'sessionNonce'    => wp_create_nonce( 'cc_boxnow_session_nonce' ),
            'isBlockCheckout' => $this->is_block_checkout() ? '1' : '0',
        ) );
    }

    public function render_widget_html_div() {
        $enabled = get_option( 'cc_wc_boxnow_enabled', '0' );
        if ( $enabled !== '1' ) return;
        if ( $this->is_block_checkout() ) return;
        ?>
        <tr class="cc-boxnow-addon-row">
            <td colspan="2" class="cc-boxnow-addon-cell">
                <?php $this->render_widget_inner(); ?>
            </td>
        </tr>
        <?php
    }

    public function render_widget_for_block_checkout() {
        if ( ! is_checkout() ) return;
        if ( ! $this->is_block_checkout() ) return;
        $enabled = get_option( 'cc_wc_boxnow_enabled', '0' );
        if ( $enabled !== '1' ) return;
        ?>
        <div id="cc-boxnow-block-wrapper" style="display:none;">
            <?php $this->render_widget_inner(); ?>
        </div>
        <?php
    }

    private function render_widget_inner() {
        ?>
        <div id="cc-boxnow-widget-wrap">

            <?php wp_nonce_field( 'cc_boxnow_locker_nonce', '_cc_boxnow_nonce' ); ?>

            <input type="hidden" id="cc_boxnow_locker_id"     name="cc_boxnow_locker_id"     value="">
            <input type="hidden" id="cc_boxnow_locker_code"   name="cc_boxnow_locker_code"   value="">
            <input type="hidden" id="cc_boxnow_locker_name"   name="cc_boxnow_locker_name"   value="">
            <input type="hidden" id="cc_boxnow_delivery_mode" name="cc_boxnow_delivery_mode" value="">

            <!-- Toggle -->
            <label id="cc-boxnow-toggle-label" class="cc-boxnow-toggle-label">
                <input type="checkbox" id="cc-boxnow-toggle" name="cc_boxnow_selected" value="1">
                <img src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/img/boxnow-logo.png' ); ?>" alt="BOX NOW" class="cc-boxnow-logo">
                <span class="cc-boxnow-toggle-text">
                    <?php esc_html_e( 'Παράδοση σε', 'courier-center-woocommerce' ); ?>
                    <strong><?php esc_html_e( 'BOX NOW Locker', 'courier-center-woocommerce' ); ?></strong>
                </span>
                <span class="cc-boxnow-toggle-arrow">›</span>
            </label>

            <!-- Επιλογές -->
            <div id="cc-boxnow-options" style="display:none;">

                <div id="cc-boxnow-mode-select" class="cc-boxnow-mode-select">
                    <p class="cc-boxnow-mode-label"><?php esc_html_e( 'Πώς θέλεις να παραλάβεις;', 'courier-center-woocommerce' ); ?></p>
                    <div class="cc-boxnow-mode-buttons">
                        <button type="button" id="cc-boxnow-auto-btn" class="cc-boxnow-mode-btn">
                            <span class="cc-bn-btn-text">
                                <strong><?php esc_html_e( 'Αυτόματη εύρεση', 'courier-center-woocommerce' ); ?></strong>
                                <small><?php esc_html_e( 'Κοντινότερο διαθέσιμο Locker', 'courier-center-woocommerce' ); ?></small>
                            </span>
                        </button>
                        <button type="button" id="cc-boxnow-pick-btn" class="cc-boxnow-mode-btn cc-boxnow-mode-btn--primary">
                            <span class="cc-bn-btn-text">
                                <strong><?php esc_html_e( 'Επιλογή από χάρτη', 'courier-center-woocommerce' ); ?></strong>
                                <small><?php esc_html_e( 'Διάλεξε συγκεκριμένο Locker', 'courier-center-woocommerce' ); ?></small>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Auto confirm -->
                <div id="cc-boxnow-auto-confirm" style="display:none;" class="cc-boxnow-confirm-wrap cc-boxnow-auto-confirm">
                    <svg class="cc-bn-check-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="10" fill="#5DC122"/><path d="M5.5 10.5l3 3 6-6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span><?php esc_html_e( 'Θα παραδοθεί στο κοντινότερο διαθέσιμο BOX NOW Locker', 'courier-center-woocommerce' ); ?></span>
                    <button type="button" id="cc-boxnow-auto-change-btn" class="cc-boxnow-change-btn">
                        <?php esc_html_e( 'Αλλαγή', 'courier-center-woocommerce' ); ?>
                    </button>
                </div>

                <!-- Pick confirm -->
                <div id="cc-boxnow-selected-info" style="display:none;" class="cc-boxnow-selected-info">
                    <svg class="cc-bn-check-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="10" fill="#5DC122"/><path d="M5.5 10.5l3 3 6-6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span id="cc-boxnow-selected-name"></span>
                    <button type="button" id="cc-boxnow-change-btn" class="cc-boxnow-change-btn">
                        <?php esc_html_e( 'Αλλαγή', 'courier-center-woocommerce' ); ?>
                    </button>
                </div>

            </div>

        </div>

        <!-- Modal Locker Map -->
        <div id="cc-boxnow-modal-overlay">
            <div id="cc-boxnow-modal" role="dialog" aria-modal="true">
                <div id="cc-boxnow-modal-header">
                    <img src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/img/boxnow-logo.png' ); ?>" alt="BOX NOW" class="cc-boxnow-modal-logo">
                    <span><?php esc_html_e( 'Επιλογή Locker', 'courier-center-woocommerce' ); ?></span>
                    <button type="button" id="cc-boxnow-modal-close" aria-label="<?php esc_attr_e( 'Κλείσιμο', 'courier-center-woocommerce' ); ?>">
                        <svg viewBox="0 0 14 14" fill="none"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <a href="javascript:;" class="boxnow-map-widget-button" aria-hidden="true" tabindex="-1"></a>
                <div id="boxnowmap"></div>
            </div>
        </div>

        <?php
    }

    public function ajax_save_session() {
        if ( ! isset( $_POST['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cc_boxnow_session_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
            return;
        }

        if ( ! WC()->session ) {
            wp_send_json_error( array( 'message' => 'No session' ) );
            return;
        }

        WC()->session->set( 'cc_boxnow_selection', array(
            'selected'      => ! empty( $_POST['selected'] ),
            'delivery_mode' => isset( $_POST['delivery_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_mode'] ) ) : '',
            'locker_id'     => isset( $_POST['locker_id'] )     ? sanitize_text_field( wp_unslash( $_POST['locker_id'] ) )     : '',
            'locker_code'   => isset( $_POST['locker_code'] )   ? sanitize_text_field( wp_unslash( $_POST['locker_code'] ) )   : '',
            'locker_name'   => isset( $_POST['locker_name'] )   ? sanitize_text_field( wp_unslash( $_POST['locker_name'] ) )   : '',
        ) );

        wp_send_json_success();
    }

    public function save_locker_meta_blocks( $order, $request ) {
        if ( ! WC()->session ) return;
        $data = WC()->session->get( 'cc_boxnow_selection' );
        if ( empty( $data ) || empty( $data['selected'] ) ) return;

        $order->update_meta_data( '_boxnow_delivery_mode', $data['delivery_mode'] );

        if ( ! empty( $data['locker_id'] ) ) {
            $order->update_meta_data( '_boxnow_locker_id',   $data['locker_id'] );
            $order->update_meta_data( '_boxnow_locker_code', $data['locker_code'] ?: $data['locker_id'] );
            $order->update_meta_data( '_boxnow_locker_name', $data['locker_name'] );
        }

        WC()->session->set( 'cc_boxnow_selection', null );
    }

    public function validate_locker_selection() {
        if ( empty( $_POST['cc_boxnow_selected'] ) ) return;

        if ( ! isset( $_POST['_cc_boxnow_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cc_boxnow_nonce'] ) ), 'cc_boxnow_locker_nonce' ) ) {
            wc_add_notice( __( 'Σφάλμα ασφαλείας. Παρακαλώ ανανεώστε τη σελίδα.', 'courier-center-woocommerce' ), 'error' );
            return;
        }

        $delivery_mode = isset( $_POST['cc_boxnow_delivery_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_boxnow_delivery_mode'] ) ) : '';

        if ( empty( $delivery_mode ) ) {
            wc_add_notice( __( 'Παρακαλώ επιλέξτε τρόπο παράδοσης BOX NOW.', 'courier-center-woocommerce' ), 'error' );
            return;
        }

        if ( $delivery_mode === 'pick' && empty( $_POST['cc_boxnow_locker_id'] ) ) {
            wc_add_notice( __( 'Παρακαλώ επιλέξτε locker BOX NOW από τον χάρτη.', 'courier-center-woocommerce' ), 'error' );
        }
    }

    public function save_locker_meta( $order_id ) {
        if ( empty( $_POST['cc_boxnow_selected'] ) ) return;

        $locker_id     = isset( $_POST['cc_boxnow_locker_id'] )     ? sanitize_text_field( wp_unslash( $_POST['cc_boxnow_locker_id'] ) )     : '';
        $locker_code   = isset( $_POST['cc_boxnow_locker_code'] )   ? sanitize_text_field( wp_unslash( $_POST['cc_boxnow_locker_code'] ) )   : '';
        $locker_name   = isset( $_POST['cc_boxnow_locker_name'] )   ? sanitize_text_field( wp_unslash( $_POST['cc_boxnow_locker_name'] ) )   : '';
        $delivery_mode = isset( $_POST['cc_boxnow_delivery_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_boxnow_delivery_mode'] ) ) : '';

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $order->update_meta_data( '_boxnow_delivery_mode', $delivery_mode );

        if ( ! empty( $locker_id ) ) {
            $order->update_meta_data( '_boxnow_locker_id',   $locker_id );
            $order->update_meta_data( '_boxnow_locker_code', $locker_code ?: $locker_id );
            $order->update_meta_data( '_boxnow_locker_name', $locker_name );
        }

        $order->save();
    }

    // Backward compat — δεν χρησιμοποιούνται πλέον
    public function get_boxnow_method_ids() { return array(); }
    public function is_boxnow_method_selected() { return false; }
}
