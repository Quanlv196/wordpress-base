/**
 * EVF API Connector – Admin Settings JS
 *
 * Responsibilities:
 *  1. Show/hide custom message textareas when the toggle changes.
 *  2. "Add Header" button: append a new header row.
 *  3. "Add Mapping" button: append a new mapping row, then refresh the
 *     EVF field <select> via AJAX so the new row has all options.
 *  4. Delegate "Remove" button clicks for any repeatable row.
 *  5. When the page loads, refresh field <select> options via AJAX so they
 *     always reflect the current EVF form (safe even on first load because the
 *     server already pre-populates them – AJAX is the single source of truth
 *     for dynamically added rows).
 *
 * Depends on: jQuery (enqueued by WP), evfApiConnector global (wp_localize_script).
 */
/* global evfApiConnector, jQuery */

(function ($) {
  "use strict";

  var api = evfApiConnector;
  var i18n = api.i18n;

  // ── DOM refs ──────────────────────────────────────────────────────────────
  var $headersList = $("#evf-headers-list");
  var $mappingsList = $("#evf-mappings-list");

  // ── 1. Custom message toggle ──────────────────────────────────────────────
  $("#use_custom_messages").on("change", function () {
    $(".evf-custom-msg-row").toggle(this.checked);
  });

  // ── 2. Add header row ─────────────────────────────────────────────────────
  $(".evf-add-header").on("click", function () {
    $headersList.append(buildHeaderRow("", ""));
  });

  // ── 3. Add mapping row ────────────────────────────────────────────────────
  $(".evf-add-mapping").on("click", function () {
    var $row = $(buildMappingRowHtml("", ""));
    $mappingsList.append($row);
    populateFieldSelect($row.find(".evf-field-select"), "");
  });

  // ── 4. Remove row (delegated) ─────────────────────────────────────────────
  $(document).on("click", ".evf-remove-row", function () {
    $(this).closest("tr").remove();
  });

  // ── 5. Initial field select population ───────────────────────────────────
  // If a form is already selected (page reload), populate all mapping selects.
  if (api.selectedForm) {
    fetchFields(api.selectedForm, function (fields) {
      $mappingsList.find(".evf-field-select").each(function () {
        var currentVal = $(this).val();
        replaceSelectOptions($(this), fields, currentVal);
      });
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  /**
   * Build HTML string for a new header row.
   *
   * @param  {string} key
   * @param  {string} value
   * @return {string}
   */
  function buildHeaderRow(key, value) {
    return (
      '<tr class="evf-header-row">' +
      '<td><input type="text" name="header_key[]" value="' +
      esc(key) +
      '" placeholder="Authorization" class="regular-text"></td>' +
      '<td><input type="text" name="header_value[]" value="' +
      esc(value) +
      '" placeholder="Bearer your-token-here" class="regular-text"></td>' +
      '<td><button type="button" class="button button-small evf-remove-row">' +
      i18n.removeHeader +
      "</button></td>" +
      "</tr>"
    );
  }

  /**
   * Build HTML string for a new mapping row (field select is empty; populated via AJAX).
   *
   * @param  {string} evfVal  Currently selected EVF meta_key.
   * @param  {string} apiVal  Currently saved API field name.
   * @return {string}
   */
  function buildMappingRowHtml(evfVal, apiVal) {
    return (
      '<tr class="evf-mapping-row">' +
      '<td><select name="mapping_evf_field[]" class="evf-field-select">' +
      '<option value="">' +
      i18n.loadingFields +
      "</option>" +
      "</select></td>" +
      '<td><input type="text" name="mapping_api_field[]" value="' +
      esc(apiVal) +
      '" placeholder="api_field_name" class="regular-text"></td>' +
      '<td><button type="button" class="button button-small evf-remove-row">' +
      i18n.removeMapping +
      "</button></td>" +
      "</tr>"
    );
  }

  /**
   * Fetch EVF form fields from the server and run a callback with the array.
   *
   * @param {number}   formId
   * @param {Function} callback  Called with array of {meta_key, label, type}.
   */
  function fetchFields(formId, callback) {
    $.post(
      api.ajaxUrl,
      {
        action: "evf_api_connector_get_fields",
        _ajax_nonce: api.nonce,
        form_id: formId,
      },
      function (response) {
        if (response.success && Array.isArray(response.data)) {
          callback(response.data);
        }
      },
    );
  }

  /**
   * Directly populate a <select> with field options, pre-selecting a value.
   *
   * @param {jQuery} $select
   * @param {Array}  fields      [{meta_key, label, type}, ...]
   * @param {string} [selected]  meta_key to pre-select.
   */
  function replaceSelectOptions($select, fields, selected) {
    var html = '<option value="">' + i18n.selectField + "</option>";
    fields.forEach(function (f) {
      var isSelected = f.meta_key === selected ? " selected" : "";
      html +=
        '<option value="' +
        esc(f.meta_key) +
        '"' +
        isSelected +
        ">" +
        escHtml(f.label) +
        " (" +
        esc(f.meta_key) +
        ")</option>";
    });
    $select.html(html);
  }

  /**
   * Populate a single field <select> element via AJAX.
   *
   * @param {jQuery} $select
   * @param {string} [selectedVal]
   */
  function populateFieldSelect($select, selectedVal) {
    var formId = api.selectedForm;
    if (!formId) {
      return;
    }
    fetchFields(formId, function (fields) {
      replaceSelectOptions($select, fields, selectedVal || "");
    });
  }

  /**
   * Minimal HTML attribute escaping to prevent XSS when building HTML strings.
   * Values set via .html()/.append() can be attacker-controlled (e.g. field labels).
   *
   * @param  {string} str
   * @return {string}
   */
  function esc(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  /**
   * Escape text content (for uses inside element text nodes).
   *
   * @param  {string} str
   * @return {string}
   */
  function escHtml(str) {
    return $("<span>").text(String(str)).html();
  }
})(jQuery);
