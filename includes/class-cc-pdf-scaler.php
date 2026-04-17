<?php
/**
 * PDF Scaler - κάνει shrink το PDF του voucher ώστε να μην κόβεται στην εκτύπωση
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CC_PDF_Scaler {

    /**
     * Scale factor - πόσο μικραίνουμε το περιεχόμενο (0.95 = 95%)
     * Αφήνει ~5% περιθώριο για printer margins
     */
    const SCALE_FACTOR = 0.95;

    /**
     * Flag: είναι οι βιβλιοθήκες φορτωμένες;
     */
    private static $libraries_loaded = false;

    /**
     * Φόρτωσε FPDF + FPDI (lazy loading — μόνο όταν χρειαστεί)
     */
    private static function load_libraries() {
        if ( self::$libraries_loaded ) {
            return;
        }

        // FPDF πρώτα
        $fpdf_path = CC_WC_PLUGIN_DIR . 'lib/fpdf/fpdf.php';
        if ( ! file_exists( $fpdf_path ) ) {
            throw new Exception( 'FPDF library not found at: ' . $fpdf_path );
        }
        require_once $fpdf_path;

        // FPDI μετά (χρησιμοποιεί το δικό της autoloader)
        $fpdi_autoload = CC_WC_PLUGIN_DIR . 'lib/fpdi/src/autoload.php';
        if ( ! file_exists( $fpdi_autoload ) ) {
            throw new Exception( 'FPDI autoload not found at: ' . $fpdi_autoload );
        }
        require_once $fpdi_autoload;

        self::$libraries_loaded = true;
    }

    /**
     * Scale down PDF content so it fits within printer printable area.
     *
     * @param string $pdf_content  Raw PDF bytes (από το API)
     * @param float  $scale_factor Scale factor (default 0.95 = 95%)
     * @return string|WP_Error Scaled PDF bytes, ή WP_Error σε αποτυχία
     */
    public static function scale_pdf( $pdf_content, $scale_factor = self::SCALE_FACTOR ) {
        try {
            self::load_libraries();
        } catch ( Exception $e ) {
            return new WP_Error( 'pdf_libs_missing', $e->getMessage() );
        }

        // Γράψε το PDF σε προσωρινό αρχείο (η FPDI θέλει file path)
        $upload_dir = wp_upload_dir();
        $temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'cc-temp';

        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        $temp_file = $temp_dir . '/voucher-' . uniqid() . '.pdf';

        if ( file_put_contents( $temp_file, $pdf_content ) === false ) {
            return new WP_Error( 'pdf_write_failed', 'Δεν ήταν δυνατή η εγγραφή προσωρινού PDF' );
        }

        try {
            $pdf = new \setasign\Fpdi\Fpdi();

            // Μέτρησε πόσες σελίδες έχει το input PDF
            $page_count = $pdf->setSourceFile( $temp_file );

            // Για κάθε σελίδα του αρχικού, φτιάξε μια νέα σελίδα με scaled content
            for ( $page_num = 1; $page_num <= $page_count; $page_num++ ) {
                // Import την αρχική σελίδα
                $template_id = $pdf->importPage( $page_num );
                $size        = $pdf->getTemplateSize( $template_id );

                // Νέα σελίδα — ίδιου μεγέθους με την αρχική (A4 συνήθως)
                $pdf->AddPage(
                    $size['width'] > $size['height'] ? 'L' : 'P',
                    array( $size['width'], $size['height'] )
                );

                // Υπολόγισε νέες διαστάσεις (95% του original)
                $new_width  = $size['width'] * $scale_factor;
                $new_height = $size['height'] * $scale_factor;

                // Κεντράρισμα του shrunk content μέσα στη σελίδα
                // Αφήνουμε λίγο περισσότερο στο κάτω margin για να μη κόβεται τίποτα
                $x_offset = ( $size['width']  - $new_width )  / 2; // οριζόντιο κέντρο
                $y_offset = ( $size['height'] - $new_height ) / 2; // κάθετο κέντρο

                // Τοποθέτησε το scaled template
                $pdf->useTemplate( $template_id, $x_offset, $y_offset, $new_width, $new_height );
            }

            // Παράγαγε το PDF ως string ('S' = string output)
            $scaled_pdf = $pdf->Output( 'S' );

        } catch ( Exception $e ) {
            @unlink( $temp_file );
            return new WP_Error( 'pdf_scale_failed', 'Σφάλμα επεξεργασίας PDF: ' . $e->getMessage() );
        }

        // Καθάρισε το temp αρχείο
        @unlink( $temp_file );

        return $scaled_pdf;
    }

    /**
     * Παίρνει multi-page PDF (1 voucher/σελίδα 100x150mm)
     * και τα τοποθετεί 4 ανά A4 σελίδα σε 2x2 grid
     *
     * @param string $pdf_data Raw PDF bytes
     * @return string|WP_Error PDF bytes με A4 4-up layout, ή WP_Error σε αποτυχία
     */
    public static function arrange_4up( $pdf_data ) {
        try {
            self::load_libraries();
        } catch ( Exception $e ) {
            return new WP_Error( 'pdf_libs_missing', $e->getMessage() );
        }

        // A4: 210x297mm — 4 vouchers (100x150mm) σε 2x2 grid με περιθώρια
        $margin_x = 4;
        $margin_y = 4;
        $gap      = 3;

        $total_w = 210 - ( 2 * $margin_x );  // 206mm
        $total_h = 297 - ( 2 * $margin_y );  // 293mm

        $w = ( $total_w - $gap ) / 2;  // ~102mm
        $h = ( $total_h - $gap ) / 2;  // ~145.5mm

        $positions = array(
            array( $margin_x,              $margin_y              ),  // πάνω αριστερά
            array( $margin_x + $w + $gap,  $margin_y              ),  // πάνω δεξιά
            array( $margin_x,              $margin_y + $h + $gap  ),  // κάτω αριστερά
            array( $margin_x + $w + $gap,  $margin_y + $h + $gap  ),  // κάτω δεξιά
        );

        try {
            $fpdi = new \setasign\Fpdi\Fpdi();
            $fpdi->SetAutoPageBreak( false );

            $page_count = $fpdi->setSourceFile(
                \setasign\Fpdi\PdfParser\StreamReader::createByString( $pdf_data )
            );

            $slot = 0;

            for ( $i = 1; $i <= $page_count; $i++ ) {
                if ( $slot % 4 === 0 ) {
                    $fpdi->AddPage( 'P', array( 210, 297 ) );
                }

                $tpl = $fpdi->importPage( $i );
                $pos = $positions[ $slot % 4 ];
                $fpdi->useTemplate( $tpl, $pos[0], $pos[1], $w, $h );

                $slot++;
            }

            return $fpdi->Output( 'S' );

        } catch ( Exception $e ) {
            return new WP_Error( 'pdf_4up_failed', 'Σφάλμα arrange_4up: ' . $e->getMessage() );
        }
    }
}