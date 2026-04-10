/**
 * EVF API Connector - Frontend JS
 *
 * Problem: Everest Forms calls jQuery's trigger('reset') which dispatches a
 * synthetic DOM event but does NOT invoke the browser's native
 * HTMLFormElement.reset(). As a result, field values are never cleared.
 *
 * Fix 1 - Capture-phase intercept (primary, fires while form is still in DOM):
 *   Listen for 'reset' events in the capture phase. jQuery synthetic events
 *   have isTrusted=false; we call form.reset() when we see one on an EVF form.
 *   form.reset() fires a trusted (isTrusted=true) native event which we skip,
 *   preventing infinite recursion.
 *
 * Fix 2 - Custom DOM event fallback (fires after AJAX success):
 *   EVF dispatches "everest_forms_ajax_submission_success" after success.
 *   For form_state_type=reset (form stays visible), the form is still in the
 *   DOM and we call reset() as a safety net.
 */
( function () {
'use strict';

// Fix 1: intercept jQuery trigger('reset')
// EVF calls $(formTuple).trigger('reset') which fires isTrusted=false.
// We detect that and call the native form.reset() instead.
document.addEventListener(
'reset',
function ( e ) {
// isTrusted=false  synthetic event (from jQuery trigger)
// isTrusted=true   native browser reset (from our form.reset() call)
if ( e.isTrusted ) {
return;
}

var form = e.target;
if (
form &&
form.tagName === 'FORM' &&
typeof form.id === 'string' &&
form.id.indexOf( 'evf-form-' ) === 0
) {
form.reset(); // triggers isTrusted=true reset - no recursion
}
},
true // capture phase: fires before jQuery bubbling handlers
);

// Fix 2: fallback via EVF's post-AJAX custom event
// For form_state_type='reset' (form stays visible after submit).
document.addEventListener( 'everest_forms_ajax_submission_success', function ( event ) {
var formId = event.detail && event.detail.formId;
if ( ! formId ) {
return;
}

var form = document.getElementById( formId );
if ( form && typeof form.reset === 'function' ) {
form.reset();
}
} );
} )();
