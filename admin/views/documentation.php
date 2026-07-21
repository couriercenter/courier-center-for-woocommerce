<?php
/**
 * Περιεχόμενο in-admin οδηγού (Courier Center → Οδηγίες).
 *
 * Μεταβλητές από CC_Docs_Page::render_page():
 *   $version    — τρέχουσα έκδοση plugin
 *   $report_url — URL της φόρμας «Αναφορά Προβλήματος»
 *
 * ▶ ΣΕ ΚΑΘΕ ΝΕΑ ΕΚΔΟΣΗ: πρόσθεσε εγγραφή στην ενότητα «Τι νέο σε κάθε έκδοση»
 *   και ενημέρωσε όπου χρειάζεται τον οδηγό λειτουργιών.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap cc-docs">

    <style>
        .cc-docs { max-width: 980px; }
        .cc-docs h1 { display:flex; align-items:center; gap:10px; }
        .cc-docs .cc-ver-badge { font-size:13px; font-weight:600; color:#fff; background:#5DC122; padding:3px 10px; border-radius:12px; vertical-align:middle; }
        .cc-docs .cc-card { background:#fff; border:1px solid #e2e4e7; border-radius:8px; padding:20px 24px; margin:0 0 18px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .cc-docs .cc-card h2 { margin-top:0; border-bottom:2px solid #f0f0f1; padding-bottom:8px; }
        .cc-docs .cc-card h3 { margin:18px 0 6px; }
        .cc-docs table.cc-tbl { border-collapse:collapse; width:100%; margin:8px 0; }
        .cc-docs table.cc-tbl th, .cc-docs table.cc-tbl td { border:1px solid #e2e4e7; padding:8px 10px; text-align:left; vertical-align:top; font-size:13px; }
        .cc-docs table.cc-tbl th { background:#f6f7f7; }
        .cc-docs .cc-toc { columns:2; column-gap:32px; }
        .cc-docs .cc-toc a { text-decoration:none; }
        .cc-docs ol.cc-steps { margin:8px 0 8px 18px; }
        .cc-docs ol.cc-steps li { margin-bottom:6px; }
        .cc-docs .cc-tip { background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:3px; margin:10px 0; }
        .cc-docs .cc-warn { background:#fff8e1; border-left:4px solid #ffb900; padding:10px 14px; border-radius:3px; margin:10px 0; }
        .cc-docs .cc-new { display:inline-block; font-size:11px; font-weight:700; color:#fff; background:#2271b1; padding:1px 7px; border-radius:10px; margin-left:6px; vertical-align:middle; }
        .cc-docs code { background:#f0f0f1; padding:1px 5px; border-radius:3px; }
        .cc-docs .button-hero { margin-top:6px; }
    </style>

    <h1>📖 Courier Center for WooCommerce — Οδηγίες <span class="cc-ver-badge">v<?php echo esc_html( $version ); ?></span></h1>
    <p style="color:#666;max-width:760px;">Πλήρης οδηγός εγκατάστασης, ρυθμίσεων και χρήσης. Απαιτήσεις: WordPress 6.0+ · WooCommerce 8.0+ · PHP 7.4+.</p>

    <!-- ─────────────── Περιεχόμενα ─────────────── -->
    <div class="cc-card">
        <h2>Περιεχόμενα</h2>
        <div class="cc-toc">
            <p><a href="#cc-new">⭐ Τι νέο σε κάθε έκδοση</a></p>
            <p><a href="#cc-intro">1. Εισαγωγή</a></p>
            <p><a href="#cc-install">2. Εγκατάσταση</a></p>
            <p><a href="#cc-settings">3. Ρυθμίσεις</a></p>
            <p><a href="#cc-create">4. Δημιουργία Voucher</a></p>
            <p><a href="#cc-print">5. Εκτύπωση Ετικέτας</a></p>
            <p><a href="#cc-track">6. Παρακολούθηση Αποστολών</a></p>
            <p><a href="#cc-cancel">7. Ακύρωση Αποστολής</a></p>
            <p><a href="#cc-bulk">8. Μαζικές Ενέργειες</a></p>
            <p><a href="#cc-manifest">9. Manifest Παραλαβής</a></p>
            <p><a href="#cc-emails">10. Tracking στα Emails</a></p>
            <p><a href="#cc-support">11. Υποστήριξη</a></p>
            <p><a href="#cc-faq">12. Συχνές Ερωτήσεις</a></p>
        </div>
    </div>

    <!-- ─────────────── Τι νέο ─────────────── -->
    <div class="cc-card" id="cc-new">
        <h2>⭐ Τι νέο σε κάθε έκδοση</h2>

        <h3>v1.4.5</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Σημείωση πελάτη στο voucher</strong> <span class="cc-new">ΝΕΟ</span>: η σημείωση που αφήνει ο πελάτης στο checkout (π.χ. «κουδούνι 3ος όροφος») περνάει πλέον στα σχόλια του voucher. Μέχρι τώρα δεν εμφανιζόταν καθόλου.</li>
            <li><strong>Πεδίο αριθμού διεύθυνσης</strong> <span class="cc-new">ΝΕΟ</span>: αν το checkout σας έχει ξεχωριστό πεδίο «Αριθμός» (εκτός της Διεύθυνσης), επιλέξτε το από <strong>Ρυθμίσεις → Στοιχεία Παραλήπτη</strong> ώστε ο αριθμός να μπαίνει σωστά στη διεύθυνση του voucher. Η λίστα σας δείχνει τα πεδία που βρέθηκαν στις παραγγελίες σας, με δείγμα τιμής.</li>
            <li><strong>Διεύθυνση αποστολής στο voucher</strong> <span class="cc-new">ΝΕΟ</span>: όταν ο πελάτης δηλώνει διαφορετική διεύθυνση αποστολής από τη διεύθυνση χρέωσης, το voucher χρησιμοποιεί πλέον τη διεύθυνση αποστολής. Όποιο στοιχείο λείπει (π.χ. τηλέφωνο) συμπληρώνεται αυτόματα από τα στοιχεία χρέωσης.</li>
        </ul>

        <h3>v1.4.4</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Αφαίρεση BOX NOW και σε locker επιλεγμένο από πελάτη</strong>: το κουμπί «Αφαίρεση επιλογής BOX NOW» εμφανίζεται πλέον και όταν ο πελάτης έχει διαλέξει συγκεκριμένο locker — μέχρι τώρα υπήρχε μόνο στην αυτόματη εύρεση. Έτσι μπορείτε να στείλετε την παραγγελία ως κανονική αποστολή Courier Center, χωρίς να χρειάζεται να την πειράξετε αλλού.</li>
        </ul>

        <h3>v1.4.3</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Νέα αποστολή μετά από ακύρωση:</strong> μετά την ακύρωση μιας αποστολής, η φόρμα δημιουργίας voucher εμφανίζεται ξανά στη σελίδα της παραγγελίας, ώστε να στείλετε ξανά την <em>ίδια</em> παραγγελία χωρίς να χρειάζεται νέα. Το ακυρωμένο AWB παραμένει ορατό (διαγραμμένο) ως ιστορικό.</li>
            <li><strong>BOX NOW Parcel ID</strong>: για αποστολές BOX NOW εμφανίζεται πλέον ο αριθμός δέματος της BOX NOW — στο meta box της παραγγελίας και στη στήλη «Tracking» της λίστας παραγγελιών.</li>
            <li><strong>Διόρθωση BOX NOW στο checkout:</strong> όταν στο checkout υπήρχε μόνο μία διαθέσιμη μέθοδος αποστολής (οπότε το WooCommerce δεν εμφανίζει κουμπί επιλογής), το πλαίσιο BOX NOW δεν εμφανιζόταν καθόλου. Τώρα εμφανίζεται κανονικά.</li>
        </ul>

        <h3>v1.4.2</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Διόρθωση κρίσιμου bug βάρους/διαστάσεων:</strong> όταν το κατάστημα έχει τη μονάδα βάρους ή διαστάσεων του WooCommerce σε κάτι διαφορετικό από kg/cm (π.χ. γραμμάρια, mm, ίντσες), το plugin μετέτρεπε λανθασμένα τον αριθμό σαν να ήταν ήδη σε kg/cm — π.χ. προϊόν 60 γραμμαρίων εμφανιζόταν ως 60 kg και απέρριπτε τη δημιουργία voucher. Τώρα γίνεται σωστή μετατροπή μονάδων πριν σταλεί στο Courier Center API.</li>
        </ul>

        <h3>v1.4.1</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Νέα σελίδα «Οδηγίες»</strong> μέσα στο plugin — πλήρης οδηγός χρήσης &amp; changelog, πάντα ενημερωμένος.</li>
        </ul>

        <h3>v1.4.0</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>Μέθοδοι αποστολής που διαχειρίζεται το plugin:</strong> νέα ρύθμιση όπου επιλέγετε ποιες μεθόδους αποστολής αφορούν την Courier Center. Voucher (αυτόματα/μαζικά/χειροκίνητα) δημιουργείται μόνο για αυτές — παραγγελίες με άλλον μεταφορέα (π.χ. ACS) δεν δημιουργούν πλέον κατά λάθος αποστολή.</li>
            <li><strong>Νέα στήλη «Μέθοδος Αποστολής»</strong> στη λίστα παραγγελιών, με ένδειξη αν διαχειρίζεται από το plugin (πράσινο) ή όχι (γκρι).</li>
            <li><strong>Χειροκίνητη παράκαμψη:</strong> αν μια παραγγελία δεν ανήκει στις επιλεγμένες μεθόδους, η χειροκίνητη δημιουργία voucher ζητά επιβεβαίωση πριν προχωρήσει.</li>
            <li><strong>BOX NOW — προεπιλεγμένη &amp; κλειδωμένη επιλογή:</strong> νέα ρύθμιση ώστε το BOX NOW να εμφανίζεται ήδη επιλεγμένο όταν ο πελάτης διαλέξει μέθοδο Courier Center.</li>
        </ul>

        <h3>v1.3.x</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li><strong>BOX NOW στο checkout</strong> εμφανίζεται μόνο όταν ο πελάτης επιλέξει τη μέθοδο αποστολής Courier Center.</li>
            <li>Βελτιώσεις ασφαλείας &amp; σταθερότητας.</li>
        </ul>
    </div>

    <!-- ─────────────── 1. Εισαγωγή ─────────────── -->
    <div class="cc-card" id="cc-intro">
        <h2>1. Εισαγωγή</h2>
        <p>Το plugin Courier Center for WooCommerce σας επιτρέπει να διαχειρίζεστε τις αποστολές σας μέσα από το WooCommerce admin, χωρίς ξεχωριστά συστήματα.</p>
        <table class="cc-tbl">
            <tr><th style="width:230px;">Λειτουργία</th><th>Περιγραφή</th></tr>
            <tr><td>Δημιουργία Voucher</td><td>Δημιουργία ετικέτας αποστολής για κάθε παραγγελία</td></tr>
            <tr><td>Εκτύπωση Ετικέτας</td><td>Εκτύπωση PDF voucher για επικόλληση στο δέμα</td></tr>
            <tr><td>Ενημέρωση Status</td><td>Αυτόματη παρακολούθηση κατάστασης αποστολής</td></tr>
            <tr><td>Ακύρωση Αποστολής</td><td>Ακύρωση voucher αν χρειαστεί</td></tr>
            <tr><td>Μαζικές Ενέργειες</td><td>Δημιουργία &amp; εκτύπωση πολλών vouchers ταυτόχρονα</td></tr>
            <tr><td>Manifest Παραλαβής</td><td>Εκτύπωση λίστας για τον οδηγό Courier Center</td></tr>
            <tr><td>Tracking στα Emails</td><td>Αυτόματη αποστολή tracking number στον πελάτη</td></tr>
            <tr><td>BOX NOW Support</td><td>Αποστολή σε lockers BOX NOW</td></tr>
            <tr><td>Αντικαταβολή (COD)</td><td>Πλήρης υποστήριξη αποστολών με αντικαταβολή</td></tr>
            <tr><td>Έλεγχος μεθόδων αποστολής</td><td>Το plugin διαχειρίζεται μόνο τις μεθόδους αποστολής που επιλέγετε</td></tr>
        </table>
    </div>

    <!-- ─────────────── 2. Εγκατάσταση ─────────────── -->
    <div class="cc-card" id="cc-install">
        <h2>2. Εγκατάσταση</h2>
        <ol class="cc-steps">
            <li><strong>Κατεβάστε το plugin</strong> — το αρχείο <code>courier-center-for-woocommerce.zip</code> από <code>https://www.courier.gr/woocommerce-plugin</code></li>
            <li><strong>Ανοίξτε το WordPress Admin</strong> — <code>https://[το-site-σας]/wp-admin</code></li>
            <li><strong>Plugins → Add New Plugin</strong></li>
            <li><strong>Upload Plugin</strong> — επιλέξτε το .zip και «Install Now»</li>
            <li><strong>Activate Plugin</strong></li>
            <li><strong>Επιβεβαίωση</strong> — στο αριστερό menu εμφανίζεται το μενού «Courier Center»</li>
        </ol>
        <div class="cc-tip"><strong>Αυτόματες ενημερώσεις:</strong> όταν κυκλοφορεί νέα έκδοση, στη σελίδα Plugins εμφανίζεται «Update now». Τα δεδομένα και οι ρυθμίσεις σας <strong>δεν χάνονται</strong>.</div>
    </div>

    <!-- ─────────────── 3. Ρυθμίσεις ─────────────── -->
    <div class="cc-card" id="cc-settings">
        <h2>3. Ρυθμίσεις</h2>
        <p>Πηγαίνετε: <strong>Courier Center → Ρυθμίσεις</strong>.</p>

        <h3>Σύνδεση με το API</h3>
        <table class="cc-tbl">
            <tr><th style="width:230px;">Πεδίο</th><th>Περιγραφή</th></tr>
            <tr><td>User Alias</td><td>Το όνομα χρήστη του API σας</td></tr>
            <tr><td>Credential Value</td><td>Ο κωδικός πρόσβασης του API</td></tr>
            <tr><td>API Key</td><td>Το κλειδί ασφαλείας API</td></tr>
            <tr><td>Carrier Billing Account</td><td>Ο αριθμός λογαριασμού χρέωσης (π.χ. 100-XXXX-XXXX)</td></tr>
        </table>
        <p>Μόλις βάλετε τα credentials, πατήστε <strong>«Test &amp; Auto-fill»</strong>: ελέγχει τα credentials, συμπληρώνει αυτόματα τα στοιχεία αποστολέα και αποθηκεύει τις ρυθμίσεις.</p>

        <h3>Ρυθμίσεις Εκτύπωσης</h3>
        <table class="cc-tbl">
            <tr><th style="width:230px;">Template</th><th>Χρήση</th></tr>
            <tr><td>pdf (default)</td><td>A4, 3 vouchers/σελίδα — για μη προεκτυπωμένα (plain) αυτοκόλλητα</td></tr>
            <tr><td>clean</td><td>A4, 3 vouchers/σελίδα — για προεκτυπωμένα αυτοκόλλητα</td></tr>
            <tr><td>singlepdf_100x150_4up</td><td>A4, 4 vouchers/σελίδα (2×2) — κανονικές αποστολές &amp; BOX NOW</td></tr>
            <tr><td>singlepdf</td><td>Θερμικός εκτυπωτής 205×100mm</td></tr>
            <tr><td>singleclean</td><td>Θερμικός 205×100mm (προεκτυπωμένο)</td></tr>
            <tr><td>singlepdf_100x150</td><td>Θερμικός 100×150mm</td></tr>
            <tr><td>singlepdf_100x170</td><td>Θερμικός 100×170mm</td></tr>
        </table>

        <h3>Tracking &amp; Emails</h3>
        <p>Το πεδίο <strong>«Tracking URL»</strong> καθορίζει τον σύνδεσμο παρακολούθησης. Προεπιλογή: <code>https://www.courier.gr/track/result?tracknr={{tracking}}</code> — το <code>{{tracking}}</code> αντικαθίσταται αυτόματα με τον αριθμό αποστολής. Το checkbox <strong>«Ενημέρωση πελάτη»</strong> στέλνει αυτόματα AWB &amp; σύνδεσμο μέσα στα emails του WooCommerce.</p>

        <h3>📍 Στοιχεία Παραλήπτη</h3>
        <p>Το voucher χρησιμοποιεί τη <strong>διεύθυνση αποστολής</strong> του πελάτη· όποιο πεδίο λείπει (π.χ. τηλέφωνο) συμπληρώνεται από τα στοιχεία <strong>χρέωσης</strong>.</p>
        <p><strong>Πεδίο αριθμού διεύθυνσης</strong> <span class="cc-new">ΝΕΟ</span>: μόνο για καταστήματα όπου το checkout έχει <strong>ξεχωριστό πεδίο «Αριθμός»</strong> εκτός της Διεύθυνσης. Επιλέξτε το από τη λίστα (δείχνει τα πεδία που βρέθηκαν στις τελευταίες παραγγελίες, με δείγμα τιμής) ή γράψτε το χειροκίνητα. Ο αριθμός θα προστίθεται στο τέλος της διεύθυνσης στο voucher. Προεπιλογή «Κανένα»: ο αριθμός θεωρείται ότι είναι ήδη μέσα στη «Διεύθυνση».</p>

        <h3>📦 BOX NOW Widget (Checkout)</h3>
        <p>Ενεργοποίηση από <strong>Courier Center → Ρυθμίσεις → BOX NOW Widget</strong>. Εμφανίζεται στο checkout <strong>μόνο όταν ο πελάτης επιλέξει τη μέθοδο αποστολής Courier Center</strong>. Δύο modes: <em>pick</em> (επιλογή locker από χάρτη) ή <em>auto</em> (αυτόματη ανάθεση στο κοντινότερο).</p>
        <p><strong>Προεπιλεγμένη επιλογή BOX NOW</strong>: αν την ενεργοποιήσετε, το BOX NOW εμφανίζεται ήδη τσεκαρισμένο και κλειδωμένο μόλις ο πελάτης διαλέξει μέθοδο Courier Center. Για να το αφαιρέσει, ο πελάτης επιλέγει άλλη μέθοδο αποστολής.</p>
        <p><strong>BOX NOW Parcel ID</strong>: μόλις δημιουργηθεί voucher BOX NOW, το Courier Center επιστρέφει και τον αριθμό δέματος της BOX NOW. Εμφανίζεται στο meta box της παραγγελίας και στη στήλη «Tracking» της λίστας παραγγελιών. Υπάρχει μόνο σε αποστολές BOX NOW και μόνο όσο η αποστολή είναι ενεργή.</p>

        <h3>🚚 Μέθοδοι αποστολής που διαχειρίζεται το plugin</h3>
        <p>Στην ομώνυμη ενότητα επιλέγετε ποιες μεθόδους αποστολής αντιστοιχούν στην Courier Center. Το plugin δημιουργεί voucher (αυτόματα, μαζικά ή χειροκίνητα) <strong>μόνο</strong> για παραγγελίες με αυτές τις μεθόδους.</p>
        <div class="cc-warn"><strong>Απαιτείται τουλάχιστον μία επιλογή.</strong> Αν προσπαθήσετε να αποθηκεύσετε χωρίς καμία επιλεγμένη μέθοδο, εμφανίζεται μήνυμα και η ρύθμιση δεν αποθηκεύεται κενή. Όσο δεν έχει ρυθμιστεί, το plugin θεωρεί ότι διαχειρίζεται όλες τις παραγγελίες.</div>
    </div>

    <!-- ─────────────── 4. Δημιουργία Voucher ─────────────── -->
    <div class="cc-card" id="cc-create">
        <h2>4. Δημιουργία Voucher</h2>
        <p>Για κάθε παραγγελία, από το meta box «Courier Center» στη δεξιά στήλη:</p>
        <ol class="cc-steps">
            <li>Ανοίξτε μια παραγγελία (WooCommerce → Orders)</li>
            <li>Βρείτε το box «Courier Center» (δεξιά στήλη)</li>
            <li>Επιλέξτε Υπηρεσία: Επόμενη Μέρα / Αυθημερόν 3ω / Αυθημερόν 5ω</li>
            <li>Επιλέξτε Επιστροφικό αν χρειάζεται: Χωρίς / Προαιρετικό / Υποχρεωτικό</li>
            <li>Πολλαπλά τεμάχια αν χρειάζεται: τσεκάρετε «Πολλαπλή Αποστολή» και ορίστε αριθμό</li>
            <li>Πατήστε «Δημιουργία Voucher» — εμφανίζεται ο AWB αριθμός</li>
        </ol>

        <div class="cc-warn">
            <strong>Έλεγχος μεθόδου αποστολής</strong>: αν η παραγγελία χρησιμοποιεί μέθοδο που <em>δεν</em> διαχειρίζεται το plugin, πριν τη δημιουργία εμφανίζεται επιβεβαίωση («Είστε σίγουροι ότι θέλετε να δημιουργήσετε την αποστολή στην Courier Center;»). Η αυτόματη &amp; μαζική δημιουργία τις παραλείπει εντελώς.
        </div>

        <h3>Παραγγελίες με BOX NOW</h3>
        <p>Όταν ο πελάτης έχει επιλέξει BOX NOW στο checkout, το meta box δείχνει το locker (ή την ένδειξη αυτόματης εύρεσης) και ένα μόνο κουμπί «Δημιουργία BOX NOW Voucher».</p>
        <p><strong>Αφαίρεση επιλογής BOX NOW</strong>: με το κουμπί «Αφαίρεση επιλογής BOX NOW» η παραγγελία γυρίζει σε κανονική αποστολή Courier Center και εμφανίζονται ξανά όλες οι επιλογές (υπηρεσία, επιστροφικό, πολλαπλά τεμάχια). Διαθέσιμο <strong>και</strong> στις δύο περιπτώσεις — είτε ο πελάτης διάλεξε συγκεκριμένο locker είτε αυτόματη εύρεση.</p>

        <h3>Σημαντικές προϋποθέσεις</h3>
        <ul style="list-style:disc;margin-left:20px;">
            <li>ΤΚ (5 ψηφία), διεύθυνση, πόλη και όνομα παραλήπτη συμπληρωμένα. Για BOX NOW απαιτείται και κινητό τηλέφωνο.</li>
            <li>BOX NOW + Αντικαταβολή: δεν επιτρέπεται. Αντικαταβολή εξωτερικού: δεν επιτρέπεται.</li>
            <li>Μέγιστο βάρος 30 kg/τεμάχιο. Μέγιστες διαστάσεις 180 cm/πλευρά. Μέγιστος γύρος (μήκος + 2×(πλάτος+ύψος)) 300 cm.</li>
        </ul>
    </div>

    <!-- ─────────────── 5. Εκτύπωση ─────────────── -->
    <div class="cc-card" id="cc-print">
        <h2>5. Εκτύπωση Ετικέτας</h2>
        <p><strong>Single:</strong> από τη σελίδα παραγγελίας πατήστε «Εκτύπωση Ετικέτας» — ανοίγει το PDF voucher (τα επιστροφικά εκτυπώνονται αυτόματα μαζί).</p>
        <p><strong>Μαζική:</strong> από τη λίστα παραγγελιών τσεκάρετε παραγγελίες → Bulk Actions → «CC — Μαζική Εκτύπωση Vouchers» (ή «CC — Μαζική Εκτύπωση BOX NOW» για BOX NOW).</p>
        <div class="cc-tip"><strong>Ρυθμίσεις εκτυπωτή:</strong> Paper A4 · Scale 100% (Actual size) · Margins None/Minimum. <strong>Μην</strong> χρησιμοποιείτε «Fit to page».</div>
    </div>

    <!-- ─────────────── 6. Παρακολούθηση ─────────────── -->
    <div class="cc-card" id="cc-track">
        <h2>6. Παρακολούθηση Αποστολών</h2>
        <p><strong>Manual:</strong> από κάθε παραγγελία πατήστε «Ενημέρωση Status» (max 1 φορά/ώρα ανά αποστολή). <strong>Αυτόματη (Cron):</strong> ενημερώνει όλες τις ενεργές αποστολές κάθε 2 ώρες. <strong>Bulk:</strong> «CC — Ενημέρωση Status».</p>

        <h3>Στήλες στη λίστα παραγγελιών</h3>
        <p>Εμφανίζονται αυτόματα οι στήλες «CC Voucher», «CC Status», «Τύπος» και <strong>«Μέθοδος Αποστολής»</strong> (πράσινο badge = διαχειρίζεται από το plugin, γκρι = όχι).</p>
        <table class="cc-tbl">
            <tr><th style="width:230px;">Badge</th><th>Σημασία</th></tr>
            <tr><td>Νέα (μπλε)</td><td>Voucher δημιουργήθηκε, χωρίς ενημέρωση status ακόμα</td></tr>
            <tr><td>Στον σταθμό (μπλε)</td><td>Το δέμα είναι στον σταθμό της Courier Center</td></tr>
            <tr><td>Σε διανομή (μπλε)</td><td>Ο οδηγός παρέλαβε το δέμα για παράδοση</td></tr>
            <tr><td>Παραδόθηκε (πράσινο)</td><td>Επιτυχής παράδοση — η παραγγελία ολοκληρώνεται αυτόματα</td></tr>
            <tr><td>Αποτυχία (κίτρινο)</td><td>Αποτυχία παράδοσης — χρειάζεται επικοινωνία</td></tr>
            <tr><td>Ακυρωμένη (κόκκινο)</td><td>Η αποστολή ακυρώθηκε</td></tr>
            <tr><td>Επιστροφή στον αποστολέα (κόκκινο)</td><td>Η αποστολή επιστράφηκε στον αποστολέα</td></tr>
        </table>
    </div>

    <!-- ─────────────── 7. Ακύρωση ─────────────── -->
    <div class="cc-card" id="cc-cancel">
        <h2>7. Ακύρωση Αποστολής</h2>
        <p>Από τη σελίδα παραγγελίας πατήστε «Ακύρωση Αποστολής» (με επιβεβαίωση).</p>
        <div class="cc-warn">Η ακύρωση είναι δυνατή <strong>μόνο πριν</strong> ο οδηγός παραλάβει το δέμα. Αν έχει ήδη σκαναριστεί, μπορεί να αποτύχει. Μετά την ακύρωση μπορείτε να δημιουργήσετε νέο voucher για την ίδια παραγγελία.</div>
    </div>

    <!-- ─────────────── 8. Bulk ─────────────── -->
    <div class="cc-card" id="cc-bulk">
        <h2>8. Μαζικές Ενέργειες (Bulk Actions)</h2>
        <p>Από τη λίστα παραγγελιών τσεκάρετε παραγγελίες και επιλέξτε από το dropdown:</p>
        <table class="cc-tbl">
            <tr><th style="width:300px;">Ενέργεια</th><th>Αποτέλεσμα</th></tr>
            <tr><td>CC — Δημιουργία Vouchers (Επόμενη Μέρα)</td><td>Δημιουργεί voucher για κάθε επιλεγμένη παραγγελία</td></tr>
            <tr><td>CC — Ενημέρωση Status</td><td>Ενημερώνει το status όλων των επιλεγμένων</td></tr>
            <tr><td>CC — Μαζική Εκτύπωση Vouchers</td><td>Εκτυπώνει όλα τα κανονικά vouchers σε ένα PDF</td></tr>
            <tr><td>CC — Μαζική Εκτύπωση BOX NOW</td><td>Εκτυπώνει μόνο τα BOX NOW vouchers</td></tr>
        </table>
        <div class="cc-tip">Παραγγελίες με υπάρχον voucher παραλείπονται. <strong>Παραγγελίες με μη διαχειρίσιμη μέθοδο αποστολής παραλείπονται επίσης</strong>. Μετά τη μαζική δημιουργία, ελέγξτε το notice για τα αποτελέσματα.</div>
    </div>

    <!-- ─────────────── 9. Manifest ─────────────── -->
    <div class="cc-card" id="cc-manifest">
        <h2>9. Manifest Παραλαβής</h2>
        <p>Το Manifest είναι η λίστα αποστολών που παραδίδετε στον οδηγό κάθε μέρα.</p>
        <ol class="cc-steps">
            <li><strong>Courier Center → Manifest Παραλαβής</strong></li>
            <li>Επιλέξτε ημερομηνία (Σήμερα / Χθες ή χειροκίνητα)</li>
            <li>«Λήψη Manifest PDF» για προβολή/αποθήκευση</li>
            <li>Εκτυπώστε και δώστε το στον οδηγό για υπογραφή</li>
        </ol>
        <p style="color:#666;font-size:13px;">Το manifest εμφανίζει όλες τις αποστολές του λογαριασμού σας για την επιλεγμένη ημερομηνία.</p>
    </div>

    <!-- ─────────────── 10. Emails ─────────────── -->
    <div class="cc-card" id="cc-emails">
        <h2>10. Tracking στα Emails Πελατών</h2>
        <p>Κάθε email του WooCommerce προς τον πελάτη συμπεριλαμβάνει αυτόματα ένα κουτί «Στοιχεία Αποστολής Courier Center» με τον AWB και κουμπί «Παρακολούθηση Αποστολής», εφόσον έχει δημιουργηθεί voucher. Ελέγχεται από το checkbox «Ενημέρωση πελάτη» στις Ρυθμίσεις.</p>
        <div class="cc-tip">Για αξιόπιστη αποστολή email, συνιστάται plugin SMTP (π.χ. WP Mail SMTP).</div>
    </div>

    <!-- ─────────────── 11. Υποστήριξη ─────────────── -->
    <div class="cc-card" id="cc-support">
        <h2>11. Υποστήριξη &amp; Αναφορά Προβλήματος</h2>
        <p>Για οποιοδήποτε τεχνικό πρόβλημα ή ερώτημα, χρησιμοποιήστε τη <strong>φόρμα Αναφοράς Προβλήματος</strong> μέσα στο plugin — η αναφορά αποστέλλεται απευθείας στην τεχνική ομάδα της Courier Center, μαζί με τα τεχνικά στοιχεία του site σας.</p>
        <p><a href="<?php echo esc_url( $report_url ); ?>" class="button button-primary button-hero">🐛 Άνοιγμα φόρμας Αναφοράς Προβλήματος</a></p>
        <p style="color:#666;font-size:13px;">Συμπληρώστε τίτλο, περιγραφή (τι κάνατε, τι περιμένατε, τι συνέβη) και σοβαρότητα, και πατήστε «Αποστολή».</p>
    </div>

    <!-- ─────────────── 12. FAQ ─────────────── -->
    <div class="cc-card" id="cc-faq">
        <h2>12. Συχνές Ερωτήσεις (FAQ)</h2>

        <h3>Γιατί εμφανίζεται σφάλμα κατά τη δημιουργία voucher;</h3>
        <p>Ελέγξτε: ΤΚ 5 ψηφία, για BOX NOW υπάρχει κινητό (69XXXXXXXX), και τα credentials είναι σωστά (Test &amp; Auto-fill).</p>

        <h3>Δημιούργησα voucher σε λάθος courier — γιατί;</h3>
        <p>Βεβαιωθείτε ότι στις <strong>«Μέθοδοι αποστολής που διαχειρίζεται το plugin»</strong> έχετε επιλέξει μόνο τις μεθόδους Courier Center. Έτσι παραγγελίες με άλλον μεταφορέα δεν δημιουργούν αυτόματα αποστολή.</p>

        <h3>Γιατί δεν βλέπω το μενού Courier Center;</h3>
        <p>Το WooCommerce πρέπει να είναι εγκατεστημένο και ενεργό. Αν είναι ανενεργό, το plugin δεν φορτώνει.</p>

        <h3>Μπορώ να κάνω BOX NOW με αντικαταβολή;</h3>
        <p>Όχι. Τα BOX NOW lockers δεν υποστηρίζουν αντικαταβολή.</p>

        <h3>Πότε εμφανίζεται το tracking στο email;</h3>
        <p>Μόνο αν έχει δημιουργηθεί voucher πριν σταλεί το email. Το επόμενο email μετά τη δημιουργία voucher θα το συμπεριλαμβάνει.</p>

        <h3>Η αυτόματη ενημέρωση status δεν λειτουργεί;</h3>
        <p>Courier Center → Ρυθμίσεις (κάτω μέρος). Αν γράφει «Δεν είναι προγραμματισμένο», απενεργοποιήστε &amp; ξανα-ενεργοποιήστε το plugin για να επαναφερθεί το schedule.</p>

        <h3>Πώς ενημερώνεται το plugin;</h3>
        <p>Αυτόματα — notification στη σελίδα Plugins → «Update now», χωρίς απώλεια δεδομένων.</p>
    </div>

    <p style="color:#888;font-size:12px;text-align:center;margin:24px 0;">Courier Center for WooCommerce · v<?php echo esc_html( $version ); ?> · courier.gr</p>

</div>
