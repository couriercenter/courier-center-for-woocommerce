# BOX NOW — Προεπιλεγμένη (κλειδωμένη) επιλογή ανά Shipping Method

**Ημερομηνία:** 2026-06-26
**Έκδοση plugin (τρέχουσα):** 1.3.8

## Σκοπός

Προσθήκη ρύθμισης στον admin ώστε, όταν ενεργοποιηθεί, το BOX NOW widget να
εμφανίζεται **ήδη τσεκαρισμένο και κλειδωμένο** στο checkout μόλις ο πελάτης
επιλέξει μια από τις ρυθμισμένες μεθόδους αποστολής "Courier Center". Ο πελάτης
δεν μπορεί να αποεπιλέξει το BOX NOW χειροκίνητα· για να το αφαιρέσει πρέπει να
επιλέξει άλλη μέθοδο αποστολής.

## Υπάρχουσα συμπεριφορά (baseline)

- Settings section `cc_wc_boxnow_section` με:
  - `cc_wc_boxnow_enabled` — ενεργοποίηση του widget.
  - `cc_wc_boxnow_shipping_methods` — λίστα instance IDs μεθόδων που μετράνε ως
    "Courier Center".
- Στο checkout το widget έχει toggle `#cc-boxnow-toggle` (name `cc_boxnow_selected`,
  value `1`), **μη τσεκαρισμένο** by default. Όταν τσεκαριστεί, ανοίγουν οι
  επιλογές (Αυτόματη εύρεση / Επιλογή από χάρτη).
- `updateBoxNowVisibility()` στο `cc-boxnow-checkout.js` εμφανίζει/κρύβει το widget
  ανάλογα με την επιλεγμένη μέθοδο. Όταν δεν υπάρχει match και το toggle είναι
  checked, το ξε-τσεκάρει αυτόματα. Λειτουργεί σε classic & block checkout.

## Νέα συμπεριφορά

### 1. Ρύθμιση (admin) — `admin/class-cc-settings.php`

- Νέα option `cc_wc_boxnow_default_selected`, type `string`, sanitize
  `sanitize_text_field`, default `'0'`, registered στο `cc_wc_settings`.
- Νέο `add_settings_field` στο `cc_wc_boxnow_section`, μετά το
  `cc_wc_boxnow_shipping_methods`, με checkbox:
  *«Προεπιλεγμένη επιλογή BOX NOW — να εμφανίζεται ήδη τσεκαρισμένο και κλειδωμένο
  όταν ο πελάτης επιλέξει μέθοδο "Courier Center". Για να αφαιρεθεί, ο πελάτης
  επιλέγει άλλη μέθοδο αποστολής.»*
- Προσθήκη της option στη λίστα `$options` του `ajax_clear_settings`.

### 2. Frontend localize — `includes/class-cc-boxnow-checkout.php`

- Στο `wp_localize_script( 'cc-boxnow-checkout', 'ccBoxNow', [...] )` προσθήκη:
  `'defaultSelected' => get_option( 'cc_wc_boxnow_default_selected', '0' )`.

### 3. Συμπεριφορά checkout — `assets/js/cc-boxnow-checkout.js`

- Διάβασμα `var defaultSelected = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.defaultSelected === '1' );`.
- Στο `updateBoxNowVisibility()`, όταν `match === true` **και** `defaultSelected`:
  - Αν το `#cc-boxnow-toggle` δεν είναι checked, το θέτει checked και κάνει
    `trigger('change')` (ώστε να ανοίξουν οι επιλογές μέσω του υπάρχοντος handler).
  - Θέτει κατάσταση «locked» (flag + οπτική ένδειξη, π.χ. CSS class στο
    `#cc-boxnow-toggle-label`, `cursor: not-allowed` / ελαφρύ fade).
  - **Δεν** χρησιμοποιείται το attribute `disabled`, ώστε η τιμή να συνεχίζει να
    υποβάλλεται στο classic checkout.
- Κλείδωμα ξε-τσεκαρίσματος: στον change handler του `#cc-boxnow-toggle`, αν είναι
  locked και ο πελάτης το έκανε uncheck, επανέρχεται σε checked (`prop('checked', true)`)
  και εμφανίζεται ειδοποίηση *«Για να αφαιρέσετε την υπηρεσία BOX NOW, επιλέξτε
  άλλον τρόπο μεταφοράς.»*. Η ειδοποίηση εμφανίζεται **μόνο** κατά την προσπάθεια
  ξε-τσεκαρίσματος (όχι μόνιμα). Χρήση guard flag ώστε τα προγραμματιστικά
  `trigger('change')` να μη μετράνε ως ενέργεια χρήστη και να μην προκαλούν loop.
- Όταν `match === false` (ο πελάτης επιλέγει μέθοδο εκτός Courier Center): το
  υπάρχον reset/uncheck εκτελείται, αλλά πρώτα αίρεται το locked flag ώστε το
  προγραμματιστικό uncheck να μην μπλοκαριστεί από το κλείδωμα.
- Ισχύει τόσο σε classic όσο και σε block checkout (κοινό `updateBoxNowVisibility`).

### Εκτός scope (YAGNI)

- Καμία προεπιλογή delivery mode (auto/χάρτη) — ο πελάτης διαλέγει κανονικά.
- Καμία ανά-μέθοδο ξεχωριστή ρύθμιση· ένα global checkbox που ισχύει για όλες τις
  ρυθμισμένες μεθόδους.

## Edge cases

- Default option ON αλλά ο πελάτης διαλέγει μη-Courier Center μέθοδο → widget κρυφό,
  toggle unchecked, locked flag off.
- Default option OFF → υπάρχουσα συμπεριφορά αμετάβλητη (toggle unchecked, ελεύθερο).
- Locked & checked αλλά χωρίς επιλεγμένο delivery mode → υπάρχουσα validation
  (classic & block) σταματά την παραγγελία και ζητά επιλογή auto/χάρτη.
- Block checkout re-render (React) → η visibility/lock λογική ξανατρέχει μέσω
  `wp.data.subscribe`.

## Αρχεία που αλλάζουν

- `admin/class-cc-settings.php`
- `includes/class-cc-boxnow-checkout.php`
- `assets/js/cc-boxnow-checkout.js`
- (πιθανώς) `assets/css/cc-boxnow-checkout.css` για το locked visual
- Bump έκδοσης plugin → 1.3.9 (header + `CC_WC_VERSION`)
