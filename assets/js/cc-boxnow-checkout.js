( function ( $ ) {
    'use strict';

    var partnerId       = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.partnerId )       ? ccBoxNow.partnerId       : 0;
    var isBlockCheckout = ( typeof ccBoxNow !== 'undefined' && ccBoxNow.isBlockCheckout === '1' );
    var sdkLoaded       = false;
    var mapInited       = false;
    var widgetWrapper   = null; // saved reference for block checkout re-injection

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

        // Block checkout: τοποθετεί το widget πριν το payment block
        if ( isBlockCheckout ) {
            // FIX #1: Προσθήκη 2500ms για αργά sites / μεγάλα themes
            setTimeout( injectWidgetInBlockCheckout, 200 );
            setTimeout( injectWidgetInBlockCheckout, 600 );
            setTimeout( injectWidgetInBlockCheckout, 1200 );
            setTimeout( injectWidgetInBlockCheckout, 2500 );

            // Ανανεώνει αν το React αφαιρέσει ή μετακινήσει το widget κατά re-render
            setInterval( function () {
                if ( ! widgetWrapper ) return;
                var paymentBlock = document.querySelector( '.wp-block-woocommerce-checkout-payment-block' );
                if ( ! paymentBlock ) return;
                if ( ! document.body.contains( widgetWrapper ) || paymentBlock.previousElementSibling !== widgetWrapper ) {
                    injectWidgetInBlockCheckout();
                }
            }, 800 );
        }

        // Μεταφορά modal στο body ώστε να μην επηρεάζεται από parent display:none
        $( 'body' ).append( $( '#cc-boxnow-modal-overlay' ).detach() );

        // Toggle BOX NOW options
        $( document ).on( 'change', '#cc-boxnow-toggle', function () {
            if ( $( this ).is( ':checked' ) ) {
                $( '#cc-boxnow-options' ).addClass( 'cc-visible' );
            } else {
                $( '#cc-boxnow-options' ).removeClass( 'cc-visible' );
                resetBoxNowOptions();
            }
            syncSession();
        } );

        // Αυτόματη εύρεση
        $( document ).on( 'click', '#cc-boxnow-auto-btn', function () {
            $( '#cc_boxnow_delivery_mode' ).val( 'auto' );
            $( '#cc_boxnow_locker_id' ).val( '' );
            $( '#cc-boxnow-mode-select' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-selected-info' ).removeClass( 'cc-visible' );
            $( '#cc-boxnow-auto-confirm' ).addClass( 'cc-visible' );
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
            syncSession();
        } );

    } );

} )( jQuery );
