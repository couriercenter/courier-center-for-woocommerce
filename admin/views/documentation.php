<?php
/**
 * Περιεχόμενο in-admin οδηγού (Courier Center → Οδηγίες).
 *
 * ΠΑΡΑΓΕΤΑΙ από το build_docs.py (courier-center-releases/odigies-deigma-2026-10) από το εγκεκριμένο
 * deigma-woocommerce.html — αλλαγές στο κείμενο γίνονται εκεί και ξαναχτίζεται.
 * Εξαίρεση: η ενότητα «Τι νέο σε κάθε έκδοση» είναι στο docs-changelog.php (χειροκίνητα, σε κάθε έκδοση).
 *
 * Μεταβλητές από CC_Docs_Page::render_page():
 *   $version    — τρέχουσα έκδοση plugin
 *   $report_url — URL της φόρμας «Αναφορά Προβλήματος»
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
<style>

  .ccd { max-width: 900px; margin: 0 auto; font-size: 15px; line-height: 1.6; color: #1d2327; }
  .ccd .x-draftnote { background:#464342; color:#fff; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:18px; }
  .ccd h1 { font-size: 24px; margin: 0 0 4px; }
  .ccd .lead { color:#555; margin:0 0 18px; }
  .ccd .card { background:#fff; border:1px solid #e2e4e7; border-radius:8px; padding:20px 24px; margin:0 0 18px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .ccd .card h2 { margin:0 0 10px; font-size:20px; border-bottom:2px solid #f0f0f1; padding-bottom:8px; }
  .ccd .card h3 { margin:22px 0 6px; font-size:16px; }
  .ccd .part { margin:30px 0 10px; font-size:13px; font-weight:700; letter-spacing:.06em; color:#C44F28; text-transform:uppercase; }
  .ccd .what { font-size:15px; color:#333; background:#f6f7f7; padding:8px 12px; border-radius:4px; margin:0 0 12px; }
  .ccd .step { display:flex; gap:12px; margin:14px 0 6px; align-items:flex-start; }
  .ccd .step .n { flex:0 0 28px; height:28px; border-radius:50%; background:#F37029; color:#fff; font-weight:700; text-align:center; line-height:28px; }
  .ccd .step .t { flex:1; }
  .ccd .shot { margin:6px 0 4px 40px; }
  .ccd .shot img { max-width:100%; border:1px solid #dcdcde; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,.08); display:block; }
  .ccd .shot.narrow img { max-width:320px; }
  .ccd .shot .cap { font-size:12px; color:#666; margin-top:4px; }
  .ccd .mk { display:inline-block; width:20px; height:20px; border-radius:50%; background:#e2231a; color:#fff; font:bold 12px/20px Arial,sans-serif; text-align:center; margin:0 2px; vertical-align:1px; }
  .ccd .ex { background:#eef7ee; border-left:4px solid #3a3; padding:10px 14px; border-radius:3px; margin:12px 0; }
  .ccd .warn { background:#fff4e5; border-left:4px solid #F37029; padding:10px 14px; border-radius:3px; margin:12px 0; }
  .ccd .tip { background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:3px; margin:12px 0; }
  .ccd table { border-collapse:collapse; width:100%; margin:8px 0; }
  .ccd th, .ccd td { border:1px solid #e2e4e7; padding:8px 10px; text-align:left; vertical-align:top; font-size:14px; }
  .ccd th { background:#f6f7f7; }
  .ccd .chg { border-left:5px solid #f5c400; background:#fffbe6; padding:8px 12px 4px; margin:12px 0; border-radius:3px; }
  .ccd .chg-tag { display:inline-block; font-size:11px; font-weight:700; color:#5a4a00; background:#f5c400; padding:1px 7px; border-radius:10px; margin-bottom:4px; }
  .ccd .ph { border:2px dashed #b5b5b5; background:#fafafa; color:#777; padding:22px; text-align:center; border-radius:6px; margin:6px 0 4px 40px; font-size:13px; }
  .ccd .toc { columns:2; column-gap:30px; font-size:14px; }
  .ccd .toc p { margin:0 0 4px; break-inside:avoid; }
  .ccd .toc a { text-decoration:none; }
  .ccd .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; font-weight:600; color:#fff; }
  .ccd code { background:#f0f0f1; padding:1px 5px; border-radius:3px; }
  .ccd .start { background:linear-gradient(135deg,#fff7f1,#fff); border:2px solid #F68B1F; }
  .ccd .start ol { margin:6px 0 0 20px; padding:0; }
  .ccd .start li { margin-bottom:8px; }
  .ccd dl dt { font-weight:700; margin-top:10px; }
  .ccd dl dd { margin:2px 0 0 0; color:#333; }

  .ccd .card { max-width:none; min-width:0; }
  .ccd .cc-new { display:inline-block; background:#5DC122; color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:8px; vertical-align:1px; }
</style>
<div class="ccd">

<h1>📖 Courier Center for WooCommerce — Οδηγίες <span class="badge" style="background:#5DC122">v<?php echo esc_html( $version ); ?></span></h1>
<p class="lead">Με αυτό το plugin βγάζετε vouchers της Courier Center κατευθείαν από τις παραγγελίες του καταστήματός σας, χωρίς να ξαναγράφετε στοιχεία πουθενά. Οι οδηγίες είναι γραμμένες βήμα-βήμα· αν ξεκινάτε τώρα, διαβάστε πρώτα το «Ξεκινήστε εδώ».</p>
<!-- ─── Περιεχόμενα ─── -->
<div class="card">
<h2>Περιεχόμενα</h2>
<div class="toc">
<p><a href="#cc-start"><b>🚀 Ξεκινήστε εδώ — 5 βήματα</b></a></p>
<p><b>Α. Στήσιμο</b></p>
<p>  <a href="#cc-a1">Α1. Εγκατάσταση</a></p>
<p>  <a href="#cc-a2">Α2. Σύνδεση με τον λογαριασμό σας</a></p>
<p>  <a href="#cc-a3">Α3. Οι ρυθμίσεις μία-μία</a></p>
<p>  <a href="#cc-a4">Α4. Παράδειγμα: πόρτα-πόρτα και BOX NOW χωριστά στο checkout</a></p>
<p><b>Β. Καθημερινή δουλειά</b></p>
<p>  <a href="#cc-b1">Β1. Βγάζω voucher</a></p>
<p>  <a href="#cc-b2">Β2. Τυπώνω την ετικέτα</a></p>
<p>  <a href="#cc-b3">Β3. Παραγγελίες BOX NOW</a></p>
<p>  <a href="#cc-b4">Β4. Αλλάζω BOX NOW locker</a></p>
<p>  <a href="#cc-b5">Β5. Ακυρώνω αποστολή</a></p>
<p>  <a href="#cc-b6">Β6. Πολλές παραγγελίες μαζί</a></p>
<p>  <a href="#cc-b7">Β7. Λίστα για τον οδηγό (Manifest)</a></p>
<p><b>Γ. Μετά την αποστολή</b></p>
<p>  <a href="#cc-c1">Γ1. Πού είναι το δέμα;</a></p>
<p>  <a href="#cc-c2">Γ2. Τι βλέπει ο πελάτης στο email</a></p>
<p>  <a href="#cc-c3">Γ3. Επιστροφές</a></p>
<p><b>Δ. Κάτι δεν πάει καλά</b></p>
<p>  <a href="#cc-d1">Δ1. Μηνύματα σφάλματος</a></p>
<p>  <a href="#cc-d2">Δ2. Συχνές ερωτήσεις</a></p>
<p>  <a href="#cc-d3">Δ3. Επικοινωνία με την Courier Center</a></p>
<p><a href="#cc-gloss">📖 Λεξικό όρων</a></p>
<p><a href="#cc-new">⭐ Τι νέο σε κάθε έκδοση</a></p>
</div>
</div>
<!-- ─── Ξεκινήστε εδώ ─── -->
<div class="card start" id="cc-start">
<h2>🚀 Ξεκινήστε εδώ — από την εγκατάσταση μέχρι το 1ο voucher</h2>
<p>Θα χρειαστείτε: το αρχείο <code>courier-center-for-woocommerce.zip</code> και τα <b>στοιχεία σύνδεσης (API)</b> που σας έστειλε η Courier Center (User Alias, Credential Value, API Key, κεντρικός κωδικός).</p>
<ol>
<li><b>Εγκαταστήστε το plugin:</b> Plugins → Add New Plugin → Upload Plugin → διαλέγετε το .zip → Install Now → Activate. <a href="#cc-a1">(αναλυτικά)</a></li>
<li><b>Συνδέστε τον λογαριασμό σας:</b> Courier Center → Ρυθμίσεις → γράφετε τα 4 στοιχεία σύνδεσης → πατάτε «Test &amp; Auto-fill στοιχεία». <a href="#cc-a2">(αναλυτικά)</a></li>
<li><b>Πείτε στο plugin ποια αποστολή είναι Courier Center:</b> στο κάτω μέρος των Ρυθμίσεων τσεκάρετε τη μέθοδο αποστολής σας (π.χ. «Courier center») → Αποθήκευση Ρυθμίσεων. <a href="#cc-a3-methods">(αναλυτικά)</a></li>
<li><b>Βγάλτε το πρώτο voucher:</b> WooCommerce → Orders → ανοίγετε μια παραγγελία → δεξιά, στο πλαίσιο «Courier Center», πατάτε «Δημιουργία Voucher». <a href="#cc-b1">(αναλυτικά)</a></li>
<li><b>Τυπώστε και παραδώστε:</b> πατάτε «Εκτύπωση Ετικέτας», κολλάτε την ετικέτα στο δέμα και, όταν έρθει ο οδηγός, του δίνετε το δέμα μαζί με τη <a href="#cc-b7">Λίστα Παράδοσης (Manifest)</a>.</li>
</ol>
<div class="tip" style="margin-bottom:0">Έτοιμοι! Από εδώ και πέρα ο πελάτης παίρνει αυτόματα τον αριθμό αποστολής στο email του και η παραγγελία ολοκληρώνεται μόνη της όταν παραδοθεί το δέμα.</div>
</div>
<!-- ═══════════ Α. ΣΤΗΣΙΜΟ ═══════════ -->
<div class="part">Α. Στήσιμο</div>
<div class="card" id="cc-a1">
<h2>Α1. Εγκατάσταση</h2>
<p class="what">Ανεβάζετε το plugin στο WordPress και το ενεργοποιείτε. Γίνεται μία φορά.</p>
<div class="step"><div class="n">1</div><div class="t">Στο μενού αριστερά πατήστε <b>Plugins</b> → <b>Add New Plugin</b> και μετά το κουμπί <b>Upload Plugin</b> πάνω-πάνω.</div></div>
<div class="step"><div class="n">2</div><div class="t">Πατήστε <b>Choose File</b> <span class="mk">1</span>, διαλέξτε το αρχείο <code>courier-center-for-woocommerce.zip</code> από τον υπολογιστή σας και πατήστε <b>Install Now</b> <span class="mk">2</span>.</div></div>
<div class="shot"><img alt="Σελίδα «Add Plugins» → Upload Plugin" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-install-upload.webp' ); ?>"/><div class="cap">Σελίδα «Add Plugins» → Upload Plugin</div></div>
<div class="step"><div class="n">3</div><div class="t">Όταν τελειώσει, πατήστε <b>Activate Plugin</b>.</div></div>
<div class="step"><div class="n">4</div><div class="t">Στο μενού αριστερά εμφανίζεται πλέον το <b>Courier Center</b>, με τις σελίδες: Ρυθμίσεις, Manifest Παραλαβής, Επιστροφές, Αναφορά Προβλήματος και Οδηγίες (αυτή εδώ).</div></div>
<div class="shot narrow"><img alt="Το μενού Courier Center" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-menu.webp' ); ?>" style="max-width:180px"/><div class="cap">Το μενού Courier Center</div></div>
<div class="warn"><b>Προσοχή:</b> το plugin χρειάζεται το <b>WooCommerce</b> εγκατεστημένο και ενεργό. Αν δεν βλέπετε το μενού «Courier Center», ελέγξτε πρώτα ότι το WooCommerce είναι ενεργό.</div>
<div class="tip"><b>Νέες εκδόσεις:</b> όταν βγει νέα έκδοση, στη σελίδα Plugins εμφανίζεται «Update now». Πατήστε το· οι ρυθμίσεις και τα vouchers σας <b>δεν χάνονται</b>.</div>
</div>
<div class="card" id="cc-a2">
<h2>Α2. Σύνδεση με τον λογαριασμό σας</h2>
<p class="what">Δίνετε στο plugin τα στοιχεία σύνδεσης της Courier Center, ώστε να μπορεί να βγάζει vouchers στο όνομά σας.</p>
<div class="step"><div class="n">1</div><div class="t">Πηγαίνετε <b>Courier Center → Ρυθμίσεις</b>. Στην πρώτη ενότητα, «API Credentials» <span class="mk">1</span>, γράψτε τα 4 στοιχεία ακριβώς όπως σας τα έστειλε η Courier Center:
    <table style="margin-top:8px">
<tr><th style="width:220px">Πεδίο</th><th>Τι είναι</th></tr>
<tr><td>User Alias</td><td>Το όνομα χρήστη σας για τη σύνδεση</td></tr>
<tr><td>Credential Value</td><td>Ο κωδικός πρόσβασης</td></tr>
<tr><td>API Key</td><td>Ένα μακρύ κλειδί ασφαλείας</td></tr>
<tr><td>Κεντρικός κωδικός (Carrier Billing Account)</td><td>Ο κωδικός πελάτη σας, της μορφής <code>100-XXXX-XXXX</code></td></tr>
</table>
</div></div>
<div class="step"><div class="n">2</div><div class="t">Πατήστε <b>Test &amp; Auto-fill στοιχεία</b> <span class="mk">2</span>. Το plugin ελέγχει ότι τα στοιχεία είναι σωστά, συμπληρώνει μόνο του τα στοιχεία αποστολέα (επωνυμία, διεύθυνση, σταθμός) και αποθηκεύει.</div></div>
<div class="shot"><img alt="Courier Center → Ρυθμίσεις → API Credentials" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-api.webp' ); ?>"/><div class="cap">Courier Center → Ρυθμίσεις → API Credentials</div></div>
<div class="ex"><b>Παράδειγμα:</b> Η Courier Center σας έστειλε email με User Alias <code>to_katastima_mou</code> και κεντρικό κωδικό <code>100-1234-5678</code>. Τα αντιγράφετε στα πεδία, μαζί με τον κωδικό και το API Key, και πατάτε «Test &amp; Auto-fill». Σε λίγα δευτερόλεπτα εμφανίζεται πράσινο μήνυμα επιτυχίας και πιο κάτω βλέπετε τη διεύθυνση της αποθήκης σας.</div>
<div class="warn"><b>Προσοχή:</b> ο κωδικός και το API Key, αφού αποθηκευτούν, εμφανίζονται ως τελείες (••••). Αν δεν θέλετε να τα αλλάξετε, <b>αφήστε τα πεδία όπως είναι</b>· άδειο πεδίο σημαίνει «κράτα τον παλιό». Το κουμπί «Εκκαθάριση» σβήνει <b>όλες</b> τις ρυθμίσεις — μην το πατήσετε κατά λάθος.</div>
</div>
<div class="card" id="cc-a3">
<h2>Α3. Οι ρυθμίσεις μία-μία</h2>
<p class="what">Όλες βρίσκονται στο <b>Courier Center → Ρυθμίσεις</b>. Για κάθε ρύθμιση: τι κάνει και τι να βάλετε. Μετά από οποιαδήποτε αλλαγή, πατήστε <b>Αποθήκευση Ρυθμίσεων</b> στο κάτω μέρος.</p>
<h3>📍 Σημεία αποστολής (υποκωδικοί) — μόνο αν στέλνετε από πολλά σημεία</h3>
<p>Αν τα δέματα φεύγουν από περισσότερα από ένα σημεία (π.χ. κατάστημα και αποθήκη), η Courier Center σας δίνει έναν <b>υποκωδικό</b> για κάθε σημείο. Αν στέλνετε από ένα μόνο σημείο, <b>προσπεράστε αυτή την ενότητα</b>.</p>
<div class="step"><div class="n">1</div><div class="t">Πατήστε <b>+ Προσθήκη υποκωδικού</b> <span class="mk">1</span> και γράψτε τον υποκωδικό και ένα όνομα που να καταλαβαίνετε (π.χ. «Αποθήκη Πειραιά»).</div></div>
<div class="step"><div class="n">2</div><div class="t">Στη <b>Χρέωση</b> <span class="mk">2</span> διαλέξτε αν οι αποστολές αυτού του σημείου χρεώνονται στον ίδιο τον υποκωδικό ή στον κεντρικό σας κωδικό (ρωτήστε τη Courier Center αν δεν ξέρετε).</div></div>
<div class="step"><div class="n">3</div><div class="t">Πατήστε <b>Test &amp; Auto-fill</b> <span class="mk">3</span> στη γραμμή: συμπληρώνονται η διεύθυνση και ο σταθμός του σημείου.</div></div>
<div class="step"><div class="n">4</div><div class="t">Στο <b>Προεπιλεγμένο σημείο</b> <span class="mk">4</span> διαλέξτε από πού φεύγουν συνήθως τα δέματα, και αποθηκεύστε.</div></div>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-points.webp' ); ?>"/></div>
<div class="tip">Το «Test &amp; Auto-fill» ενός υποκωδικού δημιουργεί μία δοκιμαστική αποστολή και την ακυρώνει αμέσως. Δεν χρεώνεστε.</div>
<h3>📍 Στοιχεία Παραλήπτη — Πεδίο αριθμού διεύθυνσης</h3>
<table>
<tr><th style="width:170px">Τι κάνει</th><td>Αν το checkout σας ζητά τον <b>αριθμό</b> της διεύθυνσης σε ξεχωριστό κουτάκι (π.χ. «Οδός» και «Αριθμός» χωριστά), το plugin πρέπει να ξέρει ποιο είναι αυτό το κουτάκι, για να γράψει σωστά τη διεύθυνση στο voucher.</td></tr>
<tr><th>Τι να βάλω</th><td>Αν ο πελάτης γράφει οδό και αριθμό στο ίδιο κουτάκι: αφήστε <b>«Κανένα»</b>. Αλλιώς ανοίξτε τη λίστα <span class="mk">1</span>: δείχνει τα πεδία που βρέθηκαν στις παραγγελίες σας με ένα δείγμα τιμής — διαλέξτε αυτό που έχει αριθμούς σπιτιών.</td></tr>
</table>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-recipient.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> στη λίστα βλέπετε <code>_billing_street_number</code> με δείγμα «15». Το διαλέγετε. Από εκεί και πέρα, παραγγελία με «Αγίου Δημητρίου» + «15» βγαίνει στο voucher ως «Αγίου Δημητρίου 15».</div>
<h3>✉️ Tracking &amp; Emails</h3>
<table>
<tr><th style="width:170px">Tracking URL <span class="mk">1</span></th><td><b>Τι κάνει:</b> ο σύνδεσμος που πατά ο πελάτης για να δει πού είναι το δέμα.<br/><b>Τι να βάλω:</b> αφήστε την προεπιλογή <code>https://www.courier.gr/track/result?tracknr={{tracking}}</code>. Το <code>{{tracking}}</code> αντικαθίσταται μόνο του με τον αριθμό αποστολής.</td></tr>
<tr><th>Ενημέρωση πελάτη <span class="mk">2</span></th><td><b>Τι κάνει:</b> προσθέτει τον αριθμό αποστολής και κουμπί παρακολούθησης στα emails που στέλνει το WooCommerce στον πελάτη (<a href="#cc-c2">δείτε πώς φαίνεται</a>).<br/><b>Τι να βάλω:</b> τσεκαρισμένο (προτείνεται).</td></tr>
</table>
<h3>🖨️ Ρυθμίσεις Εκτύπωσης</h3>
<p>Διαλέξτε μορφή ανάλογα με τον εκτυπωτή και τις ετικέτες σας — μία για τα κανονικά vouchers <span class="mk">3</span> και μία για τα BOX NOW <span class="mk">4</span>.</p>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-tracking-print.webp' ); ?>"/></div>
<table>
<tr><th style="width:260px">Έχω…</th><th>Διαλέγω</th></tr>
<tr><td>Κανονικό εκτυπωτή, απλές σελίδες A4 ή λευκά αυτοκόλλητα 3 θέσεων</td><td>A4 - 3 θέσεις μη προεκτυπωμένο (προεπιλογή)</td></tr>
<tr><td>Προεκτυπωμένα αυτοκόλλητα της Courier Center, 3 θέσεων</td><td>A4 - 3 θέσεις προεκτυπωμένο</td></tr>
<tr><td>Αυτοκόλλητα A4 με 4 θέσεις (2×2)</td><td>A4 - 4 θέσεις 100x150mm</td></tr>
<tr><td>Θερμικό εκτυπωτή ετικετών</td><td>Το μέγεθος της ετικέτας σας: 205×100, 100×150 ή 100×170 mm</td></tr>
</table>
<h3>📦 BOX NOW Widget (Checkout)</h3>

<p><b>Πώς λειτουργεί:</b> το BOX NOW <b>δεν</b> είναι ξεχωριστή μέθοδος αποστολής του plugin. Είναι μια <b>επιπλέον επιλογή παράδοσης</b> που εμφανίζεται στο checkout κάτω από τις μεθόδους αποστολής που θα ορίσετε εδώ. Όταν ο πελάτης διαλέξει μία από αυτές τις μεθόδους, βλέπει την επιλογή «Παράδοση σε BOX NOW Locker» και αποφασίζει αν θα παραλάβει το δέμα στη διεύθυνσή του ή από locker, ανάλογα με τις ρυθμίσεις σας.</p>
<table>
<tr><th style="width:170px">Ενεργοποίηση BOX NOW <span class="mk">1</span></th><td><b>Τι κάνει:</b> ενεργοποιεί τη δυνατότητα παραλαβής από BOX NOW Locker στο checkout (<a href="#cc-b3">δείτε τι βλέπει ο πελάτης</a>).<br/><b>Τι να βάλω:</b> τσεκάρετε αν θέλετε να προσφέρετε BOX NOW.</td></tr>
<tr><th>Μέθοδος αποστολής "Courier Center" <span class="mk">2</span></th><td><b>Τι κάνει:</b> επιλέγετε σε ποιες μεθόδους αποστολής θα εμφανίζεται η δυνατότητα παραλαβής από BOX NOW Locker. Σε όσες δεν τσεκάρετε, το BOX NOW δεν εμφανίζεται καθόλου.<br/><b>Τι να βάλω:</b> μόνο μεθόδους της Courier Center (όχι άλλων μεταφορέων). Αν έχετε ξεχωριστή μέθοδο για lockers, <b>μόνο αυτήν</b> (<a href="#cc-a4">δείτε Α4</a>).</td></tr>
<tr><th>Προεπιλεγμένη επιλογή BOX NOW <span class="mk">3</span></th><td><b>Τι κάνει:</b> σε όλες τις μεθόδους του <span class="mk">2</span>, η παραλαβή από BOX NOW Locker εμφανίζεται ήδη επιλεγμένη και ο πελάτης <b>δεν μπορεί να την αποεπιλέξει</b> όσο μένει σε αυτή τη μέθοδο. Για παράδοση στη διεύθυνσή του πρέπει να διαλέξει άλλη μέθοδο αποστολής.<br/><b>Τι να βάλω:</b> τσεκάρετε αν έχετε ξεχωριστή μέθοδο μόνο για lockers (<a href="#cc-a4">Α4</a>), ή αν στέλνετε <b>όλα</b> τα δέματα σε lockers. Αν θέλετε ο πελάτης να επιλέγει ο ίδιος μέσα στην ίδια μέθοδο, αφήστε το ξετσεκάριστο.</td></tr>
</table>

<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-boxnow.webp' ); ?>"/></div>
<h3 id="cc-a3-methods">🤖 Αυτόματη Δημιουργία Voucher &amp; 🚚 Μέθοδοι αποστολής</h3>
<table>
<tr><th style="width:170px">Αυτόματη δημιουργία <span class="mk">1</span> <span class="mk">2</span></th><td><b>Τι κάνει:</b> βγάζει voucher (Επόμενη Μέρα) μόνο του, μόλις μια παραγγελία περάσει στην κατάσταση που διαλέξατε.<br/><b>Τι να βάλω:</b> αν το θέλετε, τσεκάρετε και διαλέγετε κατάσταση, π.χ. «Processing». Αν προτιμάτε να ελέγχετε κάθε παραγγελία πριν φύγει, αφήστε το ξετσεκάριστο.</td></tr>
<tr><th>Διαχειρίσιμες μέθοδοι <span class="mk">3</span></th><td><b>Τι κάνει:</b> λέει στο plugin ποιες παραγγελίες είναι για Courier Center. Μόνο γι' αυτές βγαίνουν vouchers αυτόματα ή μαζικά.<br/><b>Τι να βάλω:</b> <b>υποχρεωτικά</b> τσεκάρετε τουλάχιστον μία — τη μέθοδο αποστολής Courier Center του καταστήματός σας. Μην τσεκάρετε μεθόδους άλλων μεταφορέων.</td></tr>
</table>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-auto-methods.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> το κατάστημά σας έχει δύο μεθόδους αποστολής, «Courier center» και «ACS Courier». Τσεκάρετε μόνο το «Courier center». Έτσι, όταν μαζικά βγάζετε vouchers, οι παραγγελίες ACS παραλείπονται και δεν φεύγει κατά λάθος δέμα με λάθος μεταφορέα.</div>
<div class="tip">Στο κάτω μέρος της σελίδας φαίνεται πότε έτρεξε τελευταία φορά η αυτόματη ενημέρωση κατάστασης των αποστολών (κάθε 2 ώρες).</div>
<h3 id="cc-a3-pickup">🚚 Παραλαβή από τον courier</h3>

<table>
<tr><th style="width:170px">Σε παραλαβή → κατάσταση <span class="mk">1</span></th><td><b>Τι κάνει:</b> μόλις ο courier παραλάβει το δέμα από εσάς, η παραγγελία αλλάζει αυτόματα στην κατάσταση που διαλέγετε (π.χ. «Εστάλη»). Χρήσιμο ιδίως για BOX NOW, όπου η παράδοση καταγράφεται μόνο όταν ο πελάτης ανοίξει τη θυρίδα, και ως τότε η παραγγελία θα έμενε «Σε επεξεργασία».<br/><b>Τι να βάλω:</b> αν το θέλετε, διαλέξτε κατάσταση. Αν το αφήσετε κενό, δεν αλλάζει τίποτα.</td></tr>
<tr><th>Email στον πελάτη <span class="mk">2</span></th><td><b>Τι κάνει:</b> ο πελάτης παίρνει email «Η παραγγελία στάλθηκε» με τον αριθμό αποστολής και το link παρακολούθησης. Στέλνεται μία φορά ανά αποστολή.<br/><b>Τι να βάλω:</b> τσεκαρισμένο (προτείνεται).</td></tr>
</table>
<div class="shot"><img alt="Courier Center → Ρυθμίσεις → Παραλαβή από τον courier" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-pickup.webp' ); ?>"/><div class="cap">Courier Center → Ρυθμίσεις → Παραλαβή από τον courier</div></div>
<div class="tip">Η αυτόματη ολοκλήρωση της παραγγελίας όταν παραδοθεί το δέμα γίνεται κανονικά, όπως πριν.</div>

</div>
<div class="card" id="cc-a4">
<h2>Α4. Παράδειγμα: πόρτα-πόρτα και BOX NOW χωριστά στο checkout</h2>

<p class="what">Ο πιο συνηθισμένος τρόπος: ο πελάτης βλέπει στο checkout <b>δύο ξεχωριστές επιλογές αποστολής</b> και διαλέγει μία.</p>
<table>
<tr><th style="width:260px">Μέθοδος στο checkout</th><th>Τι σημαίνει</th></tr>
<tr><td>Courier Center – Παράδοση στην πόρτα</td><td>Ο courier φέρνει το δέμα στη διεύθυνση του πελάτη.</td></tr>
<tr><td>BOX NOW – Παραλαβή από locker</td><td>Ο πελάτης παραλαμβάνει από BOX NOW Locker.</td></tr>
</table>
<div class="step"><div class="n">1</div><div class="t"><b>Φτιάξτε δύο μεθόδους αποστολής στο WooCommerce.</b> Πηγαίνετε <b>WooCommerce → Settings → Shipping</b>, ανοίξτε τη ζώνη σας (π.χ. «Ελλάδα») και πατήστε <b>Add shipping method</b> δύο φορές (π.χ. «Flat rate»). Δώστε στην πρώτη τίτλο «Courier Center – Παράδοση στην πόρτα» <span class="mk">1</span> και στη δεύτερη «BOX NOW – Παραλαβή από locker» <span class="mk">2</span>, με το κόστος που θέλετε για την καθεμία.</div></div>
<div class="shot"><img alt="WooCommerce → Settings → Shipping → η ζώνη σας" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-zone-two-methods.webp' ); ?>"/><div class="cap">WooCommerce → Settings → Shipping → η ζώνη σας</div></div>
<div class="step"><div class="n">2</div><div class="t">Στο <b>Courier Center → Ρυθμίσεις → BOX NOW Widget</b> (<a href="#cc-a3">Α3</a>): τσεκάρετε <b>Ενεργοποίηση BOX NOW</b> <span class="mk">1</span>.</div></div>
<div class="step"><div class="n">3</div><div class="t">Στο <b>Μέθοδος αποστολής "Courier Center"</b> <span class="mk">2</span> του BOX NOW τσεκάρετε <b>μόνο</b> το «BOX NOW – Παραλαβή από locker». <b>Όχι</b> το «Παράδοση στην πόρτα».</div></div>
<div class="step"><div class="n">4</div><div class="t">Τσεκάρετε <b>Προεπιλεγμένη επιλογή BOX NOW</b> <span class="mk">3</span>. Έτσι, όποιος διαλέγει «BOX NOW – Παραλαβή από locker» παραλαμβάνει υποχρεωτικά από locker. Η προεπιλογή ισχύει μόνο για τις μεθόδους του βήματος 3, άρα η «Παράδοση στην πόρτα» δεν επηρεάζεται.</div></div>
<div class="shot"><img alt="Courier Center → Ρυθμίσεις → BOX NOW Widget, στημένο για το παράδειγμα" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-set-boxnow-example.webp' ); ?>"/><div class="cap">Courier Center → Ρυθμίσεις → BOX NOW Widget, στημένο για το παράδειγμα</div></div>
<div class="step"><div class="n">5</div><div class="t">Στο <b>Διαχειρίσιμες μέθοδοι</b> (<a href="#cc-a3-methods">Α3</a>) τσεκάρετε <b>και τις δύο</b> μεθόδους. Αλλιώς δεν βγαίνει voucher για όποια λείπει.</div></div>
<div class="step"><div class="n">6</div><div class="t"><b>Αντικαταβολή:</b> αυτή τη στιγμή δεν γίνεται αποστολή BOX NOW με αντικαταβολή (<a href="#cc-b3">Β3</a>). Για να μη φαίνεται καθόλου η αντικαταβολή σε όποιον διαλέγει locker, πηγαίνετε <b>WooCommerce → Settings → Payments → Αντικαταβολή (Cash on delivery)</b>, και στο πεδίο <b>«Enable for shipping methods»</b> <span class="mk">1</span> βάλτε μόνο το «Courier Center – Παράδοση στην πόρτα» → <b>Save changes</b> <span class="mk">2</span>.</div></div>
<div class="shot"><img alt="WooCommerce → Settings → Payments → Cash on delivery (στα ελληνικά: «Αντικαταβολή»)" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-cod-methods.webp' ); ?>"/><div class="cap">WooCommerce → Settings → Payments → Cash on delivery (στα ελληνικά: «Αντικαταβολή»)</div></div>
<div class="ex"><b>Αποτέλεσμα:</b> ο πελάτης που διαλέγει «Παράδοση στην πόρτα» πληρώνει όπως θέλει (και με αντικαταβολή) και το δέμα πάει σπίτι του. Ο πελάτης που διαλέγει «BOX NOW – Παραλαβή από locker» βλέπει αμέσως την επιλογή locker, δεν μπορεί να τη βγάλει και δεν βλέπει αντικαταβολή.</div>
<div class="tip"><b>Μία μόνο μέθοδος;</b> Αν έχετε μόνο μία μέθοδο Courier Center, βάλτε την στις μεθόδους του BOX NOW και αφήστε την «Προεπιλεγμένη επιλογή» ξετσεκάριστη: ο πελάτης τσεκάρει μόνος του αν θέλει locker. Σε αυτή την περίπτωση η αντικαταβολή <b>δεν</b> μπορεί να κρυφτεί αυτόματα, γιατί για το WooCommerce είναι η ίδια μέθοδος. Αν ο πελάτης διαλέξει BOX NOW με αντικαταβολή, θα το δείτε όταν πάτε να βγάλετε voucher (<a href="#cc-b3">Β3</a>).</div>

</div>
<!-- ═══════════ Β. ΚΑΘΗΜΕΡΙΝΗ ΔΟΥΛΕΙΑ ═══════════ -->
<div class="part">Β. Καθημερινή δουλειά</div>
<div class="card" id="cc-b1">
<h2>Β1. Βγάζω voucher για μια παραγγελία</h2>
<p class="what">Το voucher είναι η ετικέτα αποστολής. Με ένα κουμπί, τα στοιχεία του πελάτη στέλνονται στην Courier Center και παίρνετε αριθμό αποστολής (AWB).</p>
<div class="step"><div class="n">1</div><div class="t">Πηγαίνετε <b>WooCommerce → Orders</b> και πατήστε τον αριθμό της παραγγελίας <span class="mk">1</span>. Στη στήλη «Μέθοδος Αποστολής» το πράσινο σήμα <span class="mk">2</span> σημαίνει ότι η παραγγελία είναι για Courier Center. Η στήλη «CC Voucher» <span class="mk">3</span> είναι κενή όσο δεν έχει βγει voucher.</div></div>
 Η στήλη <b>«Τύπος»</b> δείχνει από την αρχή αν ο πελάτης διάλεξε <b>📦 BOX NOW</b> <span class="mk">1</span> ή <b>🚚 Κανονική</b> <span class="mk">2</span> παράδοση, χωρίς να ανοίξετε την παραγγελία. Όσο δεν έχει βγει voucher, η ένδειξη έχει διακεκομμένο πλαίσιο.
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-orders-list.webp' ); ?>"/></div>
<div class="shot"><img alt="Η στήλη «Τύπος» πριν βγει voucher (διακεκομμένο πλαίσιο)" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-orders-type.webp' ); ?>"/><div class="cap">Η στήλη «Τύπος» πριν βγει voucher (διακεκομμένο πλαίσιο)</div></div>
<div class="step"><div class="n">2</div><div class="t">Μέσα στην παραγγελία, στην ενότητα <b>Shipping</b>, ελέγξτε ότι τα στοιχεία του παραλήπτη (όνομα, διεύθυνση, πόλη και Τ.Κ.) είναι σωστά. Ελέγξτε επίσης ότι υπάρχει <b>τηλέφωνο</b> του παραλήπτη στα στοιχεία της παραγγελίας.<br/>
    Μετά, στο δεξί μέρος της οθόνης, βρείτε το πλαίσιο <b>Courier Center</b>. Από εκεί ορίζετε τις επιλογές αποστολής και βγάζετε το voucher. Οι επιλογές του πλαισίου διαφέρουν ανάλογα με τον τύπο παράδοσης:
    <ul style="margin:6px 0 6px 18px">
<li><b>Κανονική παράδοση:</b> εμφανίζεται η φόρμα δημιουργίας voucher (παρακάτω), όπου διαλέγετε Υπηρεσία, Πολλαπλή Αποστολή, Επιστροφικό και Σημείο Αποστολής, όπου υπάρχει. Στην κορυφή της, η γραμμή «📮 Διεύθυνση voucher» δείχνει τη διεύθυνση ακριβώς όπως θα γραφτεί στο voucher.</li>
<li><b>Παράδοση σε BOX NOW Locker</b> (ο πελάτης διάλεξε locker): εμφανίζονται τα στοιχεία του locker και το κουμπί «Δημιουργία BOX NOW Voucher» (<a href="#cc-b3">Β3</a>), <b>όχι</b> η φόρμα.</li>
</ul>
    Αν θέλετε να στείλετε μια παραγγελία BOX NOW κανονικά στην πόρτα, πατήστε <b>«Αφαίρεση επιλογής BOX NOW»</b> και θα εμφανιστεί η κανονική φόρμα.
    Στην κανονική φόρμα διαλέγετε:
    <ul style="margin:6px 0 0 18px">
<li><span class="mk">1</span> <b>Υπηρεσία</b>: Επόμενη Μέρα (συνήθως), ή Αυθημερόν 3 ή 5 ωρών αν το έχετε συμφωνήσει.</li>
<li><b>Πολλαπλή Αποστολή</b>: τσεκάρετε αν η παραγγελία φεύγει σε περισσότερα από ένα κουτιά και γράψτε πόσα.</li>
<li><span class="mk">2</span> <b>Επιστροφικό</b>: «Χωρίς» στις περισσότερες παραγγελίες· «Προαιρετικό» αν ο παραλήπτης ίσως στείλει κάτι πίσω· «Υποχρεωτικό» αν ο courier πρέπει να πάρει κάτι πίσω κατά την παράδοση.</li>
<li><span class="mk">3</span> <b>BOX NOW Locker</b>: μόνο αν το δέμα πάει σε locker (<a href="#cc-b4">δείτε Β4</a>).</li>
<li><span class="mk">4</span> <b>Σημείο αποστολής</b>: εμφανίζεται μόνο αν έχετε υποκωδικούς.</li>
</ul>
</div></div>
<div class="shot narrow"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-metabox-form.webp' ); ?>"/></div>
<div class="step"><div class="n">3</div><div class="t">Πατήστε <b>Δημιουργία Voucher</b> <span class="mk">5</span>. Σε λίγα δευτερόλεπτα το πλαίσιο γίνεται πράσινο, «Voucher Δημιουργήθηκε», με τον αριθμό αποστολής (AWB) <span class="mk">1</span>.</div></div>
<div class="shot narrow"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-voucher-created.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> Η κ. Μαρία Παπαδοπούλου παρήγγειλε μια κούπα με αντικαταβολή. Ανοίγετε την παραγγελία #3600, αφήνετε «Επόμενη Μέρα» και «Χωρίς επιστροφικό», και πατάτε «Δημιουργία Voucher». Το ποσό της αντικαταβολής περνάει μόνο του στο voucher — δεν το γράφετε πουθενά.</div>
<div class="warn"><b>Προσοχή:</b>
<ul style="margin:4px 0 0 18px">
<li>Η παραγγελία πρέπει να έχει όνομα, διεύθυνση, πόλη, Τ.Κ. 5 ψηφίων και τηλέφωνο. Για BOX NOW χρειάζεται <b>κινητό</b>.</li>
<li>Όριο ανά κουτί: 30 kg, 180 cm η μεγαλύτερη πλευρά.</li>
<li>Αν η παραγγελία έχει μέθοδο αποστολής που <b>δεν</b> είναι Courier Center, θα σας ρωτήσει «Είστε σίγουροι…;» πριν προχωρήσει.</li>
<li>Δεύτερο voucher για την ίδια παραγγελία δεν βγαίνει όσο το πρώτο είναι ενεργό. Αν χρειάζεστε νέο, <a href="#cc-b5">ακυρώστε πρώτα</a> το παλιό.</li>
</ul>
</div>
</div>
<div class="card" id="cc-b2">
<h2>Β2. Τυπώνω την ετικέτα</h2>
<p class="what">Τυπώνετε το voucher σε PDF και το κολλάτε στο δέμα.</p>
<div class="step"><div class="n">1</div><div class="t">Στο πράσινο πλαίσιο της παραγγελίας (εικόνα του Β1) πατήστε <b>Εκτύπωση Ετικέτας</b> <span class="mk">2</span>. Ανοίγει PDF σε νέα καρτέλα.</div></div>
<div class="step"><div class="n">2</div><div class="t">Τυπώστε το με αυτές τις ρυθμίσεις εκτυπωτή: <b>A4 · Κλίμακα 100% (Actual size) · Περιθώρια None/Minimum</b>.</div></div>
<div class="step"><div class="n">3</div><div class="t">Κολλήστε την ετικέτα σε επίπεδο σημείο του κουτιού, ώστε το barcode να μη διπλώνει.</div></div>
<div class="warn"><b>Προσοχή:</b> μην επιλέγετε «Fit to page» / «Προσαρμογή στη σελίδα» — το barcode μικραίνει και μπορεί να μη σκανάρεται. Αν η αποστολή έχει επιστροφικό, το επιστροφικό voucher τυπώνεται αυτόματα μαζί.</div>
<div class="tip">Για πολλές παραγγελίες μαζί, δείτε <a href="#cc-b6">Β6</a>.</div>
</div>
<div class="card" id="cc-b3">
<h2>Β3. Παραγγελίες BOX NOW</h2>
<p class="what">Ο πελάτης μπορεί να ζητήσει να παραλάβει το δέμα από ένα αυτόματο locker BOX NOW αντί για παράδοση στην πόρτα.</p>
<h3>Τι βλέπει ο πελάτης στο checkout</h3>
<p>Όταν διαλέξει τη μέθοδο αποστολής Courier Center, εμφανίζεται το «Παράδοση σε BOX NOW Locker» <span class="mk">1</span>. Αν το τσεκάρει, διαλέγει <b>Αυτόματη εύρεση</b> <span class="mk">2</span> (το πλησιέστερο locker στη διεύθυνσή του) ή <b>Επιλογή από χάρτη</b> <span class="mk">3</span> (διαλέγει ο ίδιος).</p>
<div class="shot"><img alt="Checkout του καταστήματος (η εμφάνιση εξαρτάται από το θέμα σας)" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-checkout-boxnow.webp' ); ?>"/><div class="cap">Checkout του καταστήματος (η εμφάνιση εξαρτάται από το θέμα σας)</div></div>
<h3>Τι βλέπετε εσείς στην παραγγελία</h3>
<p>Το πλαίσιο Courier Center δείχνει το locker που διάλεξε ο πελάτης <span class="mk">1</span> και αντί για τη συνηθισμένη φόρμα έχει ένα κουμπί: <b>Δημιουργία BOX NOW Voucher</b> <span class="mk">3</span>. Από εδώ μπορείτε επίσης να <b>αλλάξετε locker</b> <span class="mk">2</span> (<a href="#cc-b4">Β4</a>) ή να στείλετε την παραγγελία κανονικά στην πόρτα με <b>Αφαίρεση επιλογής BOX NOW</b> <span class="mk">4</span>.</p>
<div class="shot narrow"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-boxnow-order.webp' ); ?>"/></div>
<div class="warn"><b>Προσοχή:</b> αυτή τη στιγμή <b>δεν</b> γίνεται αποστολή BOX NOW με αντικαταβολή. Αν ο πελάτης διάλεξε BOX NOW και πληρωμή με αντικαταβολή, το voucher δεν βγαίνει (στην αυτόματη δημιουργία μπαίνει σημείωση στην παραγγελία)· είτε αλλάξτε τον τρόπο πληρωμής είτε πατήστε «Αφαίρεση επιλογής BOX NOW». Για να μη συμβαίνει, κρύψτε την αντικαταβολή από τη μέθοδο των lockers (<a href="#cc-a4">Α4, βήμα 6</a>). Επίσης χρειάζεται κινητό (69…), γιατί εκεί ο πελάτης παίρνει τον κωδικό για να ανοίξει το locker.</div>
</div>
<div class="card" id="cc-b4">
<h2>Β4. Αλλάζω ή διαλέγω BOX NOW locker</h2>
<p class="what">Αλλάζετε το locker όπου θα πάει το δέμα — ή στέλνετε σε locker μια παραγγελία που ήταν για την πόρτα — χωρίς να ξαναπεράσετε την παραγγελία.</p>
<h3>Αν η παραγγελία έχει ήδη BOX NOW</h3>
<div class="step"><div class="n">1</div><div class="t">Στο πλαίσιο Courier Center πατήστε <b>Αλλαγή locker</b> (εικόνα του Β3, <span class="mk">2</span>).</div></div>
<div class="step"><div class="n">2</div><div class="t">Ανοίγει ο χάρτης της BOX NOW. Γράψτε περιοχή ή διεύθυνση στην <b>Αναζήτηση</b> <span class="mk">1</span>, ή μετακινηθείτε στον χάρτη. Πατήστε το locker που θέλετε, στη λίστα <span class="mk">2</span> ή στον χάρτη <span class="mk">3</span>.</div></div>
<div class="shot"><img alt="Πράσινο = διαθέσιμο, πορτοκαλί = περιορισμένη διαθεσιμότητα" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-boxnow-map.webp' ); ?>"/><div class="cap">Πράσινο = διαθέσιμο, πορτοκαλί = περιορισμένη διαθεσιμότητα</div></div>
<div class="step"><div class="n">3</div><div class="t">Το νέο locker αποθηκεύεται αμέσως στην παραγγελία και γράφεται σημείωση με το παλιό και το νέο. Πατήστε <b>Δημιουργία BOX NOW Voucher</b>.</div></div>
<h3>Αν η παραγγελία ήταν για την πόρτα</h3>
<div class="step"><div class="n">1</div><div class="t">Στη φόρμα του voucher τσεκάρετε <b>BOX NOW Locker</b> <span class="mk">1</span>.  Οι δύο επιλογές εμφανίζονται <b>μόνο αφού τσεκάρετε</b> το «BOX NOW Locker». Διαλέξτε <b>Επιλογή από χάρτη</b> <span class="mk">2</span> (ή «Αυτόματη εύρεση» για το πλησιέστερο locker σε ακτίνα 1,5 km από τη διεύθυνση του πελάτη).</div></div>
<div class="shot narrow"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-boxnow-pick-radio.webp' ); ?>"/></div>
<div class="step"><div class="n">2</div><div class="t">Διαλέγετε locker στον χάρτη όπως παραπάνω. Το επιλεγμένο locker εμφανίζεται σε πράσινο πλαίσιο <span class="mk">1</span>· με το <b>Αλλαγή</b> <span class="mk">2</span> ξανανοίγει ο χάρτης. Μετά πατήστε <b>Δημιουργία Voucher</b>.</div></div>
<div class="shot narrow"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-boxnow-picked.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> Ο κ. Γιώργος Νικολάου διάλεξε locker στο Χαλάνδρι, αλλά σας παίρνει τηλέφωνο: «Στείλτε το καλύτερα στη Λεωφόρο Πεντέλης, στα Βριλήσσια». Ανοίγετε την παραγγελία #3601 → «Αλλαγή locker» → γράφετε «Βριλήσσια» → πατάτε το locker της Λεωφόρου Πεντέλης 110. Στις σημειώσεις της παραγγελίας γράφεται η αλλαγή.</div>
<div class="warn"><b>Προσοχή:</b> αν έχει ήδη βγει voucher, το κουμπί «Αλλαγή locker» δεν εμφανίζεται. <a href="#cc-b5">Ακυρώστε πρώτα την αποστολή</a> και μετά αλλάξτε locker. Ο πελάτης <b>δεν</b> λαμβάνει email για την αλλαγή — ενημερώστε τον εσείς αν χρειάζεται.</div>
</div>
<div class="card" id="cc-b5">
<h2>Β5. Ακυρώνω αποστολή</h2>
<p class="what">Ακυρώνετε ένα voucher που βγήκε λάθος ή για παραγγελία που δεν θα φύγει.</p>
<div class="step"><div class="n">1</div><div class="t">Στο πράσινο πλαίσιο της παραγγελίας (εικόνα του Β1) πατήστε <b>Ακύρωση Αποστολής</b> <span class="mk">4</span> και επιβεβαιώστε.</div></div>
<div class="step"><div class="n">2</div><div class="t">Ο παλιός αριθμός μένει διαγραμμένος (π.χ. <s style="color:#c00">013800012345</s>) για ιστορικό, και η φόρμα εμφανίζεται ξανά για να βγάλετε νέο voucher αν θέλετε.</div></div>
<div class="ex"><b>Παράδειγμα:</b> βγάλατε voucher και μετά ο πελάτης σας λέει ότι άλλαξε διεύθυνση. Ακυρώνετε την αποστολή, διορθώνετε τη διεύθυνση στην παραγγελία (μολυβάκι ✏️ δίπλα στο «Shipping» → «Update») και βγάζετε νέο voucher.</div>
<div class="warn"><b>Προσοχή:</b> η ακύρωση γίνεται μόνο <b>πριν</b> ο οδηγός παραλάβει το δέμα. Αν το δέμα έχει ήδη σκαναριστεί, επικοινωνήστε με την Courier Center.</div>
</div>
<div class="card" id="cc-b6">
<h2>Β6. Πολλές παραγγελίες μαζί</h2>
<p class="what">Βγάζετε ή τυπώνετε vouchers για πολλές παραγγελίες με μία κίνηση.</p>
<div class="step"><div class="n">1</div><div class="t">Στο <b>WooCommerce → Orders</b> τσεκάρετε τις παραγγελίες <span class="mk">1</span>.</div></div>
<div class="step"><div class="n">2</div><div class="t">Ανοίξτε τη λίστα <b>Bulk actions</b> <span class="mk">2</span> και διαλέξτε ενέργεια:
    <table style="margin-top:6px">
<tr><th style="width:290px">Ενέργεια</th><th>Τι κάνει</th></tr>
<tr><td>CC — Δημιουργία Vouchers (Επόμενη Μέρα)</td><td>Βγάζει voucher σε όσες δεν έχουν, από το προεπιλεγμένο σημείο</td></tr>
<tr><td>CC — Vouchers από: …</td><td>Το ίδιο, από συγκεκριμένο σημείο (μόνο με υποκωδικούς)</td></tr>
<tr><td>CC — Ενημέρωση Status</td><td>Φέρνει την τελευταία κατάσταση των αποστολών</td></tr>
<tr><td>CC — Μαζική Εκτύπωση Vouchers</td><td>Ένα PDF με όλα τα κανονικά vouchers</td></tr>
<tr><td>CC — Μαζική Εκτύπωση BOX NOW</td><td>Ένα PDF μόνο με τα BOX NOW vouchers</td></tr>
</table>
</div></div>
<div class="step"><div class="n">3</div><div class="t">Πατήστε <b>Apply</b> <span class="mk">3</span>. Πάνω στη σελίδα εμφανίζεται σύνοψη: πόσα δημιουργήθηκαν και πόσα παραλείφθηκαν και γιατί.</div></div>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-bulk.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> κάθε πρωί τσεκάρετε όλες τις «Processing» παραγγελίες → «CC — Δημιουργία Vouchers» → Apply. Μετά τις ξανατσεκάρετε → «CC — Μαζική Εκτύπωση Vouchers» → Apply, και τυπώνετε όλες τις ετικέτες μαζί.</div>
<div class="tip">Παραγγελίες που έχουν ήδη voucher ή άλλο μεταφορέα <b>παραλείπονται αυτόματα</b> — δεν χρειάζεται να τις ξεχωρίσετε.</div>
</div>
<div class="card" id="cc-b7">
<h2>Β7. Λίστα για τον οδηγό (Manifest Παραλαβής)</h2>
<p class="what">Η λίστα με όλα τα δέματα της ημέρας. Την τυπώνετε, ο οδηγός την υπογράφει όταν παραλαμβάνει, και την κρατάτε ως απόδειξη.</p>
<div class="step"><div class="n">1</div><div class="t">Πηγαίνετε <b>Courier Center → Manifest Παραλαβής</b>.</div></div>
<div class="step"><div class="n">2</div><div class="t">Διαλέξτε <b>ημερομηνία</b> <span class="mk">1</span> (ή πατήστε «Σήμερα» / «Χθες»). Αν έχετε υποκωδικούς, διαλέξτε και <b>σημείο</b> <span class="mk">2</span>.</div></div>
<div class="step"><div class="n">3</div><div class="t">Πατήστε <b>Λήψη Manifest PDF</b> <span class="mk">3</span>, τυπώστε το και δώστε το στον οδηγό μαζί με τα δέματα.</div></div>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-manifest.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> έχετε κατάστημα στο κέντρο και αποθήκη στον Πειραιά. Στον οδηγό της αποθήκης δίνετε το Manifest με σημείο «Αποθήκη Πειραιά» — μόνο τα δέματα που παραλαμβάνει εκεί.</div>
<div class="warn"><b>Προσοχή:</b> αν μια αποστολή έχει δημιουργηθεί αλλά δεν εμφανίζεται στο Manifest, επικοινωνήστε με την Courier Center.</div>
</div>
<!-- ═══════════ Γ. ΜΕΤΑ ΤΗΝ ΑΠΟΣΤΟΛΗ ═══════════ -->
<div class="part">Γ. Μετά την αποστολή</div>
<div class="card" id="cc-c1">
<h2>Γ1. Πού είναι το δέμα;</h2>
<p class="what">Το plugin ρωτά την Courier Center για την κατάσταση κάθε ενεργής αποστολής, αυτόματα κάθε 2 ώρες.</p>
<p>Την κατάσταση τη βλέπετε στη στήλη <b>CC Status</b> της λίστας παραγγελιών:</p>
<table>
<tr><th style="width:250px">Ένδειξη</th><th>Τι σημαίνει</th><th>Τι κάνω</th></tr>
<tr><td><span class="badge" style="background:#2271b1">Νέα</span></td><td>Βγήκε voucher, ο οδηγός δεν το έχει παραλάβει ακόμα</td><td>Τίποτα· περιμένει παραλαβή</td></tr>
<tr><td><span class="badge" style="background:#2271b1">Στον σταθμό</span></td><td>Το δέμα είναι σε σταθμό της Courier Center</td><td>Τίποτα</td></tr>
<tr><td><span class="badge" style="background:#2271b1">Σε διανομή</span></td><td>Ο courier το έχει για παράδοση</td><td>Τίποτα</td></tr>
<tr><td><span class="badge" style="background:#46b450">Παραδόθηκε</span></td><td>Παραδόθηκε στον πελάτη</td><td>Τίποτα· η παραγγελία γίνεται «Completed» μόνη της</td></tr>
<tr><td><span class="badge" style="background:#dba617">Αποτυχία</span></td><td>Η παράδοση δεν έγινε (π.χ. απουσία)</td><td>Επικοινωνήστε με τον πελάτη</td></tr>
<tr><td><span class="badge" style="background:#d63638">Ακυρωμένη</span></td><td>Η αποστολή ακυρώθηκε</td><td>Αν χρειάζεται, βγάλτε νέο voucher</td></tr>
<tr><td><span class="badge" style="background:#d63638">Επιστροφή στον αποστολέα</span></td><td>Το δέμα γυρίζει σε εσάς</td><td>Επικοινωνήστε με τον πελάτη</td></tr>
</table>
<p>Αν θέλετε την κατάσταση αμέσως, πατήστε <b>Ενημέρωση Status</b> μέσα στην παραγγελία (μία φορά την ώρα ανά αποστολή) ή «CC — Ενημέρωση Status» από τη λίστα. Για το πλήρες ιστορικό, πατήστε τον αριθμό στη στήλη «Tracking»: ανοίγει η σελίδα παρακολούθησης στο courier.gr.</p>
</div>
<div class="card" id="cc-c2">
<h2>Γ2. Τι βλέπει ο πελάτης στο email</h2>
<p class="what">Από τη στιγμή που βγαίνει voucher, κάθε email του καταστήματος προς τον πελάτη έχει ένα πλαίσιο με τον αριθμό αποστολής <span class="mk">1</span> και κουμπί <b>Παρακολούθηση Αποστολής</b> <span class="mk">2</span>.</p>
<div class="shot"><img alt="Το κείμενο γύρω από το πλαίσιο είναι του WooCommerce και μπορεί να διαφέρει" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-email-tracking.webp' ); ?>" style="max-width:520px"/><div class="cap">Το κείμενο γύρω από το πλαίσιο είναι του WooCommerce και μπορεί να διαφέρει</div></div>
<div class="warn"><b>Προσοχή:</b> το πλαίσιο μπαίνει μόνο σε emails που φεύγουν <b>αφού</b> βγει το voucher. Αν το «Processing» email στάλθηκε πριν, ο αριθμός θα φανεί στο επόμενο (π.χ. «Completed»). Για να φτάνουν σίγουρα τα emails, προτείνουμε ένα plugin SMTP (π.χ. WP Mail SMTP).</div>
</div>
<div class="card" id="cc-c3">
<h2>Γ3. Επιστροφές</h2>
<p class="what">Ο πελάτης ζητά επιστροφή από τον λογαριασμό του στο κατάστημα· εσείς εγκρίνετε με ένα κλικ και η αποστολή επιστροφής βγαίνει αυτόματα στην Courier Center.</p>
<h3>1. Ρυθμίζω τις επιστροφές (μία φορά)</h3>
<p>Πηγαίνετε <b>Courier Center → Επιστροφές → Ρυθμίσεις</b>:</p>
<ul style="margin:4px 0 0 18px">
<li><span class="mk">1</span> <b>Ενεργοποίηση επιστροφών</b>: εμφανίζει στους πελάτες το κουμπί «Αίτημα Επιστροφής».</li>
<li><span class="mk">2</span> <b>Απαιτείται έγκριση</b>: τσεκαρισμένο = βλέπετε κάθε αίτημα πριν βγει η αποστολή (προτείνεται). Ξετσεκαρισμένο = εγκρίνεται αυτόματα.</li>
<li><span class="mk">3</span> <b>Προθεσμία</b>: πόσες μέρες μετά την παράδοση μπορεί ο πελάτης να ζητήσει επιστροφή (π.χ. 14· 0 = χωρίς όριο).</li>
<li><span class="mk">4</span> <b>Μέθοδοι επιστροφής</b>: ο πελάτης φέρνει το δέμα σε κατάστημα Courier Center (Drop Off), ο courier το παίρνει από το σπίτι του (Pickup), ή το αφήνει σε BOX NOW locker.</li>
</ul>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-returns-settings.webp' ); ?>"/></div>
<h3>2. Ο πελάτης κάνει αίτημα</h3>
<p>Στο <i>My Account → Orders</i> του καταστήματος, δίπλα στις παραγγελίες που επιτρέπουν επιστροφή, ο πελάτης βλέπει <b>Αίτημα Επιστροφής</b>. Διαλέγει προϊόντα και ποσότητες, γράφει (αν θέλει) τον λόγο, τα τηλέφωνά του και τη μέθοδο.</p>

<ul>
<li><span class="mk">1</span> <b>Προϊόντα</b>: ο πελάτης γράφει πόσα τεμάχια επιστρέφει από κάθε προϊόν (0 = κανένα).</li>
<li><span class="mk">2</span> <b>Τρόπος επιστροφής</b>: μόνο όσοι έχετε ενεργοποιήσει στις ρυθμίσεις. Με BOX NOW διαλέγει και μέγεθος θυρίδας.</li>
<li><span class="mk">3</span> <b>Υποβολή αιτήματος</b>: το αίτημα έρχεται σε εσάς για έγκριση.</li>
</ul>
<div class="shot"><img alt="Η φόρμα «Αίτημα Επιστροφής» όπως τη βλέπει ο πελάτης" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-return-form.webp' ); ?>" style="max-width:420px"/></div>
<h3>3. Εγκρίνω το αίτημα</h3>
<p>Ένας αριθμός δίπλα στο μενού <b>Courier Center</b> σας δείχνει πόσα αιτήματα περιμένουν, και λαμβάνετε email «Νέο αίτημα επιστροφής». Στο <b>Courier Center → Επιστροφές → Αιτήματα</b> πατήστε <b>Έγκριση</b> ή <b>Απόρριψη</b>. Με την έγκριση βγαίνει η αποστολή επιστροφής και ο πελάτης παίρνει email με τις οδηγίες.</p>

<ul>
<li><span class="mk">1</span> Το αίτημα που περιμένει: κατάσταση <b>«Σε αναμονή έγκρισης»</b>.</li>
<li><span class="mk">2</span> Περάστε το ποντίκι πάνω από τη γραμμή του αιτήματος· κάτω από τον αριθμό του εμφανίζονται <b>Έγκριση</b> και <b>Απόρριψη</b>.</li>
</ul>
<div class="shot"><img alt="Λίστα αιτημάτων επιστροφής" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-returns-list.webp' ); ?>"/></div>
<div class="ex"><b>Παράδειγμα:</b> Η κ. Ελένη Κωνσταντίνου θέλει να επιστρέψει τη μία από τις δύο κούπες που πήρε. Από τον λογαριασμό της πατά «Αίτημα Επιστροφής», διαλέγει 1 κούπα και «BOX NOW». Εσείς βλέπετε το αίτημα και πατάτε «Έγκριση». Εκείνη λαμβάνει email με έναν κωδικό BOX NOW και αφήνει το δέμα σε όποιο locker τη βολεύει.</div>
<h3>Επιστροφή χωρίς αίτημα (από εσάς)</h3>
<p>Αν ο πελάτης σας πάρει τηλέφωνο: ανοίξτε την παραγγελία, στο πλαίσιο <b>Επιστροφές Courier Center</b> πατήστε <b>+ Νέα επιστροφή</b>, διαλέξτε προϊόντα και μέθοδο. Η αποστολή βγαίνει αμέσως. Τσεκάρετε «Αποστολή email στον πελάτη» αν θέλετε να του πάνε οι οδηγίες.</p>
<div class="warn"><b>Προσοχή:</b>
<ul style="margin:4px 0 0 18px">
<li>Για Pickup, ο πελάτης διαλέγει εργάσιμη μέρα και, αν θέλει, διάστημα τουλάχιστον 4 ωρών μεταξύ 09:00 και 17:00. Ελέγξτε ότι στο <b>Settings → General → Timezone</b> του WordPress είναι «Athens».</li>
<li>Για BOX NOW επιστροφή χρειάζεται ελληνικό κινητό (69…).</li>
</ul>
</div>
<details><summary style="cursor:pointer;color:#2271b1">Τι σημαίνει κάθε κατάσταση αιτήματος;</summary>
<table style="margin-top:6px">
<tr><td style="width:220px"><b>Σε αναμονή έγκρισης</b></td><td>Περιμένει την απόφασή σας</td></tr>
<tr><td><b>Εγκρίθηκε</b></td><td>Η αποστολή επιστροφής ετοιμάζεται εκείνη τη στιγμή</td></tr>
<tr><td><b>Δημιουργήθηκε αποστολή</b></td><td>Έχει αριθμό αποστολής και παρακολουθείται αυτόματα</td></tr>
<tr><td><b>Σε έλεγχο</b></td><td>Η Courier Center δεν απάντησε καθαρά· το plugin ελέγχει μόνο του αν βγήκε η αποστολή, ώστε να μη βγει δεύτερη</td></tr>
<tr><td><b>Αποτυχία δημιουργίας</b></td><td>Κάτι πήγε στραβά (το μήνυμα φαίνεται στο αίτημα)· πατήστε «Επανάληψη δημιουργίας»</td></tr>
<tr><td><b>Απορρίφθηκε</b></td><td>Δεν εγκρίθηκε· ο πελάτης μπορεί να κάνει νέο αίτημα</td></tr>
</table>
</details>
<details style="margin-top:6px"><summary style="cursor:pointer;color:#2271b1">Έχω υποκωδικούς — σε ποιον λογαριασμό πάνε οι επιστροφές;</summary>
<p style="margin-top:6px">Στο ίδιο σημείο από το οποίο έφυγε η αρχική αποστολή. Αν θέλετε οι επιστροφές ενός σημείου να καταλήγουν αλλού, ορίστε το στο «Λογαριασμοί επιστροφών» των ρυθμίσεων επιστροφών.</p>
</details>
</div>
<!-- ═══════════ Δ. ΚΑΤΙ ΔΕΝ ΠΑΕΙ ΚΑΛΑ ═══════════ -->
<div class="part">Δ. Κάτι δεν πάει καλά</div>
<div class="card" id="cc-d1">
<h2>Δ1. Μηνύματα σφάλματος — τι σημαίνουν και τι κάνετε</h2>
<table>
<tr><th style="width:260px">Βλέπω…</th><th>Τι σημαίνει</th><th>Τι κάνω</th></tr>
<tr><td>Μη έγκυρος ΤΚ παραλήπτη. Πρέπει να είναι 5 ψηφία</td><td>Ο Τ.Κ. της παραγγελίας λείπει ή είναι λάθος</td><td>Διορθώστε τον Τ.Κ. στην παραγγελία (μολυβάκι ✏️ δίπλα στο «Shipping» → «Update») και ξαναπατήστε</td></tr>
<tr><td>Λείπει το τηλέφωνο / η διεύθυνση / η πόλη / το όνομα παραλήπτη</td><td>Η παραγγελία δεν έχει αυτό το στοιχείο</td><td>Συμπληρώστε το στην παραγγελία και ξαναπατήστε</td></tr>
<tr><td>Το BOX NOW δεν υποστηρίζει αντικαταβολή</td><td>Ο πελάτης διάλεξε BOX NOW και αντικαταβολή</td><td>Πατήστε «Αφαίρεση επιλογής BOX NOW» ή αλλάξτε τρόπο πληρωμής (<a href="#cc-b3">Β3</a>)</td></tr>
<tr><td>Το βάρος (… kg) υπερβαίνει το μέγιστο των 30 kg</td><td>Το βάρος των προϊόντων ξεπερνά τα 30 kg ανά κουτί</td><td>Τσεκάρετε «Πολλαπλή Αποστολή» και μοιράστε σε περισσότερα κουτιά, ή διορθώστε το βάρος του προϊόντος</td></tr>
<tr><td>Η μεγαλύτερη πλευρά υπερβαίνει τα 180 cm</td><td>Οι διαστάσεις κάποιου προϊόντος είναι πολύ μεγάλες</td><td>Ελέγξτε τις διαστάσεις του προϊόντος στο WooCommerce</td></tr>
<tr><td>Επιλέξατε BOX NOW αλλά δεν επιλέξατε locker από τον χάρτη</td><td>Τσεκάρατε «Επιλογή από χάρτη» χωρίς να πατήσετε locker</td><td>Διαλέξτε locker ή επιλέξτε «Αυτόματη εύρεση»</td></tr>
<tr><td>Σφάλμα σύνδεσης</td><td>Το site δεν μπόρεσε να μιλήσει με την Courier Center (προσωρινό)</td><td>Δοκιμάστε ξανά σε λίγα λεπτά. Αν συνεχίζει, <a href="#cc-d3">αναφέρετέ το</a></td></tr>
<tr><td>Σφάλμα από το API: …</td><td>Η Courier Center απέρριψε το voucher· ο λόγος ακολουθεί</td><td>Διαβάστε τον λόγο· αν αφορά στοιχεία σύνδεσης, ξαναπατήστε «Test &amp; Auto-fill» (<a href="#cc-a2">Α2</a>)</td></tr>
<tr><td>Σφάλμα ασφαλείας. Παρακαλώ ανανεώστε τη σελίδα</td><td>Η σελίδα ήταν ανοιχτή πολύ ώρα</td><td>Πατήστε F5 και ξαναδοκιμάστε</td></tr>
<tr><td>Δεν υπάρχουν αποστολές για αυτή την ημερομηνία</td><td>Στο Manifest: δεν βγήκε κανένα voucher εκείνη τη μέρα (ή σε εκείνο το σημείο)</td><td>Ελέγξτε ημερομηνία και σημείο</td></tr>
</table>
</div>
<div class="card" id="cc-d2">
<h2>Δ2. Συχνές ερωτήσεις</h2>
<h3>Βγήκε voucher Courier Center σε παραγγελία άλλου μεταφορέα. Γιατί;</h3>
<p>Στις Ρυθμίσεις, στο «Μέθοδοι αποστολής που διαχειρίζεται το plugin», τσεκάρετε <b>μόνο</b> τη μέθοδο Courier Center (<a href="#cc-a3-methods">Α3</a>). Ακυρώστε το λάθος voucher (<a href="#cc-b5">Β5</a>).</p>
<h3>Δεν βλέπω το μενού «Courier Center».</h3>
<p>Το WooCommerce πρέπει να είναι ενεργό. Ελέγξτε στο Plugins ότι και τα δύο είναι «Active».</p>
<h3>Ο πελάτης δεν βλέπει τον αριθμό αποστολής στο email.</h3>
<p>Το email στάλθηκε πριν βγει το voucher (<a href="#cc-c2">Γ2</a>), ή το «Ενημέρωση πελάτη» είναι ξετσεκαρισμένο στις Ρυθμίσεις.</p>
<h3>Η κατάσταση των αποστολών δεν αλλάζει.</h3>
<p>Στο κάτω μέρος των Ρυθμίσεων δείτε πότε έτρεξε τελευταία η αυτόματη ενημέρωση. Αν γράφει «Δεν είναι προγραμματισμένο», απενεργοποιήστε και ενεργοποιήστε ξανά το plugin από το Plugins.</p>
<h3>Μπορώ να στείλω BOX NOW με αντικαταβολή;</h3>
<p> Όχι, αυτή τη στιγμή δεν γίνεται. Αν έχετε ξεχωριστή μέθοδο αποστολής για lockers, μπορείτε να κρύψετε την αντικαταβολή από αυτήν (<a href="#cc-a4">Α4, βήμα 6</a>), ώστε ο πελάτης να μην μπορεί καν να τη διαλέξει.</p>
<h3>Το barcode δεν σκανάρεται.</h3>
<p>Τυπώστε σε κλίμακα 100%, χωρίς «Fit to page» (<a href="#cc-b2">Β2</a>), και ελέγξτε ότι η μορφή εκτύπωσης στις Ρυθμίσεις ταιριάζει με τις ετικέτες σας.</p>
</div>
<div class="card" id="cc-d3">
<h2>Δ3. Επικοινωνία με την Courier Center</h2>
<p class="what">Για οποιοδήποτε πρόβλημα, στείλτε αναφορά από μέσα από το plugin. Φτάνει κατευθείαν στην τεχνική ομάδα, μαζί με τα τεχνικά στοιχεία του site σας — δεν χρειάζεται να τα ψάξετε.</p>
<div class="step"><div class="n">1</div><div class="t">Πηγαίνετε <a href="<?php echo esc_url( $report_url ); ?>"><b>Courier Center → Αναφορά Προβλήματος</b></a> <span class="mk">1</span>.</div></div>
<div class="step"><div class="n">2</div><div class="t">Γράψτε σύντομο <b>τίτλο</b> <span class="mk">2</span> και στην <b>περιγραφή</b> <span class="mk">3</span>: τι πατήσατε, τι περιμένατε, τι έγινε, σε ποια παραγγελία και πότε.</div></div>
<div class="step"><div class="n">3</div><div class="t">Διαλέξτε <b>σοβαρότητα</b> <span class="mk">4</span>, ελέγξτε το <b>email</b> <span class="mk">5</span> όπου θα σας απαντήσουμε, και πατήστε <b>Αποστολή Αναφοράς</b> στο κάτω μέρος.</div></div>
<div class="shot"><img alt="" loading="lazy" src="<?php echo esc_url( CC_WC_PLUGIN_URL . 'assets/docs/wc-bug-report.webp' ); ?>"/></div>
<div class="ex"><b>Καλή περιγραφή:</b> «Πατάω “Δημιουργία Voucher” στην παραγγελία #1234 και βγαίνει “Σφάλμα σύνδεσης”. Έγινε σήμερα στις 10:15, σε 2 παραγγελίες. Χθες δούλευε κανονικά.»<br/><b>Όχι τόσο καλή:</b> «Δεν δουλεύει.»</div>
</div>
<!-- ═══════════ ΛΕΞΙΚΟ ═══════════ -->
<div class="card" id="cc-gloss">
<h2>📖 Λεξικό όρων</h2>
<dl>
<dt>Voucher</dt><dd>Η ετικέτα αποστολής που κολλάτε στο δέμα. Έχει barcode και τα στοιχεία αποστολέα και παραλήπτη.</dd>
<dt>AWB / Αριθμός αποστολής</dt><dd>Ο μοναδικός αριθμός κάθε αποστολής (π.χ. 013800012345). Με αυτόν βρίσκετε το δέμα στο courier.gr.</dd>
<dt>Κεντρικός κωδικός (Carrier Billing Account)</dt><dd>Ο κωδικός πελάτη σας στην Courier Center, μορφής 100-XXXX-XXXX. Σε αυτόν χρεώνονται οι αποστολές.</dd>
<dt>Υποκωδικός / Σημείο αποστολής</dt><dd>Επιπλέον κωδικός για κάθε σημείο από το οποίο φεύγουν δέματα (κατάστημα, αποθήκη). Μόνο αν στέλνετε από πολλά σημεία.</dd>
<dt>Έδρα</dt><dd>Το βασικό σας σημείο αποστολής — αυτό του κεντρικού κωδικού.</dd>
<dt>Manifest (Λίστα Παράδοσης)</dt><dd>Η λίστα με τα δέματα της ημέρας, που υπογράφει ο οδηγός κατά την παραλαβή.</dd>
<dt>Αντικαταβολή (COD)</dt><dd>Ο πελάτης πληρώνει τον courier κατά την παράδοση. Το ποσό περνά αυτόματα στο voucher.</dd>
<dt>BOX NOW / Locker</dt><dd>Αυτόματες θυρίδες όπου ο πελάτης παραλαμβάνει το δέμα με κωδικό που παίρνει στο κινητό.</dd>
<dt>Επιστροφικό</dt><dd>Δεύτερο voucher που τυπώνεται μαζί με το κανονικό, για να σας στείλει ο παραλήπτης κάτι πίσω.</dd>
<dt>Drop Off / Pickup</dt><dd>Τρόποι επιστροφής: ο πελάτης φέρνει το δέμα σε κατάστημα Courier Center (Drop Off) ή ο courier το παίρνει από το σπίτι του (Pickup).</dd>
<dt>Επόμενη Μέρα / Αυθημερόν</dt><dd>Υπηρεσίες παράδοσης: την επόμενη εργάσιμη, ή την ίδια μέρα σε 3 ή 5 ώρες (όπου διατίθεται).</dd>
<dt>Status / Κατάσταση</dt><dd>Πού βρίσκεται το δέμα τώρα (Νέα, Σε διανομή, Παραδόθηκε…). Δείτε <a href="#cc-c1">Γ1</a>.</dd>
<dt>Στοιχεία σύνδεσης (API Credentials)</dt><dd>User Alias, Credential Value και API Key: τα «κλειδιά» με τα οποία το plugin μιλά με την Courier Center στο όνομά σας.</dd>
</dl>
</div>
<!-- ═══════════ ΤΙ ΝΕΟ ═══════════ -->
<div class="card" id="cc-new">
<?php include __DIR__ . '/docs-changelog.php'; ?>
</div>
<p style="color:#888;font-size:12px;text-align:center;margin:24px 0;">Courier Center for WooCommerce · v<?php echo esc_html( $version ); ?> · courier.gr</p>
</div>
</div>
