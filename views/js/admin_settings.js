/**
* 2022 Brightweb
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*  @author Brightweb SAS <jonathan@brightweb.cloud>
*  @copyright  2022 Brightweb SAS
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
$( document ).ready(function() {
    const clientIdLive = $( '#STAN_API_CLIENT_ID' );
    const clientSecretLive = $( '#STAN_API_CLIENT_SECRET' );
    const clientIdTest = $( '#STAN_API_CLIENT_ID_TEST' );
    const clientSecretTest = $( '#STAN_API_CLIENT_SECRET_TEST' );

    const testmodeCheck = $( '#STAN_CHECK_TESTMODE_on' );
    const testmodeCheckOff = $( '#STAN_CHECK_TESTMODE_off' );

    /*
     * Updates styles
     */
    clientSecretLive.closest( '.input-group' ).removeClass( 'fixed-width-lg' );
    clientSecretTest.closest( '.input-group' ).removeClass( 'fixed-width-lg' );

    const updateForm = (isTestmode) => {
        if (isTestmode) {
            clientIdLive.closest( '.form-group' ).hide();
            clientSecretLive.closest( '.form-group' ).hide();
            clientIdTest.closest( '.form-group' ).show();
            clientSecretTest.closest( '.form-group' ).show();
        } else {
            clientIdLive.closest( '.form-group' ).show();
            clientSecretLive.closest( '.form-group' ).show();
            clientIdTest.closest( '.form-group' ).hide();
            clientSecretTest.closest( '.form-group' ).hide();
        }
    }

    testmodeCheck.change(function() {
        updateForm(true);
    });
    testmodeCheckOff.change(function() {
        updateForm(false);
    });

    if (testmodeCheck[0]) {
        updateForm(testmodeCheck[0].checked);
    }
});