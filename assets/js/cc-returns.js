/**
 * Επιστροφές (My Account): εναλλαγή μεθόδου (BOX NOW / Pickup), έλεγχοι φόρμας στον browser, αντιγραφή κωδικού BOX NOW.
 * Οι έλεγχοι του server είναι οι αυθεντικοί· εδώ μόνο άμεση ανατροφοδότηση.
 */
jQuery(function ($) {
    var cfg = window.ccReturns || {};

    // Ίδιος κανόνας με το CC_Returns_Rules::normalize_phone()
    function normalizePhone(raw) {
        var d = String(raw || '').replace(/\D+/g, '');
        if (d.indexOf('0030') === 0) {
            d = d.substring(4);
        } else if (d.indexOf('30') === 0 && d.length === 12) {
            d = d.substring(2);
        }
        return d;
    }
    function isMobile(d) { return /^69\d{8}$/.test(d); }
    function isLandline(d) { return /^2\d{9}$/.test(d); }

    var $form = $('#cc-return-form');
    if ($form.length) {
        var $boxnow = $form.find('.cc-return-boxnow');
        var $label = $form.find('.cc-return-mobile-label');
        var $error = $form.find('.cc-return-error');
        var $pickup = $form.find('.cc-return-pickup');
        var $from = $form.find('[name="pickup_time_from"]');
        var $to = $form.find('[name="pickup_time_to"]');
        var hourOf = function (v) { return v ? parseInt(String(v).substring(0, 2), 10) : NaN; };
        // Το «Έως» δείχνει μόνο ώρες ≥ Από + minHours
        var filterTo = function () {
            var f = hourOf($from.val());
            $to.find('option').each(function () {
                var v = $(this).val();
                var ok = !v || isNaN(f) || hourOf(v) - f >= (cfg.minHours || 4);
                $(this).prop('disabled', !ok).toggle(ok);
            });
            if ($to.find('option:selected').prop('disabled')) {
                $to.val('');
            }
        };
        $from.on('change', filterTo);
        filterTo();

        var selectedMethod = function () {
            return $form.find('input[name="method"]:checked').val();
        };
        var toggleMethod = function () {
            var isBox = selectedMethod() === cfg.boxnow;
            $boxnow.toggle(isBox);
            $pickup.toggle(selectedMethod() === cfg.pickup);
            $label.text(isBox ? $label.data('boxnow') : $label.data('base'));
        };
        $form.on('change', 'input[name="method"]', toggleMethod);
        toggleMethod();

        var showError = function (msg) {
            $error.text(msg).show();
            if ($error[0] && $error[0].scrollIntoView) {
                $error[0].scrollIntoView({ block: 'center' });
            }
        };

        $form.on('submit', function (e) {
            $error.hide().text('');

            var total = 0;
            $form.find('input[name^="qty["]').not(':disabled').each(function () {
                total += Math.max(0, parseInt($(this).val(), 10) || 0);
            });
            if (total < 1) {
                e.preventDefault();
                showError(cfg.msgItems);
                return;
            }

            if (selectedMethod() === cfg.pickup) {
                if (!$.trim($form.find('[name="pickup_address"]').val()) || !$.trim($form.find('[name="pickup_city"]').val())) {
                    e.preventDefault();
                    showError(cfg.msgPickupAddress);
                    return;
                }
                if (!/^\d{5}$/.test(String($form.find('[name="pickup_postcode"]').val() || '').replace(/\s+/g, ''))) {
                    e.preventDefault();
                    showError(cfg.msgPickupPostcode);
                    return;
                }
                var fv = $from.val(), tv = $to.val();
                if ((fv && !tv) || (!fv && tv)) {
                    e.preventDefault();
                    showError(cfg.msgPickupHalf);
                    return;
                }
                if (fv && tv && hourOf(tv) - hourOf(fv) < (cfg.minHours || 4)) {
                    e.preventDefault();
                    showError(cfg.msgPickupWindow);
                    return;
                }
            }

            var mobile = normalizePhone($form.find('[name="mobile"]').val());
            var landline = normalizePhone($form.find('[name="landline"]').val());
            if (selectedMethod() === cfg.boxnow) {
                if (!isMobile(mobile)) {
                    e.preventDefault();
                    showError(cfg.msgMobile);
                    return;
                }
                if (!$form.find('input[name="locker_size"]:checked').length) {
                    e.preventDefault();
                    showError(cfg.msgSize);
                    return;
                }
            } else if (!(isMobile(mobile) || isLandline(landline) || isLandline(mobile) || isMobile(landline))) {
                e.preventDefault();
                showError(cfg.msgPhone);
                return;
            }

            // Έγκυρη υποβολή: αποτρέπει διπλό κλικ (το cc_return_submit είναι hidden πεδίο, δεν χάνεται)
            $form.find('button[type="submit"]').prop('disabled', true);
        });
    }

    // Αντιγραφή κωδικού BOX NOW
    function fallbackCopy(text) {
        var $tmp = $('<input type="text" readonly>').val(text).css({ position: 'absolute', left: '-9999px' }).appendTo('body');
        $tmp[0].select();
        try { document.execCommand('copy'); } catch (err) { /* αγνοείται */ }
        $tmp.remove();
    }
    $(document).on('click', '.cc-return-copy', function () {
        var $btn = $(this);
        var text = String($btn.data('copy') || '');
        var done = function () {
            var old = $btn.data('label') || $btn.text();
            $btn.data('label', old).text(cfg.copied || old);
            setTimeout(function () { $btn.text(old); }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
        } else {
            fallbackCopy(text);
            done();
        }
    });
});
