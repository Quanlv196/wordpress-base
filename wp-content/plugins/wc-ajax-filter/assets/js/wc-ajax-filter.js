/**
 * WC AJAX Filter — Frontend JavaScript
 *
 * Responsibilities:
 *  - Collect values from every [wc_filter] widget on the page
 *  - Debounce changes and send a single AJAX request per product list
 *  - Replace the grid, pagination, and result-count HTML in place
 *  - Keep the browser URL in sync (shareable filter state)
 *  - Restore filter state from URL on page load
 *
 * Requires: jQuery (bundled with WordPress)
 * Global:   wcaf_params  { ajax_url, nonce, i18n }
 *
 * @package WC_Ajax_Filter
 */

/* global wcaf_params, jQuery */

(function ($) {
  "use strict";

  // =========================================================================
  // WCAF Namespace
  // =========================================================================

  var WCAF = {
    /** Per-filter debounce timers keyed by filter ID. */
    debounceTimers: {},

    /** Active jQuery XHR objects keyed by list ID — abort stale requests. */
    activeXhr: {},

    /** Debounce delay in milliseconds. */
    DEBOUNCE_MS: 350,

    // =====================================================================
    // Init
    // =====================================================================

    /**
     * Initialise all sub-systems.
     */
    init: function () {
      this.bindFilterEvents();
      this.bindListControls();
      this.initPriceSliders();
      this.bindClearFilterButtons();
      this.bindActiveFilterEvents();
      this.restoreFromUrl();
      this.updateFilterCountBadges();
      this.renderActiveFilterTags();
    },

    // =====================================================================
    // Filter event binding
    // =====================================================================

    /**
     * Attach delegated listeners to filter inputs.
     * Delegation allows filters loaded via AJAX to work automatically.
     */
    bindFilterEvents: function () {
      var self = this;

      // Checkbox / radio.
      $(document).on(
        "change",
        '.wcaf-filter__input[type="checkbox"], .wcaf-filter__input[type="radio"]',
        function () {
          self.onFilterChange($(this).closest(".wcaf-filter"));
        },
      );

      // Multi-select dropdown (taxonomy).
      $(document).on(
        "change",
        ".wcaf-filter__select:not(.wcaf-filter__price-range-select)",
        function () {
          self.onFilterChange($(this).closest(".wcaf-filter"));
        },
      );

      // Price-range dropdown — parse "min:max" value into hidden inputs.
      $(document).on("change", ".wcaf-filter__price-range-select", function () {
        var parts = String($(this).val()).split(":");
        var $wrap = $(this).closest(".wcaf-filter__price-dropdown");
        $wrap.find('[data-filter-key="min_price"]').val(parts[0] || "");
        $wrap.find('[data-filter-key="max_price"]').val(parts[1] || "");
        self.onFilterChange($(this).closest(".wcaf-filter"));
      });

      // Tabs.
      $(document).on("click", ".wcaf-filter__tab", function () {
        var $tab = $(this);
        var $tabs = $tab.closest(".wcaf-filter__tabs");

        $tabs
          .find(".wcaf-filter__tab")
          .removeClass("is-active")
          .attr("aria-selected", "false");

        $tab.addClass("is-active").attr("aria-selected", "true");

        // Sync hidden value carrier.
        $tabs.find(".wcaf-filter__tab-value").val($tab.data("value"));

        self.onFilterChange($tab.closest(".wcaf-filter"));
      });
    },

    // =====================================================================
    // Product list controls (per-page, sort, pagination)
    // =====================================================================

    bindListControls: function () {
      var self = this;

      // Per-page change.
      $(document).on("change", ".wcaf-product-list__per-page", function () {
        var $list = $(this).closest(".wcaf-product-list");
        $list.data("per-page", $(this).val());
        $list.data("page", 1);
        self.refreshList($list);
      });

      // Sort order change.
      $(document).on("change", ".wcaf-product-list__orderby", function () {
        var parts = String($(this).val()).split(":");
        var $list = $(this).closest(".wcaf-product-list");
        $list.data("orderby", parts[0] || "date");
        $list.data("order", parts[1] || "DESC");
        $list.data("page", 1);
        self.refreshList($list);
      });

      // Pagination buttons.
      $(document).on("click", ".wcaf-pagination__btn", function () {
        var $list = $(this).closest(".wcaf-product-list");
        $list.data("page", $(this).data("page"));
        self.refreshList($list);

        // Scroll to top of list with a small top margin.
        $("html, body").animate({ scrollTop: $list.offset().top - 80 }, 300);
      });
    },

    // =====================================================================
    // Price slider
    // =====================================================================

    /**
     * Initialise all price range sliders present on the page.
     */
    initPriceSliders: function () {
      var self = this;
      $(".wcaf-filter__price-range").each(function () {
        self.setupPriceSlider($(this));
      });
    },

    /**
     * Wire up a single price-range slider element.
     *
     * @param {jQuery} $wrap The .wcaf-filter__price-range container.
     */
    setupPriceSlider: function ($wrap) {
      var self = this;
      var $sMin = $wrap.find(".wcaf-filter__range--min");
      var $sMax = $wrap.find(".wcaf-filter__range--max");
      var $iMin = $wrap.find(".wcaf-filter__price-min");
      var $iMax = $wrap.find(".wcaf-filter__price-max");
      var globalMin = parseFloat($wrap.data("min")) || 0;
      var globalMax = parseFloat($wrap.data("max")) || 1000;

      /** Update fill bar between the two thumbs. */
      function updateFill() {
        var fillEl = $wrap.find(".wcaf-filter__price-fill")[0];
        var pctMin =
          ((parseFloat($sMin.val()) - globalMin) / (globalMax - globalMin)) *
          100;
        var pctMax =
          ((parseFloat($sMax.val()) - globalMin) / (globalMax - globalMin)) *
          100;
        fillEl.style.left = pctMin + "%";
        fillEl.style.right = 100 - pctMax + "%";
      }

      // Slider → text input sync.
      $sMin.on("input", function () {
        var v = Math.min(parseFloat($(this).val()), parseFloat($sMax.val()));
        $(this).val(v);
        $iMin.val(v);
        updateFill();
      });

      $sMax.on("input", function () {
        var v = Math.max(parseFloat($(this).val()), parseFloat($sMin.val()));
        $(this).val(v);
        $iMax.val(v);
        updateFill();
      });

      // Text input → slider sync.
      $iMin.on("input change", function () {
        var v = Math.max(
          globalMin,
          Math.min(
            parseFloat($(this).val()) || globalMin,
            parseFloat($iMax.val()),
          ),
        );
        $(this).val(v);
        $sMin.val(v);
        updateFill();
      });

      $iMax.on("input change", function () {
        var v = Math.min(
          globalMax,
          Math.max(
            parseFloat($(this).val()) || globalMax,
            parseFloat($iMin.val()),
          ),
        );
        $(this).val(v);
        $sMax.val(v);
        updateFill();
      });

      // Trigger filter refresh after slider is released.
      $sMin.add($sMax).on("change", function () {
        $wrap.data("is-dirty", true); // user explicitly moved the slider
        self.onFilterChange($wrap.closest(".wcaf-filter"));
      });

      // Trigger filter on text-input blur.
      $iMin.add($iMax).on("change", function () {
        $wrap.data("is-dirty", true); // user explicitly typed a price
        self.onFilterChange($wrap.closest(".wcaf-filter"));
      });

      updateFill();
    },

    // =====================================================================
    // Filter change coordination
    // =====================================================================

    /**
     * Called whenever any filter input changes.
     * Debounces the refresh for the associated product lists and updates URL.
     *
     * @param {jQuery} $filter The .wcaf-filter element that changed.
     */
    onFilterChange: function ($filter) {
      var filterId = $filter.data("filter-id");
      this.debouncedRefreshForFilter(filterId);
      this.updateUrl();
      this.updateFilterCountBadges();
      this.renderActiveFilterTags();
    },

    /**
     * Debounce refresh calls so rapid UI changes result in a single request.
     *
     * @param {string} filterId The filter ID that changed.
     */
    debouncedRefreshForFilter: function (filterId) {
      var self = this;
      if (this.debounceTimers[filterId]) {
        clearTimeout(this.debounceTimers[filterId]);
      }
      this.debounceTimers[filterId] = setTimeout(function () {
        self.refreshListsForFilter(filterId);
      }, self.DEBOUNCE_MS);
    },

    /**
     * Find every product list that is connected to the given filter ID and
     * trigger a refresh on each.
     *
     * A list with an empty data-filter-ids attribute is treated as
     * "connected to all filters" so simple single-filter setups work
     * without having to wire up IDs explicitly.
     *
     * @param {string} filterId The filter ID that changed.
     */
    refreshListsForFilter: function (filterId) {
      var self = this;
      // Always compare as a string — jQuery auto-casts numeric data attributes.
      var filterIdStr = String(filterId);
      $(".wcaf-product-list").each(function () {
        var $list = $(this);
        var filterIds = self.parseFilterIds($list);
        // filterIds.length === 0  → no explicit connection → update for any filter.
        // filterIds.indexOf(...)  → explicit connection matches this filter.
        if (filterIds.length === 0 || filterIds.indexOf(filterIdStr) !== -1) {
          $list.data("page", 1);
          self.refreshList($list);
        }
      });
    },

    // =====================================================================
    // AJAX refresh
    // =====================================================================

    /**
     * Send an AJAX request to refresh a single product list.
     *
     * @param {jQuery} $list The .wcaf-product-list element.
     */
    refreshList: function ($list) {
      var self = this;
      var listId = $list.data("list-id");
      var filterIds = this.parseFilterIds($list);
      var filters = this.collectFilters(filterIds);

      // Merge cat_ids from the product list element itself (set via the
      // [wc_product_list cat_ids="..."] attribute).  These take precedence over
      // (or are merged with) any cat_ids already gathered from filter widgets.
      var listCatIdsRaw = $list.attr("data-cat-ids") || "";
      if (listCatIdsRaw.trim() !== "") {
        var listCatIds = listCatIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
        if (listCatIds.length) {
          if (Array.isArray(filters.cat_ids) && filters.cat_ids.length) {
            // Keep only IDs present in both sets (intersection).
            filters.cat_ids = filters.cat_ids.filter(function (id) {
              return listCatIds.indexOf(id) !== -1;
            });
            if (!filters.cat_ids.length) {
              filters.cat_ids = listCatIds;
            }
          } else {
            filters.cat_ids = listCatIds;
          }
        }
      }

      var perPage = parseInt($list.data("per-page"), 10) || 12;
      var page = parseInt($list.data("page"), 10) || 1;
      var orderby = $list.data("orderby") || "date";
      var order = $list.data("order") || "DESC";
      var columns = parseInt($list.data("columns"), 10) || 4;
      var tablet = parseInt($list.data("tablet"), 10) || 2;
      var mobile = parseInt($list.data("mobile"), 10) || 1;

      // Abort any in-flight request for this list.
      if (this.activeXhr[listId]) {
        this.activeXhr[listId].abort();
      }

      this.showLoading($list);

      this.activeXhr[listId] = $.ajax({
        url: wcaf_params.ajax_url,
        type: "POST",
        data: {
          action: "wcaf_filter_products",
          nonce: wcaf_params.nonce,
          filters: filters,
          per_page: perPage,
          page: page,
          orderby: orderby,
          order: order,
          columns: columns,
          tablet: tablet,
          mobile: mobile,
        },
        success: function (response) {
          if (response.success) {
            self.applyResponse($list, response.data);
          } else {
            self.showError($list);
          }
        },
        error: function (xhr) {
          // 'abort' is expected — don't show error for aborted requests.
          if ("abort" !== xhr.statusText) {
            self.showError($list);
          }
        },
        complete: function () {
          self.hideLoading($list);
          delete self.activeXhr[listId];
        },
      });
    },

    /**
     * Apply the AJAX response to the product list DOM.
     *
     * @param {jQuery} $list The .wcaf-product-list element.
     * @param {Object} data  Parsed response.data.
     */
    applyResponse: function ($list, data) {
      $list.find(".wcaf-product-list__grid-wrap").html(data.html);
      $list.find(".wcaf-product-list__pagination").html(data.pagination);
      $list.find(".wcaf-product-list__results-count").text(data.count_text);
      $list.data("page", data.current_page);

      /**
       * Fires after a product list is updated via AJAX.
       * Useful for reinitialising third-party scripts (e.g. sliders, quick-view).
       *
       * @event wcaf:list_updated
       * @param {jQuery} $list The updated list element.
       * @param {Object} data  Response data.
       */
      $(document).trigger("wcaf:list_updated", [$list, data]);
    },

    // =====================================================================
    // Loading state
    // =====================================================================

    showLoading: function ($list) {
      $list.addClass("is-loading");
      $list.find(".wcaf-product-list__loading").attr("aria-hidden", "false");
    },

    hideLoading: function ($list) {
      $list.removeClass("is-loading");
      $list.find(".wcaf-product-list__loading").attr("aria-hidden", "true");
    },

    showError: function ($list) {
      $list
        .find(".wcaf-product-list__grid-wrap")
        .html(
          '<div class="wcaf-error"><p>' + wcaf_params.i18n.error + "</p></div>",
        );
    },

    // =====================================================================
    // Filter value collection
    // =====================================================================

    /**
     * Collect all active filter values for a set of filter IDs,
     * merging results from each filter widget.
     *
     * When filterIds is empty (the product list has no explicit filter_ids
     * configured), every .wcaf-filter element on the page is collected so
     * that the implicit "connect to all" behaviour actually sends the correct
     * values in the AJAX request.
     *
     * @param  {string[]} filterIds Array of filter element IDs.
     * @return {Object}             Combined filter values.
     */
    collectFilters: function (filterIds) {
      var combined = {};

      // Determine which filter elements to read from.
      var $filters;
      if (!filterIds || filterIds.length === 0) {
        // No explicit connection — collect from every filter on the page.
        $filters = $(".wcaf-filter");
      } else {
        // Collect only from the explicitly listed filter widgets.
        var selector = filterIds
          .filter(Boolean)
          .map(function (id) {
            return "#" + id;
          })
          .join(",");
        $filters = selector ? $(selector) : $();
      }

      $filters.each(function () {
        var vals = WCAF.collectSingleFilter($(this));
        $.each(vals, function (key, val) {
          if (Array.isArray(val)) {
            if (!combined[key]) {
              combined[key] = [];
            }
            combined[key] = combined[key].concat(val);
          } else {
            combined[key] = val;
          }
        });
      });

      return combined;
    },

    /**
     * Collect active values from a single .wcaf-filter element.
     *
     * @param  {jQuery} $filter The filter element.
     * @return {Object}         Map of filter-key → value(s).
     */
    collectSingleFilter: function ($filter) {
      var values = {};

      // Checked checkboxes → array.
      $filter
        .find('.wcaf-filter__input[type="checkbox"]:checked')
        .each(function () {
          var key = $(this).data("filter-key");
          if (!values[key]) {
            values[key] = [];
          }
          values[key].push($(this).val());
        });

      // Checked radio → scalar.
      $filter
        .find('.wcaf-filter__input[type="radio"]:checked')
        .each(function () {
          values[$(this).data("filter-key")] = $(this).val();
        });

      // Dropdowns (multiple) → array.
      $filter
        .find(".wcaf-filter__select:not(.wcaf-filter__price-range-select)")
        .each(function () {
          var sel = $(this).val();
          // Single-select returns a string; multi-select returns an array.
          // Skip empty string ("Tất cả" / "All" option) and empty array.
          if (
            Array.isArray(sel) ? sel.length > 0 : sel !== "" && sel !== null
          ) {
            values[$(this).data("filter-key")] = sel;
          }
        });

      // Hidden inputs (tab value, price-dropdown parsed values).
      $filter.find('.wcaf-filter__input[type="hidden"]').each(function () {
        var key = $(this).data("filter-key");
        var val = $(this).val();
        if (key && val !== "") {
          values[key] = val;
        }
      });

      // Price-range slider — only send min/max when the user has explicitly
      // moved the slider or typed a value (is-dirty flag set by setupPriceSlider).
      // This prevents every category/brand AJAX request from carrying an unwanted
      // price BETWEEN clause using the slider's initial boundary values.
      $filter.find(".wcaf-filter__price-range").each(function () {
        if (!$(this).data("is-dirty")) {
          return; // untouched slider — skip price filter
        }
        var minVal = $(this).find(".wcaf-filter__price-min").val();
        var maxVal = $(this).find(".wcaf-filter__price-max").val();
        if (minVal !== "") {
          values.min_price = minVal;
        }
        if (maxVal !== "") {
          values.max_price = maxVal;
        }
      });

      // Cat IDs restriction — read from data attribute and include in every AJAX
      // request so the query builder can restrict products to that category subtree.
      var catIdsRaw = $filter.attr("data-cat-ids");
      if (catIdsRaw && catIdsRaw.trim() !== "") {
        values.cat_ids = catIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
      }

      return values;
    },

    // =====================================================================
    // URL state (shareable links)
    // =====================================================================

    /**
     * Push the current filter state into the browser history as query params
     * so users can copy/share the URL and land on the same filtered view.
     */
    updateUrl: function () {
      if (!window.history || !window.history.pushState) {
        return;
      }

      var params = new URLSearchParams();

      $(".wcaf-filter").each(function () {
        var $filter = $(this);
        var filterId = $filter.data("filter-id");
        var vals = WCAF.collectSingleFilter($filter);

        $.each(vals, function (key, val) {
          if (Array.isArray(val)) {
            val.forEach(function (v) {
              params.append(filterId + "[" + key + "][]", v);
            });
          } else if (val !== "") {
            params.set(filterId + "[" + key + "]", val);
          }
        });
      });

      var qs = params.toString();
      var newUrl = window.location.pathname + (qs ? "?" + qs : "");
      window.history.pushState({}, "", newUrl);
    },

    /**
     * On page load, read query params and pre-populate filter inputs,
     * then kick off a refresh of every connected product list.
     */
    restoreFromUrl: function () {
      var search = window.location.search;
      if (!search) {
        return;
      }

      var params = new URLSearchParams(search);
      var hasValues = false;

      $(".wcaf-filter").each(function () {
        var $filter = $(this);
        var filterId = $filter.data("filter-id");

        params.forEach(function (value, paramKey) {
          // Match filterId[key][]  (multi-value).
          var multiMatch = paramKey.match(/^(.+)\[([^\]]+)\]\[\]$/);
          // Match filterId[key]    (single value).
          var singleMatch = !multiMatch && paramKey.match(/^(.+)\[([^\]]+)\]$/);

          if (multiMatch && multiMatch[1] === filterId) {
            var key = multiMatch[2];
            $filter
              .find('[data-filter-key="' + key + '"][value="' + value + '"]')
              .prop("checked", true)
              .trigger("change");
            hasValues = true;
          } else if (singleMatch && singleMatch[1] === filterId) {
            var sKey = singleMatch[2];
            // Number inputs + hidden inputs.
            $filter
              .find(
                '[data-filter-key="' +
                  sKey +
                  '"][type="number"],' +
                  '[data-filter-key="' +
                  sKey +
                  '"][type="hidden"]',
              )
              .val(value);
            hasValues = true;
          }
        });
      });

      if (hasValues) {
        // Re-sync slider fill bars.
        $(".wcaf-filter__price-range").each(function () {
          var $range = $(this);
          var globalMin = parseFloat($range.data("min")) || 0;
          var globalMax = parseFloat($range.data("max")) || 0;
          var $iMin = $range.find(".wcaf-filter__price-min");
          var $iMax = $range.find(".wcaf-filter__price-max");
          var $sMin = $range.find(".wcaf-filter__range--min");
          var $sMax = $range.find(".wcaf-filter__range--max");
          // Mark dirty so collectSingleFilter includes the restored price.
          if (
            parseFloat($iMin.val()) !== globalMin ||
            parseFloat($iMax.val()) !== globalMax
          ) {
            $range.data("is-dirty", true);
          }
          $sMin.val($iMin.val());
          $sMax.val($iMax.val());
          $sMin.trigger("input");
        });

        // Refresh all product lists.
        $(".wcaf-product-list").each(function () {
          WCAF.refreshList($(this));
        });
      }
    },

    // =====================================================================
    // Filter count badge  [wc_filter_count]
    // =====================================================================

    /**
     * Count the number of active (non-empty) filter values in the given
     * set of filter widgets and return the total.
     *
     * Rules:
     *  - Each checked checkbox   → +1
     *  - A selected radio (non-empty value) → +1
     *  - A dropdown with a non-empty selection → +1 per selected option
     *  - Price range when dirty  → +1
     *
     * @param  {string[]} filterIds Filter element IDs to inspect.
     *                              Empty array = all .wcaf-filter on the page.
     * @return {number}             Total active filter count.
     */
    countActiveFilters: function (filterIds) {
      var total = 0;
      var $filters;

      if (!filterIds || filterIds.length === 0) {
        $filters = $(".wcaf-filter");
      } else {
        var selector = filterIds
          .filter(Boolean)
          .map(function (id) {
            return "#" + id;
          })
          .join(",");
        $filters = selector ? $(selector) : $();
      }

      $filters.each(function () {
        var $f = $(this);

        // Checked checkboxes.
        total += $f.find('.wcaf-filter__input[type="checkbox"]:checked').length;

        // Checked radio with a non-empty value.
        $f.find('.wcaf-filter__input[type="radio"]:checked').each(function () {
          if ($(this).val() !== "") {
            total += 1;
          }
        });

        // Dropdowns (taxonomy — exclude price-range selects).
        $f.find(
          ".wcaf-filter__select:not(.wcaf-filter__price-range-select)",
        ).each(function () {
          var sel = $(this).val();
          if (Array.isArray(sel)) {
            total += sel.filter(Boolean).length;
          } else if (sel !== "" && sel !== null) {
            total += 1;
          }
        });

        // Price range — count as 1 when the user has explicitly interacted.
        $f.find(".wcaf-filter__price-range").each(function () {
          if ($(this).data("is-dirty")) {
            total += 1;
          }
        });

        // Price dropdown — count as 1 when a range is selected.
        $f.find(".wcaf-filter__price-range-select").each(function () {
          if ($(this).val() !== "" && $(this).val() !== null) {
            total += 1;
          }
        });

        // Tab UI — count as 1 when a non-"all" (non-empty) tab is active.
        $f.find(".wcaf-filter__tabs").each(function () {
          var $active = $(this).find(".wcaf-filter__tab.is-active");
          var val = $active.data("value");
          if (val !== undefined && val !== null && String(val) !== "") {
            total += 1;
          }
        });
      });

      return total;
    },

    /**
     * Update every [wc_filter_count] badge on the page.
     * Each badge can be scoped to a specific subset of filters via its
     * data-filter-ids attribute.
     */
    updateFilterCountBadges: function () {
      $(".wcaf-filter-count").each(function () {
        var $badge = $(this);
        var filterIds = String($badge.data("filter-ids") || "")
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);

        var count = WCAF.countActiveFilters(filterIds);
        $badge.text(count);

        // Toggle a visual modifier so themes can hide/style the badge at zero.
        $badge.toggleClass("is-empty", count === 0);

        // Optional zero text override.
        var zeroText = $badge.data("zero-text") || "";
        if (count === 0 && zeroText !== "") {
          $badge.text(zeroText);
        }
      });

      // Show/hide standalone [wc_clear_filter] buttons based on active count.
      // Use a CSS class instead of .show()/.hide() so the inline style from
      // server-rendered HTML never interferes.
      $(".wcaf-clear-filter").each(function () {
        var $btn = $(this);
        var filterIds = String($btn.data("filter-ids") || "")
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
        var count = WCAF.countActiveFilters(filterIds);
        $btn.toggleClass("is-active", count > 0);
      });
    },

    // =====================================================================
    // Clear filter button  [wc_clear_filter]
    // =====================================================================

    /**
     * Bind click handler for every [wc_clear_filter] button on the page.
     * Uses event delegation so dynamically inserted buttons work too.
     */
    bindClearFilterButtons: function () {
      var self = this;
      $(document).on("click", ".wcaf-clear-filter", function () {
        var $btn = $(this);
        var filterIds = String($btn.data("filter-ids") || "")
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);

        self.clearFilters(filterIds);
      });
    },

    /**
     * Reset all filter inputs in the specified (or all) filter widgets, then
     * trigger a product-list refresh and update URL + count badges.
     *
     * @param {string[]} filterIds Filter element IDs to clear.
     *                             Empty array = clear all filters on the page.
     */
    clearFilters: function (filterIds) {
      var self = this;
      var $filters;

      if (!filterIds || filterIds.length === 0) {
        $filters = $(".wcaf-filter");
      } else {
        var selector = filterIds
          .filter(Boolean)
          .map(function (id) {
            return "#" + id;
          })
          .join(",");
        $filters = selector ? $(selector) : $();
        // If no element matched the given IDs, fall back to ALL filters.
        if ($filters.length === 0) {
          $filters = $(".wcaf-filter");
        }
      }

      // 1. Reset all filter inputs.
      $filters.each(function () {
        var $f = $(this);

        $f.find('.wcaf-filter__input[type="checkbox"]:checked').prop(
          "checked",
          false,
        );

        $f.find('.wcaf-filter__input[type="radio"]').prop("checked", false);

        $f.find(
          ".wcaf-filter__select:not(.wcaf-filter__price-range-select)",
        ).each(function () {
          $(this).val($(this).find("option:first").val());
        });

        $f.find(".wcaf-filter__price-range").each(function () {
          var $range = $(this);
          var globalMin = parseFloat($range.data("min")) || 0;
          var globalMax = parseFloat($range.data("max")) || 0;
          $range.find(".wcaf-filter__range--min").val(globalMin);
          $range.find(".wcaf-filter__range--max").val(globalMax);
          $range.find(".wcaf-filter__price-min").val(globalMin);
          $range.find(".wcaf-filter__price-max").val(globalMax);
          $range.removeData("is-dirty");
          $range.find(".wcaf-filter__range--min").trigger("input");
        });

        $f.find(".wcaf-filter__price-range-select").val("");
        $f.find('[data-filter-key="min_price"]').val("");
        $f.find('[data-filter-key="max_price"]').val("");

        $f.find(".wcaf-filter__tabs").each(function () {
          var $tabs = $(this);
          $tabs
            .find(".wcaf-filter__tab")
            .removeClass("is-active")
            .attr("aria-selected", "false");
          $tabs
            .find(".wcaf-filter__tab:first")
            .addClass("is-active")
            .attr("aria-selected", "true");
          $tabs
            .find(".wcaf-filter__tab-value")
            .val($tabs.find(".wcaf-filter__tab:first").data("value") || "");
        });
      });

      // 2. Fire the standard onFilterChange pipeline for every cleared filter.
      //    This is the exact same path used by normal checkbox/dropdown changes,
      //    so it is guaranteed to update URL, badges, chips, and trigger the
      //    AJAX product-list refresh correctly.
      $filters.each(function () {
        self.onFilterChange($(this));
      });
    },

    // =====================================================================
    // Active filter chips  [wc_active_filters]
    // =====================================================================

    /**
     * Build an array of active-filter tag descriptors from the given filters.
     *
     * @param  {string[]} filterIds Filter element IDs to inspect. Empty = all.
     * @return {Array}              Array of tag objects.
     */
    buildActiveFilterTags: function (filterIds) {
      var tags = [];
      var $filters;

      if (!filterIds || filterIds.length === 0) {
        $filters = $(".wcaf-filter");
      } else {
        var selector = filterIds
          .filter(Boolean)
          .map(function (id) {
            return "#" + id;
          })
          .join(",");
        $filters = selector ? $(selector) : $();
      }

      $filters.each(function () {
        var $f = $(this);
        var filterId = String($f.data("filter-id") || "");
        var filterTitle = $f.find(".wcaf-filter__title").first().text().trim();

        // — Checkboxes —
        $f.find('.wcaf-filter__input[type="checkbox"]:checked').each(
          function () {
            var $inp = $(this);
            var label = $inp
              .closest(".wcaf-filter__label")
              .find(".wcaf-filter__label-text")
              .text()
              .trim();
            tags.push({
              filterId: filterId,
              filterTitle: filterTitle,
              key: $inp.data("filter-key"),
              value: $inp.val(),
              displayText: label || $inp.val(),
              type: "checkbox",
              $input: $inp,
            });
          },
        );

        // — Radios —
        $f.find('.wcaf-filter__input[type="radio"]:checked').each(function () {
          var $inp = $(this);
          if ($inp.val() === "") return; // "all" radio — skip
          var label = $inp
            .closest(".wcaf-filter__label")
            .find(".wcaf-filter__label-text")
            .text()
            .trim();
          tags.push({
            filterId: filterId,
            filterTitle: filterTitle,
            key: $inp.data("filter-key"),
            value: $inp.val(),
            displayText: label || $inp.val(),
            type: "radio",
            $input: $inp,
          });
        });

        // — Taxonomy dropdowns (non-price) —
        $f.find(
          ".wcaf-filter__select:not(.wcaf-filter__price-range-select)",
        ).each(function () {
          var $sel = $(this);
          var val = $sel.val();
          if (!val || (Array.isArray(val) && val.length === 0)) return;
          var vals = Array.isArray(val) ? val : [val];
          vals.forEach(function (v) {
            if (!v) return;
            var text = $sel
              .find('option[value="' + v + '"]')
              .text()
              .replace(/\s*\(\d+\)\s*$/, "")
              .trim();
            tags.push({
              filterId: filterId,
              filterTitle: filterTitle,
              key: $sel.data("filter-key"),
              value: v,
              displayText: text || v,
              type: "select",
              $input: $sel,
            });
          });
        });

        // — Tabs —
        $f.find(".wcaf-filter__tabs").each(function () {
          var $tabs = $(this);
          var $active = $tabs.find(".wcaf-filter__tab.is-active");
          var val = $active.data("value");
          if (val === "" || val === null || val === undefined) return;
          tags.push({
            filterId: filterId,
            filterTitle: filterTitle,
            key: $tabs.find(".wcaf-filter__tab-value").data("filter-key"),
            value: String(val),
            displayText: $active.text().trim() || String(val),
            type: "tab",
            $tabs: $tabs,
          });
        });

        // — Price range slider (dirty only) —
        $f.find(".wcaf-filter__price-range").each(function () {
          var $range = $(this);
          if (!$range.data("is-dirty")) return;
          var min = $range.find(".wcaf-filter__price-min").val();
          var max = $range.find(".wcaf-filter__price-max").val();
          tags.push({
            filterId: filterId,
            filterTitle: filterTitle,
            key: "price_range",
            value: min + ":" + max,
            displayText: min + " \u2013 " + max,
            type: "price_range",
            $range: $range,
          });
        });

        // — Price dropdown —
        $f.find(".wcaf-filter__price-range-select").each(function () {
          var $sel = $(this);
          var val = $sel.val();
          if (!val) return;
          var text = $sel.find("option:selected").text().trim();
          tags.push({
            filterId: filterId,
            filterTitle: filterTitle,
            key: "price_dropdown",
            value: val,
            displayText: text || val,
            type: "price_dropdown",
            $input: $sel,
          });
        });
      });

      return tags;
    },

    /**
     * Update every [wc_active_filters] container on the page.
     */
    renderActiveFilterTags: function () {
      var self = this;

      $(".wcaf-active-filters").each(function () {
        var $container = $(this);
        var filterIds = String($container.data("filter-ids") || "")
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);

        var tags = self.buildActiveFilterTags(filterIds);
        var $tagsEl = $container.find(".wcaf-active-filters__tags");
        var $emptyEl = $container.find(".wcaf-active-filters__empty");
        var $clearEl = $container.find(".wcaf-active-filters__clear-all");

        $tagsEl.empty();

        if (tags.length === 0) {
          $emptyEl.show();
          $clearEl.hide();
          $container.addClass("is-empty");
        } else {
          $emptyEl.hide();
          $clearEl.show();
          $container.removeClass("is-empty");

          tags.forEach(function (tag) {
            var $chip = $("<span>", { class: "wcaf-active-filters__tag" });
            var $text = $("<span>", {
              class: "wcaf-active-filters__tag-text",
              text: tag.displayText,
            });
            var $remove = $("<button>", {
              type: "button",
              class: "wcaf-active-filters__tag-remove",
              "aria-label": "Xo\u00e1 " + tag.displayText,
            }).html("&times;");

            $remove.data("wcaf-tag", tag);
            $chip.append($text).append($remove);
            $tagsEl.append($chip);
          });
        }
      });
    },

    /**
     * Bind delegated click events for [wc_active_filters] interactions.
     */
    bindActiveFilterEvents: function () {
      var self = this;

      // Remove a single chip.
      $(document).on("click", ".wcaf-active-filters__tag-remove", function () {
        var tag = $(this).data("wcaf-tag");
        if (tag) {
          self.removeFilterTag(tag);
        }
      });

      // "Clear all" inside the active-filters widget.
      $(document).on("click", ".wcaf-active-filters__clear-all", function () {
        var $container = $(this).closest(".wcaf-active-filters");
        var filterIds = String($container.data("filter-ids") || "")
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
        self.clearFilters(filterIds);
      });
    },

    /**
     * Remove a single active-filter tag and trigger a refresh.
     *
     * @param {Object} tag Tag descriptor built by buildActiveFilterTags.
     */
    removeFilterTag: function (tag) {
      var self = this;
      var $filter = $("#" + tag.filterId);
      if (!$filter.length) return;

      switch (tag.type) {
        case "checkbox":
          tag.$input.prop("checked", false);
          break;

        case "radio":
          tag.$input.prop("checked", false);
          break;

        case "select":
          var $sel = tag.$input;
          var current = $sel.val();
          if (Array.isArray(current)) {
            var updated = current.filter(function (v) {
              return v !== tag.value;
            });
            $sel.val(updated.length ? updated : "");
          } else {
            $sel.val("");
          }
          break;

        case "tab":
          // Click the "all" tab (data-value="") to reset.
          tag.$tabs.find('.wcaf-filter__tab[data-value=""]').trigger("click");
          return; // click fires onFilterChange — exit early

        case "price_range":
          var $range = tag.$range;
          var gMin = parseFloat($range.data("min")) || 0;
          var gMax = parseFloat($range.data("max")) || 0;
          $range.find(".wcaf-filter__range--min").val(gMin);
          $range.find(".wcaf-filter__range--max").val(gMax);
          $range.find(".wcaf-filter__price-min").val(gMin);
          $range.find(".wcaf-filter__price-max").val(gMax);
          $range.removeData("is-dirty");
          $range.find(".wcaf-filter__range--min").trigger("input");
          break;

        case "price_dropdown":
          tag.$input.val("");
          $filter.find('[data-filter-key="min_price"]').val("");
          $filter.find('[data-filter-key="max_price"]').val("");
          break;
      }

      // Route through the normal filter-change path so URL, badges, chips,
      // debounce, and AJAX are all handled consistently.
      self.onFilterChange($filter);
    },

    // =====================================================================
    // Utilities
    // =====================================================================

    /**
     * Parse the comma-separated filter-ids data attribute into an array.
     *
     * @param  {jQuery}   $list The product list element.
     * @return {string[]}       Array of filter IDs.
     */
    parseFilterIds: function ($list) {
      var raw = $list.data("filter-ids") || "";
      return String(raw)
        .split(",")
        .map(function (s) {
          return s.trim();
        })
        .filter(Boolean);
    },
  }; // END WCAF

  // =========================================================================
  // Bootstrap
  // =========================================================================

  $(document).ready(function () {
    WCAF.init();
  });
})(jQuery);
