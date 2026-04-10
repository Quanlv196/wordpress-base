/**
 * Post AJAX Filter — Frontend JavaScript
 *
 * Nhiệm vụ:
 *  - Thu thập giá trị từ mọi widget [post_filter] trên trang
 *  - Debounce các thay đổi và gửi một yêu cầu AJAX duy nhất cho mỗi danh sách bài viết
 *  - Thay thế HTML lưới, phân trang và số kết quả tại chỗ
 *  - Đồng bộ URL trình duyệt (bộ lọc có thể chia sẻ qua URL)
 *  - Khôi phục trạng thái bộ lọc từ URL khi tải trang
 *
 * Yêu cầu: jQuery (đi kèm với WordPress)
 * Global:  paf_params  { ajax_url, nonce, i18n }
 *
 * @package Post_Ajax_Filter
 */

/* global paf_params, jQuery */

(function ($) {
  "use strict";

  // =========================================================================
  // Namespace PAF
  // =========================================================================

  var PAF = {
    /** Timer debounce theo bộ lọc ID. */
    debounceTimers: {},

    /** XHR đang chạy theo list ID — hủy request cũ khi có request mới. */
    activeXhr: {},

    /** Độ trễ debounce tính bằng mili giây. */
    DEBOUNCE_MS: 400,

    // =====================================================================
    // Khởi tạo
    // =====================================================================

    init: function () {
      this.bindFilterEvents();
      this.bindListControls();
      this.restoreFromUrl();
    },

    // =====================================================================
    // Gắn sự kiện bộ lọc
    // =====================================================================

    bindFilterEvents: function () {
      var self = this;

      // Checkbox / radio.
      $(document).on(
        "change",
        '.paf-filter__input[type="checkbox"], .paf-filter__input[type="radio"]',
        function () {
          self.onFilterChange($(this).closest(".paf-filter"));
        },
      );

      // Dropdown taxonomy.
      $(document).on("change", ".paf-filter__select", function () {
        self.onFilterChange($(this).closest(".paf-filter"));
      });

      // Tabs.
      $(document).on("click", ".paf-filter__tab", function () {
        var $tab = $(this);
        var $tabs = $tab.closest(".paf-filter__tabs");

        $tabs
          .find(".paf-filter__tab")
          .removeClass("is-active")
          .attr("aria-selected", "false");

        $tab.addClass("is-active").attr("aria-selected", "true");

        // Đồng bộ giá trị lên hidden input.
        $tabs.find(".paf-filter__tab-value").val($tab.data("value"));

        self.onFilterChange($tab.closest(".paf-filter"));
      });

      // Ô tìm kiếm — debounce riêng (dài hơn để tránh gửi quá nhiều request).
      $(document).on("input", ".paf-filter__search", function () {
        var $filter = $(this).closest(".paf-filter");
        var filterId = $filter.data("filter-id");

        if (self.debounceTimers["search_" + filterId]) {
          clearTimeout(self.debounceTimers["search_" + filterId]);
        }
        self.debounceTimers["search_" + filterId] = setTimeout(function () {
          self.onFilterChange($filter);
        }, 600);
      });
    },

    // =====================================================================
    // Điều khiển danh sách bài viết (sắp xếp, phân trang)
    // =====================================================================

    bindListControls: function () {
      var self = this;

      // Thay đổi sắp xếp.
      $(document).on("change", ".paf-post-list__orderby", function () {
        var parts = String($(this).val()).split(":");
        var $list = $(this).closest(".paf-post-list");
        $list.data("orderby", parts[0] || "date");
        $list.data("order", parts[1] || "DESC");
        $list.data("page", 1);
        self.refreshList($list);
      });

      // Nút phân trang.
      $(document).on("click", ".paf-pagination__btn", function () {
        var $list = $(this).closest(".paf-post-list");
        $list.data("page", $(this).data("page"));
        self.refreshList($list);

        // Cuộn lên đầu danh sách với lề nhỏ.
        $("html, body").animate({ scrollTop: $list.offset().top - 80 }, 300);
      });
    },

    // =====================================================================
    // Phối hợp thay đổi bộ lọc
    // =====================================================================

    /**
     * Được gọi mỗi khi bất kỳ ô lọc nào thay đổi.
     *
     * @param {jQuery} $filter Element .paf-filter đã thay đổi.
     */
    onFilterChange: function ($filter) {
      var filterId = $filter.data("filter-id");
      this.debouncedRefreshForFilter(filterId);
      this.updateUrl();
    },

    /**
     * Debounce gọi refresh để tổng hợp các thay đổi UI nhanh thành một request.
     *
     * @param {string} filterId ID bộ lọc đã thay đổi.
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
     * Tìm mọi danh sách bài viết liên kết với filter ID và refresh từng cái.
     *
     * Danh sách không có data-filter-ids sẽ được coi là "liên kết tất cả".
     *
     * @param {string} filterId ID bộ lọc đã thay đổi.
     */
    refreshListsForFilter: function (filterId) {
      var self = this;
      var filterIdStr = String(filterId);

      $(".paf-post-list").each(function () {
        var $list = $(this);
        var filterIds = self.parseFilterIds($list);

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
     * Gửi request AJAX để refresh một danh sách bài viết.
     *
     * @param {jQuery} $list Element .paf-post-list cần refresh.
     */
    refreshList: function ($list) {
      var self = this;
      var listId = $list.data("list-id");
      var filterIds = this.parseFilterIds($list);
      var filters = this.collectFilters(filterIds);

      // Gộp cat_ids từ thuộc tính của danh sách.
      var listCatIdsRaw = $list.attr("data-cat-ids") || "";
      if (listCatIdsRaw.trim() !== "") {
        var listCatIds = listCatIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
        if (listCatIds.length) {
          filters.cat_ids = listCatIds;
        }
      }

      // Gộp tag_ids từ thuộc tính của danh sách.
      var listTagIdsRaw = $list.attr("data-tag-ids") || "";
      if (listTagIdsRaw.trim() !== "") {
        var listTagIds = listTagIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
        if (listTagIds.length) {
          filters.tag_ids = listTagIds;
        }
      }

      var perPage = parseInt($list.data("per-page"), 10) || 9;
      var page = parseInt($list.data("page"), 10) || 1;
      var orderby = $list.data("orderby") || "date";
      var order = $list.data("order") || "DESC";

      // Đọc cấu hình Flatsome từ data-config JSON.
      var listConfig = {};
      try {
        listConfig = JSON.parse($list.attr("data-config") || "{}");
      } catch (e) {
        listConfig = {};
      }
      var columns = parseInt(listConfig.columns, 10) || 3;
      var tablet = parseInt(listConfig.tablet, 10) || 2;
      var mobile = parseInt(listConfig.mobile, 10) || 1;

      // Hủy request cũ cho danh sách này.
      if (this.activeXhr[listId]) {
        this.activeXhr[listId].abort();
      }

      this.showLoading($list);

      this.activeXhr[listId] = $.ajax({
        url: paf_params.ajax_url,
        type: "POST",
        data: $.extend(
          {
            action: "paf_filter_posts",
            nonce: paf_params.nonce,
            filters: filters,
            per_page: perPage,
            page: page,
            orderby: orderby,
            order: order,
            columns: columns,
            tablet: tablet,
            mobile: mobile,
          },
          {
            style: listConfig.style || "",
            show_date: listConfig.show_date || "badge",
            show_category: listConfig.show_category || "false",
            excerpt: listConfig.excerpt || "visible",
            excerpt_length: listConfig.excerpt_length || 15,
            image_height: listConfig.image_height || "56%",
            image_size: listConfig.image_size || "medium",
            text_align: listConfig.text_align || "center",
            readmore: listConfig.readmore || "",
            readmore_style: listConfig.readmore_style || "outline",
            readmore_size: listConfig.readmore_size || "small",
          },
        ),
        success: function (response) {
          if (response.success) {
            self.applyResponse($list, response.data);
          } else {
            self.showError($list);
          }
        },
        error: function (xhr) {
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
     * Áp dụng phản hồi AJAX vào DOM danh sách bài viết.
     *
     * @param {jQuery} $list Element .paf-post-list.
     * @param {Object} data  response.data đã parse.
     */
    applyResponse: function ($list, data) {
      $list.find(".paf-post-list__grid-wrap").html(data.html);
      $list.find(".paf-post-list__pagination").html(data.pagination);
      $list.find(".paf-post-list__results-count").text(data.count_text);
      $list.data("page", data.current_page);

      /**
       * Fires sau khi danh sách bài viết được cập nhật qua AJAX.
       *
       * @event paf:list_updated
       * @param {jQuery} $list Element danh sách đã cập nhật.
       * @param {Object} data  Dữ liệu response.
       */
      $(document).trigger("paf:list_updated", [$list, data]);
    },

    // =====================================================================
    // Trạng thái loading
    // =====================================================================

    showLoading: function ($list) {
      $list.addClass("is-loading");
      $list.find(".paf-post-list__loading").attr("aria-hidden", "false");
    },

    hideLoading: function ($list) {
      $list.removeClass("is-loading");
      $list.find(".paf-post-list__loading").attr("aria-hidden", "true");
    },

    showError: function ($list) {
      $list
        .find(".paf-post-list__grid-wrap")
        .html(
          '<div class="paf-error"><p>' + paf_params.i18n.error + "</p></div>",
        );
    },

    // =====================================================================
    // Thu thập giá trị bộ lọc
    // =====================================================================

    /**
     * Thu thập tất cả giá trị bộ lọc đang hoạt động cho một tập hợp filter IDs.
     *
     * @param  {string[]} filterIds Mảng ID element bộ lọc.
     * @return {Object}             Giá trị bộ lọc kết hợp.
     */
    collectFilters: function (filterIds) {
      var combined = {};
      var $filters;

      if (!filterIds || filterIds.length === 0) {
        $filters = $(".paf-filter");
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
        var vals = PAF.collectSingleFilter($(this));
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
     * Thu thập giá trị từ một element .paf-filter đơn.
     *
     * @param  {jQuery} $filter Element bộ lọc.
     * @return {Object}         Map khóa bộ lọc → giá trị.
     */
    collectSingleFilter: function ($filter) {
      var values = {};

      // Checkbox được chọn → mảng.
      $filter
        .find('.paf-filter__input[type="checkbox"]:checked')
        .each(function () {
          var key = $(this).data("filter-key");
          if (!values[key]) {
            values[key] = [];
          }
          values[key].push($(this).val());
        });

      // Radio được chọn → scalar.
      $filter
        .find('.paf-filter__input[type="radio"]:checked')
        .each(function () {
          values[$(this).data("filter-key")] = $(this).val();
        });

      // Dropdown → mảng hoặc scalar.
      $filter.find(".paf-filter__select").each(function () {
        var sel = $(this).val();
        if (Array.isArray(sel) ? sel.length > 0 : sel !== "" && sel !== null) {
          values[$(this).data("filter-key")] = sel;
        }
      });

      // Hidden input (giá trị tab).
      $filter.find('.paf-filter__input[type="hidden"]').each(function () {
        var key = $(this).data("filter-key");
        var val = $(this).val();
        if (key && val !== "") {
          values[key] = val;
        }
      });

      // Ô tìm kiếm.
      $filter.find(".paf-filter__search").each(function () {
        var val = $.trim($(this).val());
        if (val !== "") {
          values["search"] = val;
        }
      });

      // cat_ids giới hạn từ thuộc tính data.
      var catIdsRaw = $filter.attr("data-cat-ids");
      if (catIdsRaw && catIdsRaw.trim() !== "") {
        values.cat_ids = catIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
      }

      // tag_ids giới hạn từ thuộc tính data.
      var tagIdsRaw = $filter.attr("data-tag-ids");
      if (tagIdsRaw && tagIdsRaw.trim() !== "") {
        values.tag_ids = tagIdsRaw
          .split(",")
          .map(function (s) {
            return s.trim();
          })
          .filter(Boolean);
      }

      return values;
    },

    // =====================================================================
    // Trạng thái URL (link chia sẻ)
    // =====================================================================

    /**
     * Đẩy trạng thái bộ lọc hiện tại vào lịch sử trình duyệt dưới dạng query params.
     */
    updateUrl: function () {
      if (!window.history || !window.history.pushState) {
        return;
      }

      var params = new URLSearchParams();

      $(".paf-filter").each(function () {
        var $filter = $(this);
        var filterId = $filter.data("filter-id");
        var vals = PAF.collectSingleFilter($filter);

        $.each(vals, function (key, val) {
          // Bỏ qua cat_ids/tag_ids — đây là giới hạn cố định, không cần lưu URL.
          if (key === "cat_ids" || key === "tag_ids") {
            return;
          }
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
     * Khi tải trang, đọc query params và điền vào ô lọc,
     * sau đó refresh mọi danh sách bài viết liên kết.
     */
    restoreFromUrl: function () {
      var search = window.location.search;
      if (!search) {
        return;
      }

      var params = new URLSearchParams(search);
      var hasValues = false;

      $(".paf-filter").each(function () {
        var $filter = $(this);
        var filterId = $filter.data("filter-id");

        params.forEach(function (value, paramKey) {
          // Khớp filterId[key][]  (nhiều giá trị).
          var multiMatch = paramKey.match(/^(.+)\[([^\]]+)\]\[\]$/);
          // Khớp filterId[key]    (một giá trị).
          var singleMatch = !multiMatch && paramKey.match(/^(.+)\[([^\]]+)\]$/);

          if (multiMatch && multiMatch[1] === filterId) {
            var key = multiMatch[2];
            $filter
              .find('[data-filter-key="' + key + '"][value="' + value + '"]')
              .prop("checked", true);
            hasValues = true;
          } else if (singleMatch && singleMatch[1] === filterId) {
            var sKey = singleMatch[2];
            // Text / search inputs.
            $filter
              .find(
                '[data-filter-key="' +
                  sKey +
                  '"][type="search"],' +
                  '[data-filter-key="' +
                  sKey +
                  '"][type="hidden"]',
              )
              .val(value);
            // Dropdown.
            $filter
              .find('[data-filter-key="' + sKey + '"].paf-filter__select')
              .val(value);
            hasValues = true;
          }
        });
      });

      if (hasValues) {
        $(".paf-post-list").each(function () {
          PAF.refreshList($(this));
        });
      }
    },

    // =====================================================================
    // Tiện ích
    // =====================================================================

    /**
     * Parse thuộc tính data-filter-ids thành mảng.
     *
     * @param  {jQuery}   $list Element danh sách bài viết.
     * @return {string[]}       Mảng filter IDs.
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
  }; // END PAF

  // =========================================================================
  // Khởi động
  // =========================================================================

  $(document).ready(function () {
    PAF.init();
  });
})(jQuery);
