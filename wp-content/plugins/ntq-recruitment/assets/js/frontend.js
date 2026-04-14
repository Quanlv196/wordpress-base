/**
 * NTQ Recruitment – Frontend JavaScript
 * Handles: job filter/pagination (AJAX), application form submission (AJAX + file).
 */
(function ($) {
  "use strict";

  var i18n = NTQRec.i18n;
  var ajaxUrl = NTQRec.ajaxUrl;
  var nonce = NTQRec.nonce;

  // ===================================================================
  // Utility
  // ===================================================================
  function showLoading($el) {
    $el.addClass("ntq-loading");
  }

  function hideLoading($el) {
    $el.removeClass("ntq-loading");
  }

  // ===================================================================
  // Toast notification
  // ===================================================================
  var _toastTimer = null;

  function showToast(type, title, msg) {
    // Remove any existing toast
    $(".ntq-toast").remove();
    clearTimeout(_toastTimer);

    var iconHtml = type === "success" ? "&#10003;" : "&#33;";
    var $toast = $(
      '<div class="ntq-toast ntq-toast--' +
        type +
        '" role="alert" aria-live="assertive">' +
        '<span class="ntq-toast__icon">' +
        iconHtml +
        "</span>" +
        '<div class="ntq-toast__body">' +
        '<p class="ntq-toast__title">' +
        title +
        "</p>" +
        '<p class="ntq-toast__msg">' +
        msg +
        "</p>" +
        "</div>" +
        '<button class="ntq-toast__close" aria-label="Close">&times;</button>' +
        "</div>",
    );

    $("body").append($toast);

    // Animate in
    setTimeout(function () {
      $toast.addClass("ntq-toast--visible");
    }, 20);

    // Auto-dismiss after 6 s
    _toastTimer = setTimeout(function () {
      dismissToast($toast);
    }, 6000);

    // Manual close
    $toast.on("click", ".ntq-toast__close", function () {
      dismissToast($toast);
    });
  }

  function dismissToast($toast) {
    $toast.removeClass("ntq-toast--visible");
    setTimeout(function () {
      $toast.remove();
    }, 350);
  }

  // ===================================================================
  // Job filter + list (AJAX)
  // ===================================================================

  // Exposed by initJobFilter so initTabFilter can call it directly.
  var _reloadJobList = null;

  function initJobFilter() {
    var $filterForm = $("#ntq-rec-filter-form");
    var $jobList = $("#ntq-rec-job-list");

    if (!$jobList.length) return;

    /**
     * Build request payload from the current filter state and page number.
     * Reads both the dropdown form (if present) and any active tab filters.
     */
    function buildPayload(page) {
      var payload = {
        action: "ntq_rec_filter_jobs",
        nonce: nonce,
        page: page || 1,
        limit: parseInt($jobList.data("limit")) || 10,
      };

      if ($filterForm.length) {
        payload.department =
          $filterForm.find('[name="department"]').val() || "";
        payload.location = $filterForm.find('[name="location"]').val() || "";
        payload.job_id =
          parseInt($filterForm.find('[name="job_id"]').val()) || 0;
      }

      // Tab filters override the form values for the same key.
      // Use .attr() to read directly from DOM – avoids jQuery .data() caching quirks.
      $(".ntq-tab-filter").each(function () {
        var filterType = $(this).attr("data-filter-type");
        if (!filterType) return;
        var $active = $(this).find(".ntq-tab-filter__item--active");
        payload[filterType] = $active.length
          ? $active.attr("data-value") || ""
          : "";
      });

      return payload;
    }

    /**
     * Fire an AJAX request and replace the job list HTML.
     */
    function loadJobs(page) {
      showLoading($jobList);

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: buildPayload(page),
        success: function (res) {
          if (res.success) {
            $jobList.html(res.data.html);
            bindPagination();
          } else {
            $jobList.html('<p class="ntq-no-jobs">' + i18n.error + "</p>");
          }
        },
        error: function () {
          $jobList.html('<p class="ntq-no-jobs">' + i18n.error + "</p>");
        },
        complete: function () {
          hideLoading($jobList);
        },
      });
    }

    // Expose for initTabFilter – direct call, no event bridge needed.
    _reloadJobList = function (page) {
      loadJobs(page || 1);
    };

    // Bind filter form events
    if ($filterForm.length) {
      $filterForm.on("change", "select", function () {
        loadJobs(1);
      });

      $filterForm.on("submit", function (e) {
        e.preventDefault();
        loadJobs(1);
      });
    }

    // Bind pagination (delegated – runs after each AJAX replacement)
    function bindPagination() {
      $jobList
        .off("click.ntqPag")
        .on("click.ntqPag", ".ntq-pagination__item", function (e) {
          e.preventDefault();
          var page = parseInt($(this).data("page"));
          if (page) {
            // Scroll to job list top smoothly
            $("html, body").animate(
              { scrollTop: $jobList.offset().top - 80 },
              300,
            );
            loadJobs(page);
          }
        });
    }

    bindPagination();
  }

  // ===================================================================
  // Tab filter (links .ntq-tab-filter clicks → job list reload)
  // ===================================================================
  function initTabFilter() {
    $(document).on("click", ".ntq-tab-filter__item", function () {
      var $item = $(this);
      var $filter = $item.closest(".ntq-tab-filter");

      // Update active state
      $filter
        .find(".ntq-tab-filter__item")
        .removeClass("ntq-tab-filter__item--active");
      $item.addClass("ntq-tab-filter__item--active");

      // Call loadJobs directly via the reference set by initJobFilter.
      if (_reloadJobList) {
        _reloadJobList(1);
      }
    });
  }

  // ===================================================================
  // Application form (AJAX + file upload via FormData)
  // ===================================================================
  function initApplyForm() {
    var $form = $("#ntq-rec-apply-form");
    if (!$form.length) return;

    var $submitBtn = $form.find(".ntq-submit-btn");
    var $message = $form.find(".ntq-form-message");
    var origText = $submitBtn.text().trim();
    var isSubmitting = false; // guard against double-submission

    function restoreButton() {
      isSubmitting = false;
      $submitBtn.prop("disabled", false).text(origText);
    }

    function showMessage(type, text) {
      $message
        .removeClass("ntq-success ntq-error")
        .addClass("ntq-" + type)
        .html(text)
        .show();
      // Guard: offset() can return null if element has no layout yet
      try {
        var top = $message.offset();
        if (top) {
          $("html, body").animate({ scrollTop: top.top - 100 }, 300);
        }
      } catch (e) {
        // silently ignore scroll errors
      }
    }

    function clearErrors() {
      $form.find(".ntq-field-error").text("");
      $form
        .find(
          ".ntq-form-group input, .ntq-form-group select, .ntq-form-group textarea",
        )
        .removeClass("ntq-input--error");
    }

    function setError(selector, msg) {
      $form.find(selector).text(msg);
      $form
        .find(selector)
        .closest(".ntq-form-group")
        .find("input, select, textarea")
        .addClass("ntq-input--error");
    }

    function validate() {
      clearErrors();
      var ok = true;

      // Job position (only when the select exists in the form)
      var $jobSelect = $form.find('[name="job_id"]').filter("select");
      if ($jobSelect.length && !$jobSelect.val()) {
        setError(".error-job", i18n.required);
        ok = false;
      }

      // Name
      var name = $.trim($form.find('[name="applicant_name"]').val());
      if (!name) {
        setError(".error-name", i18n.required);
        ok = false;
      }

      // Phone
      var phone = $.trim($form.find('[name="phone"]').val());
      if (!phone) {
        setError(".error-phone", i18n.required);
        ok = false;
      }

      // Email
      var email = $.trim($form.find('[name="email"]').val());
      var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email) {
        setError(".error-email", i18n.required);
        ok = false;
      } else if (!emailRe.test(email)) {
        setError(".error-email", i18n.invalidEmail);
        ok = false;
      }

      // CV file
      var fileInput = $form.find('[name="cv_file"]')[0];
      if (!fileInput || fileInput.files.length === 0) {
        setError(".error-cv", i18n.required);
        ok = false;
      } else {
        var file = fileInput.files[0];
        var ext = file.name.split(".").pop().toLowerCase();
        var allowed = ["pdf", "doc", "docx"];
        var maxBytes = 5 * 1024 * 1024;

        if (allowed.indexOf(ext) === -1) {
          setError(".error-cv", i18n.invalidFile);
          ok = false;
        } else if (file.size > maxBytes) {
          setError(".error-cv", i18n.fileTooLarge);
          ok = false;
        }
      }

      return ok;
    }

    $form.on("submit", function (e) {
      e.preventDefault();

      // Prevent double-submission while a request is in flight
      if (isSubmitting) return;

      if (!validate()) return;

      isSubmitting = true;
      var formData = new FormData(this);
      formData.append("action", "ntq_rec_submit_application");
      formData.append("nonce", nonce);

      $submitBtn.prop("disabled", true).text(i18n.loading);
      $message.hide();

      // Failsafe: always restore the button after 90 s regardless of AJAX outcome
      var failsafeTimer = setTimeout(function () {
        restoreButton();
      }, 90000);

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        // No dataType: 'json' — let jQuery auto-detect via Content-Type header.
        // Forcing JSON parsing breaks when WordPress prepends debug notices.
        success: function (res) {
          clearTimeout(failsafeTimer);
          // Normalise: response may already be an object (auto-parsed) or a string
          var data = res;
          if (typeof res === "string") {
            try {
              data = JSON.parse(res);
            } catch (e) {
              showMessage("error", i18n.error);
              restoreButton();
              return;
            }
          }
          if (data && data.success) {
            var successMsg = (data.data && data.data.message) || i18n.success;

            // Replace the form with an inline confirmation block
            var $section = $form.closest(".ntq-apply-section");
            var $target = $section.length ? $section : $form.parent();
            $target.html(
              '<div class="ntq-apply-success">' +
                '<div class="ntq-apply-success__icon">&#10003;</div>' +
                '<h3 class="ntq-apply-success__title">' +
                i18n.successTitle +
                "</h3>" +
                '<p class="ntq-apply-success__sub">' +
                successMsg +
                "</p>" +
                "</div>",
            );

            // Additionally show a floating toast
            showToast("success", i18n.successTitle, successMsg);
          } else {
            showMessage(
              "error",
              (data && data.data && data.data.message) || i18n.error,
            );
          }
          restoreButton();
        },
        error: function () {
          clearTimeout(failsafeTimer);
          showMessage("error", i18n.error);
          restoreButton();
        },
      });
    });

    // Live validation on blur for better UX
    $form.find('[name="email"]').on("blur", function () {
      var email = $.trim($(this).val());
      var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email && !emailRe.test(email)) {
        $form.find(".error-email").text(i18n.invalidEmail);
      } else {
        $form.find(".error-email").text("");
      }
    });

    // File type preview feedback
    $form.find('[name="cv_file"]').on("change", function () {
      var file = this.files[0];
      var $error = $form.find(".error-cv");
      if (!file) return;

      var ext = file.name.split(".").pop().toLowerCase();
      var allowed = ["pdf", "doc", "docx"];
      if (allowed.indexOf(ext) === -1) {
        $error.text(i18n.invalidFile);
      } else if (file.size > 5 * 1024 * 1024) {
        $error.text(i18n.fileTooLarge);
      } else {
        $error.text("");
      }
    });
  }

  // ===================================================================
  // Bootstrap
  // ===================================================================
  $(document).ready(function () {
    initJobFilter();
    initTabFilter();
    initApplyForm();
  });
})(jQuery);
