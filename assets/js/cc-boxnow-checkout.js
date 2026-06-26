( function ( $ ) {
    'use strict';

    var partnerId       = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.partnerId )       ? ccBoxNow.partnerId       : 0;
    var isBlockCheckout = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.isBlockCheckout === '1' );
    var allowedShippingIds = ( typeof ccBoxNow !== 'undefined' && Array.isArray( ccBoxNow.allowedShippingInstanceIds ) )
        ? ccBoxNow.allowedShippingInstanceIds
        : [];
    var defaultSelected = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.defaultSelected === '1' );
    var sdkLoaded       = false;
    var mapInited       = false;
    var widgetWrapper   = null; // saved reference for block checkout re-injection
    var toggleLocked       = false; // true όταν το BOX NOW είναι κλειδωμένο (default selection)
    var programmaticToggle = false; // guard: τα προγραμματιστικά change δεν μετράνε ως ενέργεια χρήστη
    var lockedNoticeTimer  = null;

    // Ορισμός config ΠΡΙΝ φορτώσει το SDK
    window._bn_map_widget_config = {
        partnerId    : partnerId,
        parentElement: '#boxnowmap',
        afterSelect  : function ( selected ) {
            $( '#cc_boxnow_locker_id'   ).val( selected.boxnowLockerId || '' );
            $( '#cc_boxnow_locker_code' ).val( selected.boxnowLockerId || '' );
            $( '#cc_boxnow_locker_name' ).val( selected.boxnowLockerAddressLine1 || '' );
            $( '#cc-boxnow-selected-name' ).text( selected.boxnowLockerAddressLine1 || '' );
            closeModal();
            $( '#cc-boxnow-selected-info' ).addClass( 'cc-visible' );
            $( '#cc-boxnow-mode-select' ).removeClass( 'cc-visible' );
            syncSession();
        }
    };

    function syncSession() {
        if ( ! ccBoxNow.ajaxUrl || ! ccBoxNow.sessionNonce ) return;
        $.post( ccBoxNow.ajaxUrl, {
            action        : 'cc_boxnow_save_session',
            nonce         : ccBoxNow.sessionNonce,
            selected      : $( '#cc-boxnow-toggle' ).is( ':checked' ) ? '1' : '',
            delivery_mode : $( '#cc_boxnow_delivery_mode' ).val(),
            locker_id     : $( '#cc_boxnow_locker_id' ).val(),
            locker_code   : $( '#cc_boxnow_locker_code' ).val(),
            locker_name   : $( '#cc_boxnow_locker_name' ).val(),
        } );
    }

    // FIX #3: CSS class αντί για inline style — δεν συγκρούεται με άλλα plugins/modals
    function openModal() {
        $( '#cc-boxnow-modal-overlay' ).addClass( 'cc-open' );
        $( 'body' ).addClass( 'cc-modal-open' );
    }

    function closeModal() {
        $( '#cc-boxnow-modal-overlay' ).removeClass( 'cc-open' );
        $( 'body' ).removeClass( 'cc-modal-open' );
    }

    // Αντικαθιστά το #boxnowmap με νέο κενό div — φρέσκο container για το SDK
    function freshMapContainer() {
        $( '#boxnowmap' ).replaceWith( '<div id="boxnowmap"></div>' );
    }

    function showSdkError() {
        closeModal();
        $( '#cc-boxnow-mode-select' ).addClass( 'cc-visible' );
        $( '#cc-boxnow-auto-confirm' ).removeClass( 'cc-visible' );
        $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
        if ( ! $( '#cc-boxnow-sdk-error' ).length ) {
            $( '#cc-boxnow-options' ).prepend(
                '<p id="cc-boxnow-sdk-error" class="cc-boxnow-sdk-error">' +
                'Ο χάρτης δεν είναι διαθέσιμος αυτή τη στιγμή. Δοκίμασε την αυτόματη εύρεση.' +
                '</p>'
            );
        }
    }

    function showValidationError( msg ) {
        $( '#cc-boxnow-widget-wrap' ).addClass( 'cc-has-error' );
        if ( ! $( '#cc-boxnow-validation-error' ).length ) {
            $( '#cc-boxnow-widget-wrap' ).append(
                '<div id="cc-boxnow-validation-error" class="cc-boxnow-validation-error">' +
                '<svg viewBox="0 0 20 20" fill="none" width="16" height="16" style="flex-shrink:0;display:block">' +
                '<circle cx="10" cy="10" r="9" stroke="#c53030" stroke-width="1.8"/>' +
                '<path d="M10 5.5v5.5" stroke="#c53030" stroke-width="1.8" stroke-linecap="round"/>' +
                '<circle cx="10" cy="14" r="1" fill="#c53030"/>' +
                '</svg>' +
                '<span>' + msg + '</span>' +
                '</div>'
            );
        }
        var wrap = document.getElementById( 'cc-boxnow-widget-wrap' );
        if ( wrap ) wrap.scrollIntoView( { behavior: 'smooth', block: 'center' } );
    }

    function clearValidationError() {
        $( '#cc-boxnow-widget-wrap' ).removeClass( 'cc-has-error' );
        $( '#cc-boxnow-validation-error' ).remove();
    }

    // Ειδοποίηση όταν ο πελάτης προσπαθεί να ξε-τσεκάρει το κλειδωμένο BOX NOW
    function showLockedNotice() {
        var $notice = $( '#cc-boxnow-locked-notice' );
        if ( ! $notice.length ) {
            $notice = $( '<div id="cc-boxnow-locked-notice" class="cc-boxnow-locked-notice"></div>' )
                .text( 'Για να αφαιρέσετε την υπηρεσία BOX NOW, επιλέξτε άλλον τρόπο μεταφοράς.' );
            $( '#cc-boxnow-widget-wrap' ).append( $notice );
        }
        $notice.addClass( 'cc-visible' );
        clearTimeout( lockedNoticeTimer );
        lockedNoticeTimer = setTimeout( function () {
            $notice.removeClass( 'cc-visible' );
        }, 5000 );
    }

    // FIX #2: Προσθήκη onerror — εμφανίζει μήνυμα αν το BOX NOW CDN δεν φορτώσει
    function loadSdk( callback ) {
        if ( sdkLoaded ) {
            if ( callback ) callback();
            return;
        }
        sdkLoaded = true;

        var s   = document.createElement( 'script' );
        s.src   = 'https://widget-cdn.boxnow.gr/map-widget/client/v5.js';
        s.async = false;
        s.onload = function () {
            $( '#cc-boxnow-sdk-error' ).remove();
            if ( callback ) callback();
        };
        s.onerror = function () {
            sdkLoaded = false;
            showSdkError();
        };
        document.head.appendChild( s );
    }

    // Ανοίγει το modal με φρέσκο χάρτη
    function openPickModal() {
        $( 'script[src*="widget-cdn.boxnow.gr"]' ).remove();
        sdkLoaded = false;
        mapInited = true;

        freshMapContainer();
        openModal();

        loadSdk( function () {
            var btn = document.querySelector( '.boxnow-map-widget-button' );
            if ( btn ) btn.click();
        } );
    }

    function resetBoxNowOptions() {
        closeModal();
        freshMapContainer();
        $( 'script[src*="widget-cdn.boxnow.gr"]' ).remove();
        sdkLoaded = false;
        mapInited = false;

        $( '#cc_boxnow_locker_id' ).val( '' );
        $( '#cc_boxnow_locker_code' ).val( '' );
        $( '#cc_boxnow_locker_name' ).val( '' );
        $( '#cc_boxnow_delivery_mode' ).val( '' );
        $( '#cc-boxnow-auto-confirm' ).removeClass( 'cc-visible' );
        $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
        $( '#cc-boxnow-mode-select' ).addClass( 'cc-visible' );
        $( '#cc-boxnow-sdk-error' ).remove();
    }

    // ─── Show BOX NOW only when the selected shipping method is "Courier Center" ───

    // Εξάγει το instance_id από rate id τύπου "flat_rate:12" -> "12"
    function getInstanceId( rateId ) {
        if ( ! rateId ) return null;
        var parts = String( rateId ).split( ':' );
        return parts.length > 1 ? parts[ parts.length - 1 ] : null;
    }

    function getClassicSelectedInstanceIds() {
        var ids = [];
        $( 'input[name^="shipping_method"]:checked' ).each( function () {
            var id = getInstanceId( $( this ).val() );
            if ( id ) ids.push( id );
        } );
        return ids;
    }

    function getBlockSelectedInstanceIds() {
        var ids = [];
        if ( typeof wp === 'undefined' || ! wp.data || ! wp.data.select ) return ids;
        var cartStore = wp.data.select( 'wc/store/cart' );
        if ( ! cartStore || ! cartStore.getShippingRates ) return ids;

        ( cartStore.getShippingRates() || [] ).forEach( function ( pkg ) {
            ( pkg.shipping_rates || [] ).forEach( function ( rate ) {
                if ( rate.selected ) {
                    var id = getInstanceId( rate.rate_id );
                    if ( id ) ids.push( id );
                }
            } );
        } );
        return ids;
    }

    function updateBoxNowVisibility() {
        if ( ! allowedShippingIds.length ) return;

        var selectedIds = isBlockCheckout ? getBlockSelectedInstanceIds() : getClassicSelectedInstanceIds();
        var match = selectedIds.some( function ( id ) {
            return allowedShippingIds.indexOf( id ) !== -1;
        } );

        var $target = isBlockCheckout ? $( '#cc-boxnow-block-wrapper' ) : $( 'tr.cc-boxnow-addon-row' );

        if ( match ) {
            $target.removeClass( 'cc-boxnow-shipping-hidden' );

            // Default selection: τσεκάρισμα + κλείδωμα του BOX NOW
            if ( defaultSelected ) {
                toggleLocked = true;
                $( '#cc-boxnow-toggle-label' ).addClass( 'cc-boxnow-locked' );
                if ( ! $( '#cc-boxnow-toggle' ).is( ':checked' ) ) {
                    programmaticToggle = true;
                    $( '#cc-boxnow-toggle' ).prop( 'checked', true ).trigger( 'change' );
                    programmaticToggle = false;
                }
            }
        } else {
            // Ξεκλείδωμα ΠΡΙΝ το uncheck, ώστε το προγραμματιστικό change να μην μπλοκαριστεί
            toggleLocked = false;
            $( '#cc-boxnow-toggle-label' ).removeClass( 'cc-boxnow-locked' );
            $( '#cc-boxnow-locked-notice' ).removeClass( 'cc-visible' );
            if ( $( '#cc-boxnow-toggle' ).is( ':checked' ) ) {
                programmaticToggle = true;
                $( '#cc-boxnow-toggle' ).prop( 'checked', false ).trigger( 'change' );
                programmaticToggle = false;
            }
            $target.addClass( 'cc-boxnow-shipping-hidden' );
        }
    }

    // ─── Block Checkout: inject widget before payment block (bottom of form) ───

    function injectWidgetInBlockCheckout() {
        if ( ! widgetWrapper ) {
            widgetWrapper = document.getElementById( 'cc-boxnow-block-wrapper' );
        }
        if ( ! widgetWrapper ) return;

        // Τοποθέτηση πριν το payment block (κάτω μέρος της φόρμας)
        var paymentBlock = document.querySelector( '.wp-block-woocommerce-checkout-payment-block' );
        if ( ! paymentBlock ) return;

        // Re-inject if removed from DOM or not in the right position
        if ( ! document.body.contains( widgetWrapper ) || paymentBlock.previousElementSibling !== widgetWrapper ) {
            paymentBlock.insertAdjacentElement( 'beforebegin', widgetWrapper );
            widgetWrapper.style.display = '';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    $( document ).ready( function () {

        // Εμφάνιση/απόκρυψη BOX NOW ανάλογα με την επιλεγμένη μέθοδο αποστολής
        if ( allowedShippingIds.length ) {
            updateBoxNowVisibility();

            if ( isBlockCheckout ) {
                if ( typeof wp !== 'undefined' && wp.data && wp.data.subscribe ) {
                    wp.data.subscribe( updateBoxNowVisibility );
                }
            } else {
                $( document.body ).on( 'updated_checkout', updateBoxNowVisibility );
                $( document ).on( 'change', 'input[name^="shipping_method"]', updateBoxNowVisibility );
            }
        }

        // Block checkout: τοποθετεί το widget πριν το payment block
        if ( isBlockCheckout ) {
            // FIX #1: Προσθήκη 2500ms για αργά sites / μεγάλα themes
            setTimeout( injectWidgetInBlockCheckout, 200 );
            setTimeout( injectWidgetInBlockCheckout, 600 );
            setTimeout( injectWidgetInBlockCheckout, 1200 );
            setTimeout( injectWidgetInBlockCheckout, 2500 );

            // Ανανεώνει αν το React αφαιρέσει ή μετακινήσει το widget κατά re-render
            // Σταματάει μετά από 30 δευτερόλεπτα — αρκετά για οποιοδήποτε site
            var injectInterval = setInterval( function () {
                if ( ! widgetWrapper ) return;
                var paymentBlock = document.querySelector( '.wp-block-woocommerce-checkout-payment-block' );
                if ( ! paymentBlock ) return;
                if ( ! document.body.contains( widgetWrapper ) || paymentBlock.previousElementSibling !== widgetWrapper ) {
                    injectWidgetInBlockCheckout();
                }
            }, 800 );
            setTimeout( function () { clearInterval( injectInterval ); }, 30000 );
        }

        // Μεταφορά modal στο body ώστε να μην επηρεάζεται από parent display:none
        $( 'body' ).append( $( '#cc-boxnow-modal-overlay' ).detach() );

        // Toggle BOX NOW options
        $( document ).on( 'change', '#cc-boxnow-toggle', function () {
            // Κλειδωμένο BOX NOW: ο πελάτης δεν μπορεί να το ξε-τσεκάρει χειροκίνητα
            if ( ! $( this ).is( ':checked' ) && toggleLocked && ! programmaticToggle ) {
                $( this ).prop( 'checked', true );
                showLockedNotice();
                return;
            }

            if ( $( this ).is( ':checked' ) ) {
                $( '#cc-boxnow-options' ).addClass( 'cc-visible' );
            } else {
                $( '#cc-boxnow-options' ).removeClass( 'cc-visible' );
                resetBoxNowOptions();
            }
            clearValidationError();
            syncSession();
        } );

        // Block checkout: interception του Place Order button για client-side validation
        if ( isBlockCheckout ) {
            $( document ).on( 'click', '.wc-block-components-checkout-place-order-button', function ( e ) {
                if ( ! $( '#cc-boxnow-toggle' ).is( ':checked' ) ) return;
                var mode = $( '#cc_boxnow_delivery_mode' ).val();
                if ( ! mode ) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    showValidationError( 'Επέλεξες BOX NOW αλλά δεν ολοκλήρωσες την επιλογή. Διάλεξε <strong>Αυτόματη εύρεση</strong> ή <strong>Επιλογή από χάρτη</strong>, ή αποεπέλεξε το BOX NOW για να συνεχίσεις.' );
                    return false;
                }
                if ( mode === 'pick' && ! $( '#cc_boxnow_locker_id' ).val() ) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    showValidationError( 'Επέλεξες παράδοση σε συγκεκριμένο Locker αλλά δεν επέλεξες Locker από τον χάρτη. Πάτα <strong>Επιλογή από χάρτη</strong> για να διαλέξεις.' );
                    return false;
                }
                clearValidationError();
            } );
        }

        // Αυτόματη εύρεση
        $( document ).on( 'click', '#cc-boxnow-auto-btn', function () {
            $( '#cc_boxnow_delivery_mode' ).val( 'auto' );
            $( '#cc_boxnow_locker_id' ).val( '' );
            $( '#cc-boxnow-mode-select' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-auto-confirm' ).addClass( 'cc-visible' );
            clearValidationError();
            syncSession();
        } );

        // Επιλογή locker — ανοίγει modal με φρέσκο χάρτη
        $( document ).on( 'click', '#cc-boxnow-pick-btn', function () {
            $( '#cc_boxnow_delivery_mode' ).val( 'pick' );
            $( '#cc-boxnow-mode-select' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-auto-confirm' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
            openPickModal();
        } );

        // Αλλαγή Locker — ξανανοίγει απευθείας το modal χωρίς mode-select
        $( document ).on( 'click', '#cc-boxnow-change-btn', function () {
            $( '#cc_boxnow_locker_id' ).val( '' );
            $( '#cc_boxnow_locker_code' ).val( '' );
            $( '#cc_boxnow_locker_name' ).val( '' );
            $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
            openPickModal();
        } );

        // Κλείσιμο modal με X
        $( document ).on( 'click', '#cc-boxnow-modal-close', function () {
            closeModal();
            if ( ! $( '#cc-boxnow-selected-info' ).hasClass( 'cc-visible' ) ) {
                $( '#cc-boxnow-mode-select' ).addClass( 'cc-visible' );
            }
        } );

        // Κλείσιμο με κλικ στο backdrop
        $( document ).on( 'click', '#cc-boxnow-modal-overlay', function ( e ) {
            if ( e.target === this ) {
                $( '#cc-boxnow-modal-close' ).trigger( 'click' );
            }
        } );

        // Κλείσιμο με ESC
        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && $( '#cc-boxnow-modal-overlay' ).hasClass( 'cc-open' ) ) {
                $( '#cc-boxnow-modal-close' ).trigger( 'click' );
            }
        } );

        // Αλλαγή από auto
        $( document ).on( 'click', '#cc-boxnow-auto-change-btn', function () {
            resetBoxNowOptions();
            clearValidationError();
            syncSession();
        } );

    } );

} )( jQuery );
