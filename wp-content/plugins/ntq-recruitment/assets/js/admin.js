/**
 * NTQ Recruitment – Admin JavaScript
 * Handles: delete confirmation, dynamic status updates.
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ── Delete confirmation ────────────────────────────────────────────────
    $(document).on("click", ".delete-btn", function (e) {
      if (!window.confirm(NTQRecAdmin.confirm)) {
        e.preventDefault();
      }
    });

    // ── Highlight current filter row ───────────────────────────────────────
    var $filterSelects = $(".ntq-admin-filter select");
    $filterSelects.each(function () {
      if ($(this).val()) {
        $(this).css("border-color", "#2563eb");
      }
    });

    $filterSelects.on("change", function () {
      $(this).closest("form").submit();
    });

    // ── Auto-submit search on Enter ────────────────────────────────────────
    $('.ntq-admin-filter input[type="search"]').on("keypress", function (e) {
      if (e.which === 13) {
        $(this).closest("form").submit();
      }
    });
  });
})(jQuery);
