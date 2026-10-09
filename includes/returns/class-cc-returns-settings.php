<?php
/**
 * Επιστροφές: ρυθμίσεις (tab «Ρυθμίσεις» της σελίδας Επιστροφές).
 *
 * Options με group `cc_wc_returns_settings`. Οι getters διαβάζουν τα options με τις
 * προεπιλογές τους· ο sanitizer των λογαριασμών επιστροφών είναι pure (sanitize_accounts).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_Returns_Settings {

    const GROUP     = 'cc_wc_returns_settings';
    const PAGE      = 'courier-center-returns-settings';
    const CAPABILITY = 'manage_woocommerce';

    const OPT_ENABLED           = 'cc_wc_returns_enabled';
    const OPT_REQUIRE_APPROVAL  = 'cc_wc_returns_require_approval';
    const OPT_WINDOW_DAYS       = 'cc_wc_returns_window_days';
    const OPT_METHOD_DROPOFF    = 'cc_wc_returns_method_dropoff';
    const OPT_METHOD_BOXNOW     = 'cc_wc_returns_method_boxnow';
    const OPT_METHOD_PICKUP     = 'cc_wc_returns_method_pickup';
    const OPT_ACCOUNTS          = 'cc_wc_returns_accounts';
    const OPT_WEIGHT            = 'cc_wc_returns_weight';
    const OPT_DROPOFF_TEXT      = 'cc_wc_returns_dropoff_instructions';
    const OPT_BOXNOW_TEXT       = 'cc_wc_returns_boxnow_instructions';

    const DEFAULT_DROPOFF_TEXT = 'Συσκευάστε καλά το δέμα και παραδώστε το σε οποιοδήποτε κατάστημα Courier Center αναφέροντας τον αριθμό αποστολής επιστροφής.';
    const DEFAULT_BOXNOW_TEXT  = 'Συσκευάστε καλά το δέμα και παραδώστε το σε ένα BOX NOW locker χρησιμοποιώντας τον κωδικό BOX NOW επιστροφής.';

    const WEIGHT_MIN = 0.1;
    const WEIGHT_MAX = 30.0;

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register' ) );
        add_filter( 'option_page_capability_' . self::GROUP, array( $this, 'settings_capability' ) );
    }

    /**
     * Όπως στις υπόλοιπες ρυθμίσεις: η αποθήκευση μέσω options.php θέλει manage_woocommerce.
     */
    public function settings_capability( $capability ) {
        return self::CAPABILITY;
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public static function option_names() {
        return array(
            self::OPT_ENABLED,
            self::OPT_REQUIRE_APPROVAL,
            self::OPT_WINDOW_DAYS,
            self::OPT_METHOD_DROPOFF,
            self::OPT_METHOD_BOXNOW,
            self::OPT_METHOD_PICKUP,
            self::OPT_ACCOUNTS,
            self::OPT_WEIGHT,
            self::OPT_DROPOFF_TEXT,
            self::OPT_BOXNOW_TEXT,
        );
    }

    private static function flag( $option, $default ) {
        return '1' === (string) get_option( $option, $default );
    }

    public static function enabled() {
        return self::flag( self::OPT_ENABLED, '0' );
    }

    public static function require_approval() {
        return self::flag( self::OPT_REQUIRE_APPROVAL, '1' );
    }

    /**
     * Ημέρες από την ολοκλήρωση της παράδοσης· 0 = χωρίς όριο.
     */
    public static function window_days() {
        return max( 0, (int) get_option( self::OPT_WINDOW_DAYS, 14 ) );
    }

    /**
     * Ενεργές μέθοδοι σε σειρά METHODS_ORDER. Η παραλαβή (PICKUP) δεν είναι ποτέ ενεργή,
     * ακόμη κι αν το option έχει '1' στη βάση.
     *
     * @return string[]
     */
    public static function enabled_methods() {
        $on = array(
            CC_Returns_Rules::METHOD_DROPOFF => self::flag( self::OPT_METHOD_DROPOFF, '1' ),
            CC_Returns_Rules::METHOD_BOXNOW  => self::flag( self::OPT_METHOD_BOXNOW, '0' ),
        );

        $methods = array();
        foreach ( CC_Returns_Rules::METHODS_ORDER as $method ) {
            if ( ! empty( $on[ $method ] ) ) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     * Βάρος δέματος επιστροφής σε kg, 0.1–30 (μη έγκυρο → 1).
     */
    public static function weight() {
        return self::clamp_weight( get_option( self::OPT_WEIGHT, 1 ) );
    }

    private static function clamp_weight( $raw ) {
        $raw = is_string( $raw ) ? str_replace( ',', '.', trim( $raw ) ) : $raw;
        if ( ! is_numeric( $raw ) || (float) $raw <= 0 ) {
            return 1.0;
        }
        return (float) min( self::WEIGHT_MAX, max( self::WEIGHT_MIN, (float) $raw ) );
    }

    public static function dropoff_instructions() {
        $text = (string) get_option( self::OPT_DROPOFF_TEXT, '' );
        return '' === trim( $text ) ? self::DEFAULT_DROPOFF_TEXT : $text;
    }

    public static function boxnow_instructions() {
        $text = (string) get_option( self::OPT_BOXNOW_TEXT, '' );
        return '' === trim( $text ) ? self::DEFAULT_BOXNOW_TEXT : $text;
    }

    /**
     * @return array[] Γραμμές array( 'original' => ..., 'return' => ... )
     */
    public static function account_rows() {
        $rows = get_option( self::OPT_ACCOUNTS, array() );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            if ( is_array( $row ) && ! empty( $row['original'] ) && ! empty( $row['return'] ) ) {
                $clean[] = array( 'original' => (string) $row['original'], 'return' => (string) $row['return'] );
            }
        }
        return $clean;
    }

    /**
     * Ο κωδικός επιστροφών που αντιστοιχεί στον αρχικό κωδικό της αποστολής ('' αν δεν υπάρχει).
     */
    public static function return_account_for( $original_code ) {
        foreach ( self::account_rows() as $row ) {
            if ( CC_Returns_Rules::codes_equal( $row['original'], $original_code ) ) {
                return $row['return'];
            }
        }
        return '';
    }

    // ── Sanitize ─────────────────────────────────────────────────────────────

    /**
     * Καθάρισμα των γραμμών αντιστοίχισης. Pure — τα μηνύματα επιστρέφονται, δεν καταγράφονται εδώ.
     * Κενός κωδικός επιστροφών = η γραμμή παραλείπεται σιωπηλά (fallback στον αρχικό κωδικό).
     *
     * @param mixed    $rows        Γραμμές όπως ήρθαν από τη φόρμα (unslashed).
     * @param string[] $known_codes Οι κωδικοί των σημείων αποστολής (Έδρα + υποκωδικοί).
     * @return array{rows: array[], errors: string[]}
     */
    public static function sanitize_accounts( $rows, array $known_codes ) {
        $clean  = array();
        $errors = array();

        if ( ! is_array( $rows ) ) {
            return array( 'rows' => array(), 'errors' => array() );
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $original = trim( sanitize_text_field( (string) ( isset( $row['original'] ) ? $row['original'] : '' ) ) );
            $return   = trim( sanitize_text_field( (string) ( isset( $row['return'] ) ? $row['return'] : '' ) ) );

            if ( '' === $return ) {
                continue;
            }

            // Ο αρχικός πρέπει να είναι γνωστό σημείο· αποθηκεύεται στη μορφή του σημείου
            $canonical = '';
            foreach ( $known_codes as $known ) {
                if ( CC_Returns_Rules::codes_equal( $known, $original ) ) {
                    $canonical = (string) $known;
                    break;
                }
            }
            if ( '' === $canonical ) {
                $errors[] = sprintf( 'Ο αρχικός κωδικός «%s» δεν αντιστοιχεί σε γνωστό σημείο αποστολής και η γραμμή αγνοήθηκε.', $original );
                continue;
            }

            if ( ! CC_Returns_Rules::is_valid_account_code( $return ) ) {
                $errors[] = sprintf( 'Ο κωδικός επιστροφών «%s» δεν έχει έγκυρη μορφή (π.χ. 100-0120-0001) και η γραμμή αγνοήθηκε.', $return );
                continue;
            }

            foreach ( $clean as $existing ) {
                if ( CC_Returns_Rules::codes_equal( $existing['original'], $canonical ) ) {
                    $errors[] = sprintf( 'Ο αρχικός κωδικός %s υπάρχει δύο φορές· κρατήθηκε μόνο η πρώτη γραμμή.', $canonical );
                    continue 2;
                }
            }

            $clean[] = array( 'original' => $canonical, 'return' => $return );
        }

        return array( 'rows' => $clean, 'errors' => $errors );
    }

    public function sanitize_accounts_option( $value ) {
        $known = array();
        foreach ( CC_Pickup_Points::get_points() as $point ) {
            if ( '' !== (string) $point['code'] ) {
                $known[] = (string) $point['code'];
            }
        }

        $result = self::sanitize_accounts( $value, $known );

        foreach ( $result['errors'] as $i => $message ) {
            add_settings_error( self::OPT_ACCOUNTS, 'cc_returns_account_' . $i, $message, 'error' );
        }

        return $result['rows'];
    }

    public static function sanitize_flag( $value ) {
        return empty( $value ) || '0' === (string) $value ? '0' : '1';
    }

    /** Η παραλαβή δεν ενεργοποιείται από τη φόρμα — πάντα '0'. */
    public static function sanitize_pickup_flag( $value ) {
        return '0';
    }

    public static function sanitize_window_days( $value ) {
        return max( 0, (int) $value );
    }

    public static function sanitize_weight( $value ) {
        return self::clamp_weight( $value );
    }

    // ── Εγγραφή ρυθμίσεων ────────────────────────────────────────────────────

    public function register() {
        // ── Γενικά ──
        add_settings_section( 'cc_returns_general', '↩️ Επιστροφές', array( $this, 'general_section' ), self::PAGE );

        $this->register_flag( self::OPT_ENABLED, '0', 'Ενεργοποίηση επιστροφών', 'cc_returns_general',
            'Ενεργοποίηση της δυνατότητας επιστροφής από τους πελάτες.' );
        $this->register_flag( self::OPT_REQUIRE_APPROVAL, '1', 'Απαιτείται έγκριση', 'cc_returns_general',
            'Αν είναι απενεργοποιημένο, το αίτημα εγκρίνεται αυτόματα με την υποβολή (ίδιες καταστάσεις και κλειδώματα).' );

        register_setting( self::GROUP, self::OPT_WINDOW_DAYS, array(
            'type'              => 'integer',
            'sanitize_callback' => array( __CLASS__, 'sanitize_window_days' ),
            'default'           => 14,
        ) );
        add_settings_field( self::OPT_WINDOW_DAYS, 'Προθεσμία επιστροφής (ημέρες)', array( $this, 'field_window_days' ),
            self::PAGE, 'cc_returns_general', array( 'label_for' => self::OPT_WINDOW_DAYS ) );

        register_setting( self::GROUP, self::OPT_WEIGHT, array(
            'type'              => 'number',
            'sanitize_callback' => array( __CLASS__, 'sanitize_weight' ),
            'default'           => 1,
        ) );
        add_settings_field( self::OPT_WEIGHT, 'Βάρος δέματος επιστροφής (kg)', array( $this, 'field_weight' ),
            self::PAGE, 'cc_returns_general', array( 'label_for' => self::OPT_WEIGHT ) );

        // ── Μέθοδοι ──
        add_settings_section( 'cc_returns_methods', '🚚 Μέθοδοι επιστροφής', array( $this, 'methods_section' ), self::PAGE );

        $this->register_flag( self::OPT_METHOD_DROPOFF, '1', 'Drop Off σε κατάστημα', 'cc_returns_methods',
            'Ο πελάτης παραδίδει το δέμα σε οποιοδήποτε κατάστημα Courier Center.' );
        $this->register_flag( self::OPT_METHOD_BOXNOW, '0', 'BOX NOW', 'cc_returns_methods',
            'Επιστροφή μέσω BOX NOW locker. Ενεργοποιήστε το όταν επιβεβαιώσετε ότι λειτουργεί end-to-end.' );

        register_setting( self::GROUP, self::OPT_METHOD_PICKUP, array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_pickup_flag' ),
            'default'           => '0',
        ) );
        add_settings_field( self::OPT_METHOD_PICKUP, 'Παραλαβή από τον πελάτη', array( $this, 'field_pickup' ),
            self::PAGE, 'cc_returns_methods' );

        // ── Λογαριασμοί επιστροφών ──
        add_settings_section( 'cc_returns_accounts', '🔁 Λογαριασμοί επιστροφών', array( $this, 'accounts_section' ), self::PAGE );

        register_setting( self::GROUP, self::OPT_ACCOUNTS, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_accounts_option' ),
            'default'           => array(),
        ) );
        add_settings_field( self::OPT_ACCOUNTS, 'Αντιστοίχιση κωδικών', array( $this, 'field_accounts' ),
            self::PAGE, 'cc_returns_accounts' );

        // ── BOX NOW ──
        add_settings_section( 'cc_returns_boxnow', '📦 BOX NOW', array( $this, 'boxnow_section' ), self::PAGE );

        // ── Οδηγίες ──
        add_settings_section( 'cc_returns_texts', '📝 Οδηγίες προς τον πελάτη', array( $this, 'texts_section' ), self::PAGE );

        foreach ( array(
            self::OPT_DROPOFF_TEXT => array( 'Οδηγίες Drop Off', self::DEFAULT_DROPOFF_TEXT ),
            self::OPT_BOXNOW_TEXT  => array( 'Οδηγίες BOX NOW', self::DEFAULT_BOXNOW_TEXT ),
        ) as $option => $def ) {
            register_setting( self::GROUP, $option, array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => $def[1],
            ) );
            add_settings_field( $option, $def[0], array( $this, 'field_textarea' ),
                self::PAGE, 'cc_returns_texts', array( 'label_for' => $option, 'default' => $def[1] ) );
        }

        // ── Ειδοποιήσεις ──
        add_settings_section( 'cc_returns_notifications', '✉️ Ειδοποιήσεις', array( $this, 'notifications_section' ), self::PAGE );
    }

    private function register_flag( $option, $default, $title, $section, $description ) {
        register_setting( self::GROUP, $option, array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_flag' ),
            'default'           => $default,
        ) );
        add_settings_field( $option, $title, array( $this, 'field_flag' ), self::PAGE, $section, array(
            'label_for'   => $option,
            'option'      => $option,
            'default'     => $default,
            'description' => $description,
        ) );
    }

    // ── Section callbacks ────────────────────────────────────────────────────

    public function general_section() {
        echo '<p>' . esc_html( 'Επιστροφές αποστολών από τη σελίδα «Η παραγγελία μου» του πελάτη. Το ίδιο το αίτημα εγκρίνεται από εσάς (ή αυτόματα, αν απενεργοποιήσετε την έγκριση).' ) . '</p>';
    }

    public function methods_section() {
        $s = CC_Returns_Rules::DROPOFF_SHIPPER;
        echo '<p>' . esc_html( 'Οι μέθοδοι που θα μπορεί να επιλέξει ο πελάτης. Αν δεν είναι ενεργή καμία, η επιστροφή δεν προσφέρεται.' ) . '</p>';
        printf(
            '<p class="description">%s <code>%s</code> / <code>%s</code> / <code>%s</code></p>',
            esc_html( 'Στοιχεία αποστολέα Drop Off (σταθερά, δεν αλλάζουν): ΤΚ / Περιοχή / Πόλη =' ),
            esc_html( $s['ZipCode'] ),
            esc_html( $s['Area'] ),
            esc_html( $s['City'] )
        );
    }

    public function accounts_section() {
        echo '<p>' . esc_html( 'Αν η επιστροφή πρέπει να χρεώνεται σε διαφορετικό κωδικό από αυτόν της αρχικής αποστολής, αντιστοιχίστε εδώ τον αρχικό κωδικό (Έδρα ή υποκωδικός) με τον κωδικό επιστροφών. Χωρίς αντιστοίχιση, χρησιμοποιείται ο αρχικός κωδικός.' ) . '</p>';
    }

    public function boxnow_section() {
        echo '<p>' . esc_html( 'Το μέγεθος του locker (1/2/3) είναι υποχρεωτικό πεδίο και το επιλέγει ο πελάτης στην αίτηση· δεν είναι ρύθμιση.' ) . '</p>';
        echo '<table class="widefat striped" style="max-width: 480px;"><thead><tr><th>Μέγεθος</th><th>Διαστάσεις</th><th>Κωδικός (reference)</th></tr></thead><tbody>';
        foreach ( CC_Returns_Rules::LOCKER_SIZES as $size ) {
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( $size['label'] ),
                esc_html( $size['dims'] ),
                esc_html( $size['reference'] )
            );
        }
        echo '</tbody></table>';
    }

    public function texts_section() {
        echo '<p>' . esc_html( 'Τα κείμενα που βλέπει ο πελάτης μετά τη δημιουργία της επιστροφής. Αν αφήσετε κενό, χρησιμοποιείται το προεπιλεγμένο.' ) . '</p>';
    }

    public function notifications_section() {
        printf(
            '<p>%s <a href="%s">%s</a>.</p>',
            esc_html( 'Τα 3 email των επιστροφών ενεργοποιούνται και απενεργοποιούνται από τα' ),
            esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ),
            esc_html( 'WooCommerce → Ρυθμίσεις → Emails' )
        );
    }

    // ── Field callbacks ──────────────────────────────────────────────────────

    public function field_flag( $args ) {
        $option = $args['option'];
        $value  = (string) get_option( $option, $args['default'] );

        printf( '<input type="hidden" name="%s" value="0">', esc_attr( $option ) );
        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
            esc_attr( $option ),
            checked( $value, '1', false ),
            esc_html( $args['description'] )
        );
    }

    public function field_pickup() {
        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" disabled="disabled"> %2$s</label>',
            esc_attr( self::OPT_METHOD_PICKUP ),
            esc_html( 'Παραλαβή του δέματος από τη διεύθυνση του πελάτη' )
        );
        echo '<p class="description">' . esc_html( 'Διαθέσιμο όταν επιβεβαιωθούν από την Courier Center τα πεδία ημερομηνίας/ώρας παραλαβής' ) . '</p>';
    }

    public function field_window_days() {
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$d" min="0" step="1" class="small-text">',
            esc_attr( self::OPT_WINDOW_DAYS ),
            self::window_days()
        );
        echo '<p class="description">' . esc_html( 'Ημέρες από την ολοκλήρωση της παράδοσης μέσα στις οποίες επιτρέπεται η επιστροφή. 0 = χωρίς όριο.' ) . '</p>';
    }

    public function field_weight() {
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" min="0.1" max="30" step="0.1" class="small-text">',
            esc_attr( self::OPT_WEIGHT ),
            esc_attr( (string) self::weight() )
        );
        echo '<p class="description">' . esc_html( 'Το βάρος που δηλώνεται στο voucher της επιστροφής (0.1–30 kg).' ) . '</p>';
    }

    public function field_textarea( $args ) {
        $option = $args['label_for'];
        $value  = (string) get_option( $option, '' );
        if ( '' === trim( $value ) ) {
            $value = $args['default'];
        }
        printf(
            '<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>',
            esc_attr( $option ),
            esc_textarea( $value )
        );
    }

    public function field_accounts() {
        $rows = self::account_rows();
        ?>
        <table class="widefat striped" id="cc-returns-accounts" style="max-width: 640px;">
            <thead>
                <tr>
                    <th>Αρχικός κωδικός</th>
                    <th>Κωδικός επιστροφών</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $i => $row ) { $this->render_account_row( $i, $row ); } ?>
            </tbody>
        </table>
        <script type="text/html" id="cc-returns-account-template"><?php $this->render_account_row( '__i__', array() ); ?></script>
        <p><button type="button" class="button" id="cc-returns-account-add">+ Προσθήκη αντιστοίχισης</button></p>
        <p class="description">Γραμμή με κενό «Κωδικός επιστροφών» αγνοείται (χρησιμοποιείται ο αρχικός κωδικός). Μορφή: 100-0120-0001.</p>

        <script>
        jQuery(function($) {
            var $tbody = $('#cc-returns-accounts tbody');
            var ccNextRow = 0;

            $('#cc-returns-account-add').on('click', function() {
                var html = $('#cc-returns-account-template').html().replace(/__i__/g, 'n' + Date.now() + '_' + (ccNextRow++));
                $tbody.append(html);
            });

            $tbody.on('click', '.cc-ra-remove', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Μία γραμμή αντιστοίχισης ($i = '__i__' για το template).
     */
    private function render_account_row( $i, array $row ) {
        $row  = wp_parse_args( $row, array( 'original' => '', 'return' => '' ) );
        $base = self::OPT_ACCOUNTS . '[' . $i . ']';

        $options = array();
        foreach ( CC_Pickup_Points::get_points() as $point ) {
            if ( '' !== (string) $point['code'] ) {
                $options[ $point['code'] ] = CC_Pickup_Points::display_label( $point );
            }
        }
        // Αποθηκευμένος αρχικός που δεν υπάρχει πια στα σημεία: δείξε τον ώστε να μη χαθεί αθόρυβα
        $has_current = false;
        foreach ( array_keys( $options ) as $code ) {
            if ( CC_Returns_Rules::codes_equal( $code, $row['original'] ) ) {
                $row['original'] = (string) $code;
                $has_current     = true;
                break;
            }
        }
        if ( ! $has_current && '' !== $row['original'] ) {
            $options[ $row['original'] ] = $row['original'];
        }
        ?>
        <tr class="cc-returns-account-row">
            <td>
                <select name="<?php echo esc_attr( $base . '[original]' ); ?>">
                    <?php foreach ( $options as $code => $label ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $row['original'], (string) $code ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="text" style="width: 160px;" placeholder="100-0120-0001"
                       name="<?php echo esc_attr( $base . '[return]' ); ?>" value="<?php echo esc_attr( $row['return'] ); ?>">
            </td>
            <td style="white-space: nowrap;">
                <button type="button" class="button cc-ra-remove" style="color: #b32d2e;" title="Διαγραφή">🗑</button>
            </td>
        </tr>
        <?php
    }

    // ── Render ───────────────────────────────────────────────────────────────

    /**
     * Η φόρμα ρυθμίσεων· καλείται από τη σελίδα «Επιστροφές» (tab Ρυθμίσεις).
     */
    public static function render_tab() {
        settings_errors();
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( self::GROUP );
            do_settings_sections( self::PAGE );
            submit_button( 'Αποθήκευση Ρυθμίσεων' );
            ?>
        </form>
        <?php
    }
}
