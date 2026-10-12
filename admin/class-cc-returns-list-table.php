<?php
/**
 * Επιστροφές: πίνακας αιτημάτων (WP_List_Table) για τη σελίδα «Επιστροφές».
 *
 * Φορτώνεται μόνο στο admin (από το CC_Returns_Admin::list_table()), αφού φορτωθεί η WP_List_Table.
 * Φίλτρα (status, μέθοδος) και σελίδα διαβάζονται από το $_GET και ελέγχονται με whitelist.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CC_Returns_List_Table extends WP_List_Table {

    const PER_PAGE = 20;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'cc_return',
            'plural'   => 'cc_returns',
            'ajax'     => false,
        ) );
    }

    // ── Φίλτρα (whitelist) ───────────────────────────────────────────────────

    public static function statuses() {
        return array(
            CC_Returns_Rules::ST_PENDING,
            CC_Returns_Rules::ST_APPROVED,
            CC_Returns_Rules::ST_CREATING,
            CC_Returns_Rules::ST_VERIFY,
            CC_Returns_Rules::ST_CREATED,
            CC_Returns_Rules::ST_FAILED,
            CC_Returns_Rules::ST_REJECTED,
        );
    }

    private function current_status() {
        $v = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        return in_array( $v, self::statuses(), true ) ? $v : '';
    }

    private function current_method() {
        $v = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        return in_array( $v, CC_Returns_Rules::METHODS_ORDER, true ) ? $v : '';
    }

    private function current_page_num() {
        $v = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1; // phpcs:ignore WordPress.Security.NonceVerification
        return max( 1, $v );
    }

    private function filters() {
        return array(
            'status' => $this->current_status(),
            'method' => $this->current_method(),
        );
    }

    private function base_url( array $args = array() ) {
        return add_query_arg(
            array_merge( array( 'page' => CC_Returns_Admin::PAGE, 'tab' => 'requests' ), $args ),
            admin_url( 'admin.php' )
        );
    }

    // ── Στήλες ───────────────────────────────────────────────────────────────

    public function get_columns() {
        return array(
            'id'                    => '#Αιτήματος',
            'order'                 => 'Παραγγελία',
            'customer'              => 'Πελάτης',
            'method'                => 'Μέθοδος',
            'original_awb'          => 'Αρχικό AWB',
            'return_awb'            => 'AWB επιστροφής',
            'original_shipper_code' => 'Αρχικός κωδικός',
            'return_account_code'   => 'Λογαριασμός επιστροφών',
            'status'                => 'Κατάσταση',
            'tracking'              => 'Tracking',
            'created_at'            => 'Δημιουργία',
            'last_tracking_at'      => 'Τελ. ενημέρωση tracking',
        );
    }

    protected function get_primary_column_name() {
        return 'id';
    }

    public function prepare_items() {
        $filters = $this->filters();
        $page    = $this->current_page_num();
        $total   = CC_Returns_Repo::count( $filters );
        $pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
        $page    = min( $page, $pages );

        $this->_column_headers = array( $this->get_columns(), array(), array(), 'id' );
        $this->items           = CC_Returns_Repo::query( array_merge( $filters, array(
            'limit'  => self::PER_PAGE,
            'offset' => ( $page - 1 ) * self::PER_PAGE,
        ) ) );
        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => self::PER_PAGE,
            'total_pages' => $pages,
        ) );
    }

    public function no_items() {
        echo 'Δεν βρέθηκαν αιτήματα επιστροφής.';
    }

    // ── Views / φίλτρο μεθόδου ───────────────────────────────────────────────

    /** @return array slug => html (Όλα + ένα ανά status που έχει αιτήματα). */
    public function get_views() {
        $counts  = CC_Returns_Repo::count_by_status();
        $current = $this->current_status();
        $method  = $this->current_method();
        $args    = '' !== $method ? array( 'method' => $method ) : array();
        $views   = array();

        $views['all'] = sprintf(
            '<a href="%s"%s>Όλα <span class="count">(%d)</span></a>',
            esc_url( $this->base_url( $args ) ),
            '' === $current ? ' class="current" aria-current="page"' : '',
            array_sum( $counts )
        );
        foreach ( self::statuses() as $status ) {
            $n = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
            if ( 0 === $n && $status !== $current ) {
                continue;
            }
            $views[ $status ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $this->base_url( array_merge( $args, array( 'status' => $status ) ) ) ),
                $status === $current ? ' class="current" aria-current="page"' : '',
                esc_html( CC_Returns_Service::status_label( $status ) ),
                $n
            );
        }
        return $views;
    }

    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        $method = $this->current_method();
        echo '<div class="alignleft actions">';
        echo '<select name="method"><option value="">Όλες οι μέθοδοι</option>';
        foreach ( CC_Returns_Rules::METHODS_ORDER as $m ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $m ),
                selected( $method, $m, false ),
                esc_html( CC_Returns_Service::method_label( $m ) )
            );
        }
        echo '</select>';
        submit_button( 'Φιλτράρισμα', '', 'filter_action', false );
        echo '</div>';
    }

    // ── Κελιά ────────────────────────────────────────────────────────────────

    public function column_id( $item ) {
        $actions = array();
        foreach ( CC_Returns_Admin::actions_for( $item ) as $a ) {
            $actions[ $a['do'] ] = CC_Returns_Admin::action_link( $item, $a, false );
        }
        return '<strong>#' . (int) $item['id'] . '</strong>' . $this->row_actions( $actions );
    }

    public function column_order( $item ) {
        $order = wc_get_order( (int) $item['order_id'] );
        if ( ! $order ) {
            return '#' . (int) $item['order_id'];
        }
        return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
    }

    public function column_customer( $item ) {
        $html = esc_html( CC_Returns_Admin::customer_name( $item ) );
        if ( isset( $item['origin'] ) && 'shop' === $item['origin'] ) {
            $html .= '<br><small>' . esc_html( 'Από: Κατάστημα' ) . '</small>';
        }
        return $html;
    }

    public function column_method( $item ) {
        $html = esc_html( CC_Returns_Service::method_label( $item['method'] ) );
        if ( CC_Returns_Rules::METHOD_PICKUP === $item['method'] ) {
            $label = CC_Returns_Service::pickup_label( $item );
            if ( '' !== $label ) {
                $html .= '<br><small>' . esc_html( 'Παραλαβή: ' . $label ) . '</small>';
            }
        }
        return $html;
    }

    public function column_status( $item ) {
        return esc_html( CC_Returns_Service::status_label( $item['status'] ) );
    }

    public function column_tracking( $item ) {
        return esc_html( $item['display_status'] );
    }

    public function column_created_at( $item ) {
        return esc_html( CC_Returns_Admin::format_date( $item['created_at'] ) );
    }

    public function column_last_tracking_at( $item ) {
        return esc_html( CC_Returns_Admin::format_date( $item['last_tracking_at'] ) );
    }

    public function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
    }
}
