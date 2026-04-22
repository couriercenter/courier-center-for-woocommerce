<?php
/**
 * Order Meta Box - HPOS compatible with AJAX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Order_Meta_Box {

    public function __construct() {
        // Register meta box - both legacy posts and HPOS specific hooks
        add_action( 'add_meta_boxes_shop_order', array( $this, 'add_meta_box_legacy' ) );
        add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'add_meta_box_hpos' ) );

        // AJAX handlers
        add_action( 'wp_ajax_cc_create_voucher', array( $this, 'ajax_create_voucher' ) );
        add_action( 'wp_ajax_cc_void_shipment', array( $this, 'ajax_void_shipment' ) );
        add_action( 'wp_ajax_cc_update_status', array( $this, 'ajax_update_status' ) );

        // PDF download handler
        add_action( 'admin_post_cc_download_voucher', array( $this, 'download_voucher_pdf' ) );

        // Tracking column — legacy orders UI
        add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_tracking_column' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_tracking_column' ), 10, 2 );

        // Tracking column — HPOS orders UI
        add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_tracking_column' ) );
        add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_tracking_column_hpos' ), 10, 2 );

        // Enqueue admin JS only on order edit pages
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add meta box for legacy posts UI
     */
    public function add_meta_box_legacy() {
        add_meta_box(
            'cc-order-actions',
            '🚚 Courier Center',
            array( $this, 'render_meta_box' ),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Add meta box for HPOS orders UI
     */
    public function add_meta_box_hpos() {
        add_meta_box(
            'cc-order-actions',
            '🚚 Courier Center',
            array( $this, 'render_meta_box' ),
            wc_get_page_screen_id( 'shop-order' ),
            'side',
            'high'
        );
    }

    /**
     * Enqueue inline JS on order edit screens
     */
    public function enqueue_scripts( $hook ) {
        $is_order_screen = false;

        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            global $post;
            if ( $post && $post->post_type === 'shop_order' ) {
                $is_order_screen = true;
            }
        }

        if ( $hook === 'woocommerce_page_wc-orders' ) {
            $is_order_screen = true;
        }

        if ( ! $is_order_screen ) {
            return;
        }

        wp_register_script( 'cc-order-actions', '', array( 'jquery' ), '0.2.0', true );
        wp_enqueue_script( 'cc-order-actions' );

        $inline_js = "
        jQuery(document).ready(function(\$) {

            // Toggle multi-parcel
            \$('#cc-multi-parcel').on('change', function() {
                if (\$(this).is(':checked')) {
                    \$('#cc-parcel-count-wrap').show();
                } else {
                    \$('#cc-parcel-count-wrap').hide();
                    \$('#cc-parcel-count').val(2);
                }
            });

            // Create voucher handler
            \$(document).on('click', '#cc-create-voucher-btn', function(e) {
                e.preventDefault();

                var \$form = \$('#cc-create-voucher-form');
                var \$button = \$('#cc-create-voucher-btn');
                var \$status = \$('#cc-ajax-status');
                var originalText = \$button.text();

                \$button.prop('disabled', true).text('⏳ Δημιουργία...');
                \$status.html('').removeClass('cc-error cc-success');

                \$.ajax({
                    url: ccOrderAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cc_create_voucher',
                        nonce: ccOrderAjax.nonce,
                        order_id: \$form.find('[name=order_id]').val(),
                        service_type: \$form.find('[name=service_type]').val(),
                        boxnow: \$form.find('[name=boxnow]').is(':checked') ? '1' : '0',
                        return_option: \$form.find('[name=return_option]:checked').val() || 'none',
                        parcel_count: \$('#cc-multi-parcel').is(':checked') ? parseInt(\$('#cc-parcel-count').val()) : 1
                    },
                    success: function(response) {
                        if (response.success) {
                            \$status.addClass('cc-success').html('✅ ' + response.data.message);
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            \$button.prop('disabled', false).text(originalText);
                            \$status.addClass('cc-error').html('❌ ' + (response.data.message || 'Σφάλμα'));
                        }
                    },
                    error: function(xhr, status, error) {
                        \$button.prop('disabled', false).text(originalText);
                        \$status.addClass('cc-error').html('❌ AJAX error: ' + error);
                    }
                });
            });

            // Void shipment handler
            \$(document).on('click', '#cc-void-btn', function(e) {
                e.preventDefault();

                if ( ! confirm('⚠️ Είστε σίγουροι ότι θέλετε να ακυρώσετε αυτή την αποστολή;') ) {
                    return;
                }

                var \$button = \$('#cc-void-btn');
                var \$status = \$('#cc-ajax-status');
                var originalText = \$button.text();
                var orderId = new URLSearchParams(window.location.search).get('id') || new URLSearchParams(window.location.search).get('post');

                \$button.prop('disabled', true).text('⏳ Ακύρωση...');
                \$status.html('').removeClass('cc-error cc-success');

                \$.ajax({
                    url: ccOrderAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cc_void_shipment',
                        nonce: ccOrderAjax.nonce,
                        order_id: orderId
                    },
                    success: function(response) {
                        if (response.success) {
                            \$status.addClass('cc-success').html('✅ ' + response.data.message);
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            \$button.prop('disabled', false).text(originalText);
                            \$status.addClass('cc-error').html('❌ ' + (response.data.message || 'Σφάλμα'));
                        }
                    },
                    error: function(xhr, status, error) {
                        \$button.prop('disabled', false).text(originalText);
                        \$status.addClass('cc-error').html('❌ AJAX error: ' + error);
                    }
                });
            });

            // Update status handler
            \$(document).on('click', '#cc-status-btn', function(e) {
                e.preventDefault();

                var \$button = \$('#cc-status-btn');
                var \$status = \$('#cc-ajax-status');
                var originalText = \$button.text();
                var orderId = new URLSearchParams(window.location.search).get('id') || new URLSearchParams(window.location.search).get('post');

                \$button.prop('disabled', true).text('⏳ Ενημέρωση...');
                \$status.html('').removeClass('cc-error cc-success');

                \$.ajax({
                    url: ccOrderAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cc_update_status',
                        nonce: ccOrderAjax.nonce,
                        order_id: orderId
                    },
                    success: function(response) {
                        if (response.success) {
                            \$status.addClass('cc-success').html('✅ ' + response.data.message);
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            \$button.prop('disabled', false).text(originalText);
                            \$status.addClass('cc-error').html('❌ ' + (response.data.message || 'Σφάλμα'));
                        }
                    },
                    error: function(xhr, status, error) {
                        \$button.prop('disabled', false).text(originalText);
                        \$status.addClass('cc-error').html('❌ AJAX error: ' + error);
                    }
                });
            });

        });
        ";

        wp_add_inline_script( 'cc-order-actions', $inline_js );

        wp_localize_script( 'cc-order-actions', 'ccOrderAjax', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cc_create_voucher_nonce' ),
        ) );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WP_Post
            ? wc_get_order( $post_or_order->ID )
            : $post_or_order;

        if ( ! $order ) {
            echo '<p>Δεν βρέθηκε η παραγγελία.</p>';
            return;
        }

        $voucher_number  = $order->get_meta( '_cc_voucher_number' );
        $tracking_number = $order->get_meta( '_cc_tracking_number' );
        $is_voided       = $order->get_meta( '_cc_voided' ) === '1';
        $cc_status       = $order->get_meta( '_cc_shipment_status' );
        $cc_status_desc  = $order->get_meta( '_cc_shipment_status_desc' );
        $cc_status_date  = $order->get_meta( '_cc_status_updated_at' );

        ?>
        <div class="cc-order-meta-box">
            <style>
                .cc-order-meta-box { padding: 10px 0; }
                .cc-voucher-info {
                    background: #e7f5e9;
                    border-left: 4px solid #46b450;
                    padding: 12px;
                    margin: 10px 0;
                    border-radius: 3px;
                }
                .cc-voucher-info strong { display: block; margin-bottom: 8px; color: #2c3338; }
                .cc-voucher-info p { margin: 5px 0; font-size: 13px; }
                .cc-status-box {
                    background: #f0f6fc;
                    border-left: 4px solid #2271b1;
                    padding: 10px 12px;
                    margin: 10px 0;
                    border-radius: 3px;
                    font-size: 12px;
                }
                .cc-status-box.cc-status-delivered {
                    background: #e7f5e9;
                    border-left-color: #46b450;
                }
                .cc-status-box.cc-status-failed {
                    background: #fff3cd;
                    border-left-color: #dba617;
                }
                .cc-status-box.cc-status-returned {
                    background: #fce8e6;
                    border-left-color: #dc3232;
                }
                .cc-button-group { margin-top: 10px; }
                .cc-button-group .button,
                .cc-button-group a.button { width: 100%; margin-bottom: 8px; text-align: center; display: block; box-sizing: border-box; }
                .cc-service-select { width: 100%; margin-bottom: 10px; }
                .cc-checkbox-label {
                    display: block;
                    margin: 10px 0;
                    padding: 8px;
                    background: #f6f7f7;
                    border-radius: 3px;
                }
                                .cc-return-options {
                    margin: 10px 0;
                    padding: 10px;
                    background: #f6f7f7;
                    border-radius: 3px;
                }
                .cc-radio-label {
                    display: block;
                    padding: 6px 4px;
                    margin: 3px 0;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 13px;
                    line-height: 1.4;
                }
                .cc-radio-label:hover {
                    background: #edf1f5;
                }
                .cc-radio-label input[type="radio"] {
                    margin-right: 6px;
                }
                .cc-radio-label small {
                    color: #666;
                    margin-left: 22px;
                }
                #cc-ajax-status {
                    margin-top: 10px;
                    padding: 8px;
                    border-radius: 3px;
                    font-size: 12px;
                }
                #cc-ajax-status.cc-success {
                    background: #e7f5e9;
                    border-left: 3px solid #46b450;
                    color: #1e6823;
                }
                #cc-ajax-status.cc-error {
                    background: #fce8e6;
                    border-left: 3px solid #dc3232;
                    color: #8b0000;
                }
            </style>

            <?php if ( $voucher_number && $is_voided ) : ?>
                <!-- VOIDED STATE -->
                <div class="cc-voucher-info" style="background: #fce8e6; border-left-color: #dc3232;">
                    <strong>❌ Αποστολή Ακυρωμένη</strong>
                    <p><strong>AWB:</strong> <del><?php echo esc_html( $voucher_number ); ?></del></p>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">Μπορείτε να δημιουργήσετε νέο voucher αν χρειάζεται.</p>

            <?php elseif ( $voucher_number ) : ?>
                <?php
                $return_awb    = $order->get_meta( '_cc_return_awb' );
                $return_option = $order->get_meta( '_cc_return_option' );

                $return_labels_display = array(
                    'optional'  => 'Προαιρετικό',
                    'mandatory' => 'Υποχρεωτικό',
                );
                ?>
                <div class="cc-voucher-info">
                    <strong>✅ Voucher Δημιουργήθηκε</strong>
                    <p><strong>AWB:</strong> <?php echo esc_html( $voucher_number ); ?></p>
                    <?php if ( $tracking_number && $tracking_number !== $voucher_number ) : ?>
                        <p><strong>Tracking:</strong> <?php echo esc_html( $tracking_number ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $return_awb ) ) : ?>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #b8d4b8;">
                            <strong style="display: block; color: #1e6823;">↩️ Επιστροφικό (<?php echo esc_html( $return_labels_display[ $return_option ] ?? '' ); ?>)</strong>
                            <p><strong>Return AWB:</strong> <?php echo esc_html( $return_awb ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $return_awb ) ) :
                    $return_print_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=cc_download_voucher&order_id=' . $order->get_id() . '&type=return' ),
                        'cc_download_voucher_' . $order->get_id()
                    );
                    ?>
                    <a href="<?php echo esc_url( $return_print_url ); ?>" target="_blank" class="button button-secondary" style="width: 100%; text-align: center; margin-bottom: 8px;">
                        📄 Εκτύπωση Επιστροφικού
                    </a>
                <?php endif; ?>

                <?php if ( $cc_status_desc ) :
                    // Determine status color class
                    $status_class = '';
                    $final_statuses = array( '29', '87' ); // delivered
                    $failed_statuses = array( '28', '30' ); // failed delivery
                    $returned_statuses = array( '25', '99', '14' ); // returned/cancelled

                    if ( in_array( $cc_status, $final_statuses, true ) ) {
                        $status_class = 'cc-status-delivered';
                    } elseif ( in_array( $cc_status, $failed_statuses, true ) ) {
                        $status_class = 'cc-status-failed';
                    } elseif ( in_array( $cc_status, $returned_statuses, true ) ) {
                        $status_class = 'cc-status-returned';
                    }
                ?>
                    <div class="cc-status-box <?php echo esc_attr( $status_class ); ?>">
                        <strong>📍 <?php echo esc_html( $cc_status_desc ); ?></strong>
                        <?php if ( $cc_status_date ) : ?>
                            <br><small>Τελ. ενημέρωση: <?php echo esc_html( $cc_status_date ); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                $print_url = wp_nonce_url(
                    admin_url( 'admin-post.php?action=cc_download_voucher&order_id=' . $order->get_id() ),
                    'cc_download_voucher_' . $order->get_id()
                );
                ?>
                <div class="cc-button-group">
                    <a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="button button-primary">
                        📄 Εκτύπωση Ετικέτας
                    </a>
                    <button type="button" id="cc-status-btn" class="button button-secondary">🔄 Ενημέρωση Status</button>
                    <button type="button" id="cc-void-btn" class="button button-secondary" style="color: #b32d2e;">❌ Ακύρωση Αποστολής</button>
                </div>

                <div id="cc-ajax-status"></div>

            <?php else : ?>
                <!-- CREATE VOUCHER STATE -->
                <p style="margin-bottom: 10px;">Δημιουργήστε voucher για αυτή την παραγγελία:</p>

                <div id="cc-create-voucher-form">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

                    <label for="cc_service_type" style="display: block; margin-bottom: 5px; font-weight: 600;">
                        📦 Υπηρεσία:
                    </label>
                    <select name="service_type" id="cc_service_type" class="cc-service-select">
                        <option value="next_day">Επόμενη Μέρα</option>
                        <option value="same_day_3h">Αυθημερόν 3 ώρες</option>
                        <option value="same_day_5h">Αυθημερόν 5 ώρες</option>
                    </select>

                    <div class="cc-field-group" style="border:1px solid #ddd; border-radius:4px; padding:10px; margin:8px 0; background:#f9f9f9;">
                        <label style="font-weight:600; display:block; margin-bottom:6px;">
                            📦 Πολλαπλή Αποστολή:
                        </label>
                        <label style="display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" id="cc-multi-parcel" value="1" />
                            <span style="font-size:12px;">Ενεργοποίηση πολλαπλών τεμαχίων</span>
                        </label>
                        <div id="cc-parcel-count-wrap" style="display:none; margin-top:8px;">
                            <label style="font-size:12px; display:flex; align-items:center; gap:6px;">
                                Αριθμός Τεμαχίων:
                                <input type="number" id="cc-parcel-count" value="2" min="2" max="99"
                                       style="width:60px;" />
                            </label>
                        </div>
                    </div>

                    <div class="cc-return-options">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                            ↩️ Επιστροφικό:
                        </label>
                        <label class="cc-radio-label">
                            <input type="radio" name="return_option" value="none" checked>
                            <span>Χωρίς επιστροφικό</span>
                        </label>
                        <label class="cc-radio-label">
                            <input type="radio" name="return_option" value="optional">
                            <span><strong>Προαιρετικό</strong></span>
                            <br><small>Ο παραλήπτης μπορεί να το ενεργοποιήσει αργότερα</small>
                        </label>
                        <label class="cc-radio-label">
                            <input type="radio" name="return_option" value="mandatory">
                            <span><strong>Υποχρεωτικό</strong></span>
                            <br><small>Ο courier θα παραλάβει το επιστροφικό κατά την παράδοση</small>
                        </label>
                    </div>

                    <label class="cc-checkbox-label">
                        <input type="checkbox" name="boxnow" value="1">
                        <strong>📦 BOX NOW Locker</strong>
                        <br>
                        <small style="color: #666;">Αυτόματη αναζήτηση σε ακτίνα 1.5km</small>
                    </label>

                    <button type="button" id="cc-create-voucher-btn" class="button button-primary" style="width: 100%; height: 36px;">
                        🚀 Δημιουργία Voucher
                    </button>
                </div>

                <div id="cc-ajax-status"></div>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for voucher creation - REAL API CALL
     */
    public function ajax_create_voucher() {
        if ( ! check_ajax_referer( 'cc_create_voucher_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => 'Missing order ID' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }

        // Allow re-creation after void
        $existing_voucher = $order->get_meta( '_cc_voucher_number' );
        $is_voided        = $order->get_meta( '_cc_voided' ) === '1';

        if ( $existing_voucher && ! $is_voided ) {
            wp_send_json_error( array( 'message' => 'Υπάρχει ήδη ενεργό voucher για αυτή την παραγγελία' ) );
        }

        $service_type = isset( $_POST['service_type'] ) ? sanitize_text_field( $_POST['service_type'] ) : 'next_day';
        $boxnow       = isset( $_POST['boxnow'] ) && $_POST['boxnow'] === '1';
        $parcel_count = isset( $_POST['parcel_count'] ) ? intval( $_POST['parcel_count'] ) : 1;
        $parcel_count = max( 1, min( 99, $parcel_count ) );

        // Validate return option
        $return_option = isset( $_POST['return_option'] ) ? sanitize_text_field( $_POST['return_option'] ) : 'none';
        if ( ! in_array( $return_option, array( 'none', 'optional', 'mandatory' ), true ) ) {
            $return_option = 'none';
        }

        $builder = new CC_Shipment_Builder( $order, array(), $parcel_count );

        $settings_check = $builder->validate_settings();
        if ( is_wp_error( $settings_check ) ) {
            wp_send_json_error( array( 'message' => $settings_check->get_error_message() ) );
        }

        $order_check = $builder->validate_order();
        if ( is_wp_error( $order_check ) ) {
            wp_send_json_error( array( 'message' => $order_check->get_error_message() ) );
        }

        // BOX NOW δεν δέχεται αντικαταβολή
        if ( $boxnow && $order->get_payment_method() === 'cod' ) {
            wp_send_json_error( array(
                'message' => '❌ Το BOX NOW δεν υποστηρίζει αντικαταβολή. Αλλάξτε τον τρόπο πληρωμής ή απενεργοποιήστε το BOX NOW Locker.',
            ) );
        }

        // Build the payload
        $payload = $builder->build_payload( $service_type, $boxnow, $return_option );

        $api    = new CC_API();
        $result = $api->create_shipment( $payload );

        if ( is_wp_error( $result ) ) {
            $order->add_order_note( '❌ Σφάλμα δημιουργίας voucher: ' . $result->get_error_message() );
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $voucher_number  = $result['ShipmentNumber'] ?? '';
        $tracking_number = isset( $result['TrackingNumbers'][0] ) ? $result['TrackingNumbers'][0] : $voucher_number;

        if ( empty( $voucher_number ) ) {
            error_log( 'CC BOX NOW DEBUG: ' . print_r( $result, true ) );
            error_log( 'CC VOUCHER ERROR - Raw result: ' . wp_json_encode( $result ) );
            $api_message = $result['Errors'][0]['Message']
                ?? $result['ErrorMessage']
                ?? $result['Message']
                ?? 'Το API δεν επέστρεψε ShipmentNumber';
            wp_send_json_error( array( 'message' => $api_message ) );
        }

        // Clear old voided data if re-creating
        if ( $is_voided ) {
            $order->delete_meta_data( '_cc_voided' );
            $order->delete_meta_data( '_cc_voided_at' );
            $order->delete_meta_data( '_cc_shipment_status' );
            $order->delete_meta_data( '_cc_shipment_status_desc' );
            $order->delete_meta_data( '_cc_shipment_action_code' );
            $order->delete_meta_data( '_cc_status_updated_at' );
        }

        // Save meta
        $order->update_meta_data( '_cc_voucher_number', $voucher_number );
        $order->update_meta_data( '_cc_tracking_number', $tracking_number );
        $order->update_meta_data( '_cc_service_type', $service_type );
        $order->update_meta_data( '_cc_boxnow', $boxnow ? '1' : '0' );
        $order->update_meta_data( '_cc_return_option', $return_option );
        $order->update_meta_data( '_cc_created_at', current_time( 'mysql' ) );

        // Save return AWB if exists
        $return_awb = '';
        if ( $return_option !== 'none' ) {
            // Το API μπορεί να επιστρέψει το return AWB σε διάφορα πεδία
            // Δοκιμάζουμε τα πιο πιθανά
            if ( ! empty( $result['ReturnShipmentNumber'] ) ) {
                $return_awb = $result['ReturnShipmentNumber'];
            } elseif ( ! empty( $result['ReturnAWB'] ) ) {
                $return_awb = $result['ReturnAWB'];
            } elseif ( ! empty( $result['ReturnTrackingNumbers'][0] ) ) {
                $return_awb = $result['ReturnTrackingNumbers'][0];
            } elseif ( isset( $result['TrackingNumbers'][1] ) ) {
                // Κάποιες υλοποιήσεις επιστρέφουν το return AWB ως δεύτερο στοιχείο
                $return_awb = $result['TrackingNumbers'][1];
            }

            if ( ! empty( $return_awb ) ) {
                $order->update_meta_data( '_cc_return_awb', $return_awb );
            }
        }

        if ( $boxnow && isset( $result['ContractorResultNote'] ) && stripos( $result['ContractorResultNote'], 'No locker found' ) !== false ) {
            $order->update_meta_data( '_cc_boxnow_fallback', '1' );
        }

        $order->save();

        // Order note
        $service_labels = array(
            'next_day'    => 'Επόμενη Μέρα',
            'same_day_3h' => 'Αυθημερόν 3h',
            'same_day_5h' => 'Αυθημερόν 5h',
        );
        $service_label = $service_labels[ $service_type ] ?? 'Επόμενη Μέρα';
        $boxnow_label  = $boxnow ? ' + BOX NOW' : '';

        $return_labels = array(
            'none'      => '',
            'optional'  => ' + Προαιρετικό Επιστροφικό',
            'mandatory' => ' + Υποχρεωτικό Επιστροφικό',
        );
        $return_label = $return_labels[ $return_option ] ?? '';

        $note = sprintf(
            '✅ Courier Center voucher: %s | Υπηρεσία: %s%s%s',
            $voucher_number,
            $service_label,
            $boxnow_label,
            $return_label
        );

        if ( ! empty( $return_awb ) ) {
            $note .= ' | Return AWB: ' . $return_awb;
        }

        if ( $boxnow && isset( $result['ContractorResultNote'] ) && stripos( $result['ContractorResultNote'], 'No locker found' ) !== false ) {
            $note .= ' | ⚠️ Δεν βρέθηκε locker - αποστέλλεται door-to-door';
        }

        $order->add_order_note( $note );

        wp_send_json_success( array(
            'message'         => 'Voucher δημιουργήθηκε: ' . $voucher_number,
            'voucher_number'  => $voucher_number,
            'tracking_number' => $tracking_number,
        ) );
    }

    /**
     * Handle PDF voucher download - opens in new tab
     */
    public function download_voucher_pdf() {
        $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;

        if ( ! $order_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cc_download_voucher_' . $order_id ) ) {
            wp_die( 'Invalid request' );
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Unauthorized' );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( 'Order not found' );
        }

        // Check if we're printing the return voucher or the main one
        $voucher_type = isset( $_GET['type'] ) && $_GET['type'] === 'return' ? 'return' : 'main';

        if ( $voucher_type === 'return' ) {
            $awb = $order->get_meta( '_cc_return_awb' );
            if ( empty( $awb ) ) {
                wp_die( 'Δεν υπάρχει επιστροφικό voucher για αυτή την παραγγελία' );
            }
        } else {
            $awb = $order->get_meta( '_cc_voucher_number' );
            if ( empty( $awb ) ) {
                wp_die( 'Δεν υπάρχει voucher για αυτή την παραγγελία' );
            }
        }

        $is_boxnow = $order->get_meta( '_cc_boxnow' ) === '1';

        // Διαβάζουμε από τις ρυθμίσεις
        if ( $is_boxnow ) {
            $raw_template = get_option( 'cc_wc_print_template_boxnow', 'singlepdf_100x150_4up' );
        } else {
            $raw_template = get_option( 'cc_wc_print_template', 'pdf' );
        }

        // singlepdf_100x150_4up είναι custom — στέλνουμε singlepdf_100x150 στο API
        // αλλά μετά κάνουμε arrange_4up() με FPDI
        $use_4up  = ( $raw_template === 'singlepdf_100x150_4up' );
        $template = $use_4up ? 'singlepdf_100x150' : $raw_template;

        $api = new CC_API();
        $pdf = $api->get_voucher_pdf( $awb, $template );

        if ( is_wp_error( $pdf ) ) {
            wp_die( 'Σφάλμα λήψης voucher: ' . esc_html( $pdf->get_error_message() ) );
        }

        // Αν είναι 4up layout, κάνε arrange με FPDI
        if ( $use_4up ) {
            $arranged = CC_PDF_Scaler::arrange_4up( $pdf );
            if ( ! is_wp_error( $arranged ) ) {
                $pdf = $arranged;
            }
        }

        // Scale για κανονικά (pdf / clean)
        if ( in_array( $template, array( 'pdf', 'clean' ), true ) ) {
            $scaled = CC_PDF_Scaler::scale_pdf( $pdf, 0.95 );
            if ( ! is_wp_error( $scaled ) ) {
                $pdf = $scaled;
            }
        }

        $scaled_pdf = $pdf;

        // Stream PDF to browser - inline display in new tab
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="voucher-' . sanitize_file_name( $awb ) . '.pdf"' );
        header( 'Content-Length: ' . strlen( $scaled_pdf ) );

        echo $scaled_pdf;
        exit;
    }

    /**
     * AJAX handler for voiding a shipment
     */
    public function ajax_void_shipment() {
        if ( ! check_ajax_referer( 'cc_create_voucher_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => 'Missing order ID' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }

        $awb = $order->get_meta( '_cc_voucher_number' );

        if ( empty( $awb ) ) {
            wp_send_json_error( array( 'message' => 'Δεν υπάρχει voucher για ακύρωση' ) );
        }

        if ( $order->get_meta( '_cc_voided' ) === '1' ) {
            wp_send_json_error( array( 'message' => 'Η αποστολή έχει ήδη ακυρωθεί' ) );
        }

        $api    = new CC_API();
        $result = $api->void_shipment( $awb );

        if ( is_wp_error( $result ) ) {
            $order->add_order_note( '❌ Αποτυχία ακύρωσης: ' . $result->get_error_message() );
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $order->update_meta_data( '_cc_voided', '1' );
        $order->update_meta_data( '_cc_voided_at', current_time( 'mysql' ) );
        $order->save();

        $order->add_order_note( '❌ Αποστολή ακυρώθηκε: ' . $awb );

        wp_send_json_success( array(
            'message' => 'Η αποστολή ' . $awb . ' ακυρώθηκε επιτυχώς',
        ) );
    }

    /**
     * Προσθέτει στήλη "Tracking" στη λίστα παραγγελιών
     */
    public function add_tracking_column( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new['cc_tracking'] = '🚚 Tracking';
            }
        }
        return $new;
    }

    /**
     * Εμφανίζει το tracking link στη στήλη — legacy UI
     *
     * @param string $column  Όνομα στήλης
     * @param int    $post_id Post ID της παραγγελίας
     */
    public function render_tracking_column( $column, $post_id ) {
        if ( $column !== 'cc_tracking' ) {
            return;
        }
        $order = wc_get_order( $post_id );
        if ( $order ) {
            $this->output_tracking_cell( $order );
        }
    }

    /**
     * Εμφανίζει το tracking link στη στήλη — HPOS UI
     *
     * @param string   $column Όνομα στήλης
     * @param WC_Order $order  Η παραγγελία
     */
    public function render_tracking_column_hpos( $column, $order ) {
        if ( $column !== 'cc_tracking' ) {
            return;
        }
        $this->output_tracking_cell( $order );
    }

    /**
     * Παράγει το HTML του tracking link (κοινό και για τις δύο UIs)
     */
    private function output_tracking_cell( $order ) {
        $voucher  = $order->get_meta( '_cc_voucher_number' );
        $is_voided = $order->get_meta( '_cc_voided' ) === '1';

        if ( empty( $voucher ) ) {
            echo '<span style="color:#aaa;">—</span>';
            return;
        }

        $tracking_url_template = get_option( 'cc_wc_tracking_url', 'https://www.courier.gr/track/result?tracknr={{tracking}}' );
        $tracking_url = str_replace( '{{tracking}}', rawurlencode( $voucher ), $tracking_url_template );

        if ( $is_voided ) {
            echo '<del style="color:#aaa; font-size:12px;">' . esc_html( $voucher ) . '</del>';
        } else {
            printf(
                '<a href="%s" target="_blank" rel="noopener" style="font-size:12px; white-space:nowrap;">%s&nbsp;↗</a>',
                esc_url( $tracking_url ),
                esc_html( $voucher )
            );
        }
    }

    /**
     * AJAX handler for manual status update
     */
    public function ajax_update_status() {
        if ( ! check_ajax_referer( 'cc_create_voucher_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => 'Missing order ID' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( array( 'message' => 'Order not found' ) );
        }

        $awb = $order->get_meta( '_cc_voucher_number' );

        if ( empty( $awb ) ) {
            wp_send_json_error( array( 'message' => 'Δεν υπάρχει voucher' ) );
        }

        // Έλεγχος rate limit (60 λεπτά ανά αποστολή)
        $rate_check = CC_Status_Tracker::check_rate_limit( $order );
        if ( ! $rate_check['allowed'] ) {
            wp_send_json_error( array(
                'message' => sprintf(
                    'Η αποστολή ενημερώθηκε %s. Δοκιμάστε ξανά σε %d λεπτά.',
                    $rate_check['last_updated_human'],
                    $rate_check['minutes_remaining']
                ),
                'rate_limited'      => true,
                'minutes_remaining' => $rate_check['minutes_remaining'],
            ) );
        }

        $api    = new CC_API();
        $result = $api->get_shipment_details( $awb );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Parse the response - data is inside ShipmentDetails[0].ShipmentInfo
        $status_code = '';
        $status_desc = '';
        $action_code = '';

        if ( isset( $result['ShipmentDetails'][0]['ShipmentInfo'] ) ) {
            $info = $result['ShipmentDetails'][0]['ShipmentInfo'];
            $status_code = (string) ( $info['ShipmentStatus'] ?? '' );
            $status_desc = $info['ShipmentStatusDesc'] ?? '';
            $action_code = $info['CollectionStatus'] ?? '';
        }

        if ( empty( $status_code ) && empty( $status_desc ) ) {
            error_log( 'CC STATUS DEBUG - Raw response: ' . wp_json_encode( $result ) );
            wp_send_json_error( array( 'message' => 'Δεν βρέθηκε status στο API response.' ) );
        }

        // Save status to order meta
        $old_status = $order->get_meta( '_cc_shipment_status' );

        $order->update_meta_data( '_cc_shipment_status', $status_code );
        $order->update_meta_data( '_cc_shipment_status_desc', $status_desc );
        $order->update_meta_data( '_cc_shipment_action_code', $action_code );
        $order->update_meta_data( '_cc_status_updated_at', current_time( 'Y-m-d H:i' ) );
        $order->save();

        // Add order note only if status changed
        if ( $old_status !== $status_code ) {
            $action_text = $action_code ? " (Action: $action_code)" : '';
            $order->add_order_note( sprintf(
                '📍 Status ενημερώθηκε: %s - %s%s',
                $status_code,
                $status_desc,
                $action_text
            ) );
        }

        $message = $status_desc ?: "Status: $status_code";
        if ( $action_code ) {
            $message .= " (Action: $action_code)";
        }

        // Σημείωσε ότι έγινε check τώρα για να ενεργοποιηθεί το rate limit
        CC_Status_Tracker::mark_check_now( $order );

        wp_send_json_success( array(
            'message'     => $message,
            'status_code' => $status_code,
            'status_desc' => $status_desc,
            'action_code' => $action_code,
        ) );
    }
}