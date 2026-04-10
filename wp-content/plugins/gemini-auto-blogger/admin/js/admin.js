/**
 * Gemini Auto Blogger – Admin JavaScript
 *
 * Handles:
 *  • "Test Connection" button on the settings page.
 *  • "Generate Now" button on the settings page.
 *  • "Clear All Logs" button on the logs page.
 *  • Show/hide the publish-delay row based on publish-status selection.
 *
 * Relies on the `gabAdmin` object localised by GAB_Admin::enqueue_assets().
 */

/* global gabAdmin, jQuery */
(function ($) {
  "use strict";

  // ── Helpers ─────────────────────────────────────────────────────────────

  /**
   * Show a result message next to a button.
   *
   * @param {jQuery}  $el      The result <span> element.
   * @param {string}  message  Text to display.
   * @param {string}  type     'success' | 'error' | 'info'
   */
  function showResult($el, message, type) {
    $el.removeClass("success error info").addClass(type).html(message);
  }

  function clearResult($el) {
    $el.removeClass("success error info").html("");
  }

  // ── Test API connection ──────────────────────────────────────────────────

  $("#gab-test-api").on("click", function () {
    var $btn = $(this);
    var $result = $("#gab-api-test-result");
    var groqApiKey = $("#gab_groq_api_key").val().trim();
    var geminiApiKey = $("#gab_gemini_api_key").val().trim();
    var cfAccountId = $("#gab_cf_account_id").val().trim();
    var textModel = $("#gab_text_model").val();
    var imageModel = "";

    if (!groqApiKey && !geminiApiKey) {
      showResult(
        $result,
        "Vui lòng nhập ít nhất 1 API key (Groq hoặc Cloudflare).",
        "error",
      );
      return;
    }

    clearResult($result);
    $btn.prop("disabled", true).text(gabAdmin.i18n.testing);

    $.ajax({
      url: gabAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "gab_test_api",
        nonce: gabAdmin.nonce,
        groq_api_key: groqApiKey,
        gemini_api_key: geminiApiKey,
        cf_account_id: cfAccountId,
        text_model: textModel,
        image_model: imageModel,
      },
      timeout: 30000,
      success: function (response) {
        if (response.success) {
          showResult($result, response.data.message, "success");
        } else {
          showResult($result, response.data.message, "error");
        }
      },
      error: function () {
        showResult($result, gabAdmin.i18n.requestFailed, "error");
      },
      complete: function () {
        $btn.prop("disabled", false).text("Kiểm tra kết nối");
      },
    });
  });

  // ── Generate Now ─────────────────────────────────────────────────────────
  // Simple synchronous approach: single AJAX call, PHP returns result directly.
  // Apache mod_php keeps the connection open for the full generation duration.

  $("#gab-generate-now").on("click", function () {
    var $btn = $(this);
    var $result = $("#gab-generate-result");
    var elapsedSec = 0;
    var tickTimer = setInterval(function () {
      elapsedSec++;
      var m = Math.floor(elapsedSec / 60);
      var s = elapsedSec % 60;
      var elapsed = (m > 0 ? m + " phút " : "") + s + " giây";
      showResult($result, "⏳ Đang tạo bài viết... (" + elapsed + ")", "info");
    }, 1000);

    $btn.prop("disabled", true).text(gabAdmin.i18n.generating);
    showResult($result, "⏳ " + gabAdmin.i18n.generating, "info");

    $.ajax({
      url: gabAdmin.ajaxUrl,
      type: "POST",
      data: { action: "gab_generate_now", nonce: gabAdmin.nonce },
      timeout: 600000, // 10 minutes – waits for full synchronous generation
      success: function (response) {
        clearInterval(tickTimer);
        if (response.success) {
          var d = response.data;
          var html = "✅ " + d.message;
          if (d.edit_url) {
            html +=
              ' – <a href="' +
              d.edit_url +
              '" target="_blank" rel="noopener noreferrer">Chỉnh sửa</a>';
          }
          if (d.view_url) {
            html +=
              ' | <a href="' +
              d.view_url +
              '" target="_blank" rel="noopener noreferrer">Xem bài</a>';
          }
          showResult($result, html, "success");
        } else {
          showResult($result, "❌ " + response.data.message, "error");
        }
      },
      error: function () {
        clearInterval(tickTimer);
        showResult(
          $result,
          "⚠️ Kết nối bị gián đoạn sau " +
            elapsedSec +
            "s. Kiểm tra <a href='?page=gemini-auto-blogger-logs'>Nhật ký</a> để xem kết quả.",
          "error",
        );
      },
      complete: function () {
        $btn.prop("disabled", false).text("Tạo bài ngay");
      },
    });
  });

  // ── Clear Logs ───────────────────────────────────────────────────────────

  $("#gab-clear-logs").on("click", function () {
    if (!window.confirm(gabAdmin.i18n.confirmClear)) {
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true).text(gabAdmin.i18n.clearing);

    $.ajax({
      url: gabAdmin.ajaxUrl,
      type: "POST",
      data: { action: "gab_clear_logs", nonce: gabAdmin.nonce },
      success: function (response) {
        if (response.success) {
          // Reload the page to show the empty state.
          window.location.reload();
        } else {
          window.alert(response.data.message);
          $btn.prop("disabled", false).text("Xóa tất cả nhật ký");
        }
      },
      error: function () {
        window.alert(gabAdmin.i18n.requestFailed);
        $btn.prop("disabled", false).text("Xóa tất cả nhật ký");
      },
    });
  });

  // ── Show / hide publish-delay row ────────────────────────────────────────

  $('select[name="gab_settings[publish_status]"]')
    .on("change", function () {
      var $row = $("#gab-publish-delay-row");

      if ($(this).val() === "future") {
        $row.show();
      } else {
        $row.hide();
      }
    })
    .trigger("change"); // apply on page load
})(jQuery);
