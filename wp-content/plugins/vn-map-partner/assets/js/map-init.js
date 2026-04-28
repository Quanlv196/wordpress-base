/**
 * VN Map Partner – map-init.js
 *
 * Luồng xử lý đúng cho simplemaps:
 *   1. countrymap.js tự động render bản đồ (auto-load) khi script được parse.
 *      map_div đã được override = "vn_map" qua inline script trong class-shortcode.php.
 *   2. map-init.js đăng ký hooks NGAY (simplemaps_countrymap đã có vì countrymap.js
 *      là dependency của script này trong WordPress).
 *   3. Fetch AJAX data bất đồng bộ.
 *   4. Khi data về: cập nhật state_specific → gọi .load() để re-render màu đối tác.
 *   5. Click tỉnh → hiển thị danh sách đối tác và đồng bộ dropdown.
 *
 * Depends: jQuery, select2, vnm-mapdata, vnm-countrymap, vnm-data-builder
 */
/* global jQuery, simplemaps_countrymap_mapdata, simplemaps_countrymap, VNM_DataBuilder, VNM_CONFIG */
(function ($, window) {
  "use strict";

  // ------------------------------------------------------------------
  // Trạng thái nội bộ
  // ------------------------------------------------------------------
  var _partnersData = {}; // Dữ liệu từ REST API { "CODE": { name, partners[] } }
  var _pendingStateId = null; // state được click trước khi data AJAX về

  // ------------------------------------------------------------------
  // VNM_Map – object chính
  // ------------------------------------------------------------------
  var VNM_Map = {
    /**
     * Điểm khởi đầu – gọi sau khi DOM ready.
     *
     * Ở thời điểm này countrymap.js đã được parse xong (dependency WP)
     * nên simplemaps_countrymap PHẢI có trong window (nếu file thật).
     * Bản đồ cũng đã auto-render với màu mặc định.
     * Nhiệm vụ ở đây: đăng ký hooks → fetch data → cập nhật màu.
     */
    init: function () {
      if ($("#vn_map").length === 0) {
        return; // Shortcode không có trên trang này
      }

      // BƯỚC 1: Đăng ký hooks ngay để không bỏ lỡ click của người dùng
      VNM_Map._attachHooks();

      // BƯỚC 2: Khởi tạo select2 + populate dropdown ngay từ danh sách tĩnh
      VNM_Map._initSelect();

      // BƯỚC 3: Fetch data bất đồng bộ
      VNM_DataBuilder.fetchPartners(function (err, data) {
        if (err || !data) {
          data = {};
          console.warn("[VNM] Không lấy được dữ liệu đối tác từ API.");
        }

        _partnersData = data;

        // Cập nhật màu sắc trên bản đồ (re-render)
        VNM_Map._refreshMapColors(data);

        // Nếu người dùng đã click tỉnh trước khi data về → xử lý ngay
        if (_pendingStateId) {
          VNM_DataBuilder.renderPartnerList(
            _partnersData[_pendingStateId] || null,
          );
          $("#vn_map_province_select")
            .val(_pendingStateId)
            .trigger("change.select2");
          _pendingStateId = null;
        }
      });
    },

    // ----------------------------------------------------------------
    // Đăng ký hooks lên simplemaps_countrymap
    // ----------------------------------------------------------------
    _attachHooks: function () {
      if (typeof window.simplemaps_countrymap === "undefined") {
        // countrymap.js chưa được thay bằng file thật
        console.warn(
          "[VNM] simplemaps_countrymap không tìm thấy.\n" +
            "→ Hãy copy file countrymap.js thật từ site gốc vào:\n" +
            "  wp-content/plugins/vn-map-partner/assets/js/countrymap.js",
        );
        return;
      }

      if (!window.simplemaps_countrymap.hooks) {
        window.simplemaps_countrymap.hooks = {};
      }

      // Hook click tỉnh
      window.simplemaps_countrymap.hooks.click = function (stateId) {
        VNM_Map._onProvinceClick(stateId);
      };

      // Render bản đồ lần đầu với màu mặc định ngay lập tức
      // (mapdata.js gốc có auto_load:"no" nên phải gọi thủ công)
      window.simplemaps_countrymap.load();
    },

    // ----------------------------------------------------------------
    // Cập nhật state_specific và re-render bản đồ với màu đối tác
    // ----------------------------------------------------------------
    _refreshMapColors: function (data) {
      if (typeof window.simplemaps_countrymap_mapdata === "undefined") {
        console.warn(
          "[VNM] simplemaps_countrymap_mapdata không tìm thấy. Kiểm tra mapdata.js.",
        );
        return;
      }

      // Merge state_specific: giữ dữ liệu tỉnh gốc, ghi đè màu cho tỉnh có đối tác
      var dynamicStates = VNM_DataBuilder.buildStateSpecific(data);
      window.simplemaps_countrymap_mapdata.state_specific = $.extend(
        true,
        {},
        window.simplemaps_countrymap_mapdata.state_specific || {},
        dynamicStates,
      );

      // Re-render để áp dụng màu mới
      if (
        typeof window.simplemaps_countrymap !== "undefined" &&
        typeof window.simplemaps_countrymap.load === "function"
      ) {
        window.simplemaps_countrymap.load();
      }
    },

    // ----------------------------------------------------------------
    // Xử lý sự kiện click tỉnh trên bản đồ
    // ----------------------------------------------------------------
    _onProvinceClick: function (stateId) {
      if (!stateId) return;

      // Data AJAX chưa về → lưu lại, xử lý sau
      if (Object.keys(_partnersData).length === 0) {
        _pendingStateId = stateId;
        return;
      }

      // Sync dropdown
      $("#vn_map_province_select").val(stateId).trigger("change.select2");

      // Render danh sách đối tác
      VNM_DataBuilder.renderPartnerList(_partnersData[stateId] || null);
    },

    /**
     * Kích hoạt (highlight + zoom) một tỉnh theo stateId.
     * @param {string} stateId
     * @private
     */
    _activateProvince: function (stateId) {
      if (typeof window.simplemaps_countrymap === "undefined") {
        return;
      }

      try {
        // simplemaps cung cấp phương thức click_province để highlight tỉnh
        if (typeof window.simplemaps_countrymap.click_province === "function") {
          simplemaps_countrymap.click_province(stateId);
        } else if (
          typeof simplemaps_countrymap.setStateSelected === "function"
        ) {
          // Phiên bản khác
          window.simplemaps_countrymap.setStateSelected(stateId);
        }
      } catch (e) {
        console.warn("[VNM] Không thể kích hoạt tỉnh:", stateId, e);
      }
    },

    // ----------------------------------------------------------------
    // Khởi tạo select2 dropdown + populate ngay từ VNM_CONFIG.provinces
    // ----------------------------------------------------------------
    _initSelect: function () {
      // Populate tất cả 63 tỉnh thành ngay lập tức (không chờ AJAX)
      VNM_DataBuilder.populateSelect();

      // Khởi tạo select2 plugin
      $("#vn_map_province_select").select2({
        width: "100%",
        placeholder: "-- Chọn tỉnh thành --",
        allowClear: true,
      });

      // Xử lý khi người dùng chọn tỉnh từ dropdown
      $("#vn_map_province_select").on("change", function () {
        var stateId = $(this).val();

        // Xóa kết quả nếu clear
        if (!stateId) {
          var desc = document.getElementById("vn_map_description");
          if (desc) {
            desc.innerHTML =
              '<p class="vn-map-placeholder">Kích vào tỉnh thành trên bản đồ hoặc sử dụng ô tìm kiếm để xem danh sách đối tác.</p>';
          }
          return;
        }

        // Kích hoạt tỉnh trên bản đồ
        VNM_Map._activateProvince(stateId);

        // Render danh sách đối tác
        VNM_DataBuilder.renderPartnerList(_partnersData[stateId] || null);
      });
    },
  };

  // ------------------------------------------------------------------
  // Khởi động sau khi DOM sẵn sàng
  // ------------------------------------------------------------------
  $(document).ready(function () {
    VNM_Map.init();
  });

  // Expose để debug nếu cần
  window.VNM_Map = VNM_Map;
})(jQuery, window);
