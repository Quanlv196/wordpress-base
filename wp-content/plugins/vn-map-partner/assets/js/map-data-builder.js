/**
 * VN Map Partner – map-data-builder.js
 *
 * Chức năng:
 *   1. Gọi WP REST API lấy dữ liệu đối tác
 *   2. Chuyển đổi sang định dạng simplemaps state_specific
 *   3. Render danh sách đối tác vào #vn_map_description
 *   4. Populate select2 dropdown
 */
/* global VNM_CONFIG, simplemaps_countrymap_mapdata */
(function (window) {
  "use strict";

  // -------------------------------------------------------
  // Cấu hình màu sắc
  // -------------------------------------------------------
  var COLORS = {
    gold: "#f5c518",
    gold_hover: "#e5a810",
    gold_border: "#d4a900",
    silver: "#aaaaaa",
    silver_hover: "#888888",
    default: "#dddddd",
    border: "#ffffff",
  };

  // -------------------------------------------------------
  // VNM_DataBuilder – đối tượng public
  // -------------------------------------------------------
  var VNM_DataBuilder = {
    /**
     * Gọi REST API lấy toàn bộ dữ liệu đối tác.
     *
     * @param {function(Error|null, Object|null): void} callback
     */
    fetchPartners: function (callback) {
      if (!window.VNM_CONFIG || !window.VNM_CONFIG.rest_url) {
        callback(new Error("[VNM] VNM_CONFIG chưa được khởi tạo."), null);
        return;
      }

      fetch(window.VNM_CONFIG.rest_url, {
        method: "GET",
        headers: {
          "X-WP-Nonce": window.VNM_CONFIG.nonce || "",
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("[VNM] REST API lỗi HTTP " + response.status);
          }
          return response.json();
        })
        .then(function (data) {
          callback(null, data);
        })
        .catch(function (err) {
          console.error("[VNM] Lấy dữ liệu thất bại:", err);
          callback(err, null);
        });
    },

    /**
     * Chuyển đổi dữ liệu WP sang object state_specific của simplemaps.
     *
     * Mỗi key là province_code (phải khớp với state ID trong mapdata.js).
     * Kết quả dùng để gán: simplemaps_countrymap_mapdata.state_specific = result
     *
     * @param  {Object} wpData  - Dữ liệu trả về từ REST API
     * @return {Object}         - state_specific object
     */
    buildStateSpecific: function (wpData) {
      var stateSpecific = {};

      Object.keys(wpData).forEach(function (code) {
        var province = wpData[code];
        var partners = Array.isArray(province.partners)
          ? province.partners
          : [];

        // Xác định màu ưu tiên: nếu có ít nhất 1 đối tác vàng → màu vàng
        var hasGold = partners.some(function (p) {
          return p.type === "gold";
        });
        var hasSilver = partners.some(function (p) {
          return p.type === "silver";
        });

        var color = COLORS.default;
        var hoverColor = COLORS.silver_hover;

        if (hasGold) {
          color = COLORS.gold;
          hoverColor = COLORS.gold_hover;
        } else if (hasSilver) {
          color = COLORS.silver;
          hoverColor = COLORS.silver_hover;
        }

        stateSpecific[code] = {
          color: color,
          hover_color: hoverColor,
          border_color: COLORS.border,
          description: VNM_DataBuilder._buildTooltipHtml(
            province.name,
            partners,
          ),
          url: "",
        };
      });

      return stateSpecific;
    },

    /**
     * Tạo HTML hiển thị trong tooltip (popup) khi hover/click tỉnh trên bản đồ.
     *
     * @param  {string} provinceName
     * @param  {Array}  partners
     * @return {string} HTML string
     * @private
     */
    _buildTooltipHtml: function (provinceName, partners) {
      var html =
        '<div class="vnm-tt-province">' +
        VNM_DataBuilder._esc(provinceName) +
        "</div>";

      if (!partners || partners.length === 0) {
        return html;
      }

      partners.forEach(function (p, idx) {
        var typeClass = p.type === "gold" ? "vnm-tt-gold" : "vnm-tt-silver";
        html += '<div class="vnm-tt-item ' + typeClass + '">';
        html += '<span class="vnm-tt-num">' + (idx + 1) + ". </span>";
        html += "<strong>" + VNM_DataBuilder._esc(p.name) + "</strong>";
        if (p.address) {
          html += "<br><small>" + VNM_DataBuilder._esc(p.address) + "</small>";
        }
        if (p.phone) {
          html +=
            "<br><small>Liên hệ: " + VNM_DataBuilder._esc(p.phone) + "</small>";
        }
        html += "</div>";
      });

      return html;
    },

    /**
     * Render danh sách đối tác vào element #vn_map_description.
     *
     * @param {Object|null} provinceData  - object { name, partners[] } hoặc null
     */
    renderPartnerList: function (provinceData) {
      var container = document.getElementById("vn_map_description");
      if (!container) {
        return;
      }

      // Trường hợp không có dữ liệu
      if (
        !provinceData ||
        !Array.isArray(provinceData.partners) ||
        provinceData.partners.length === 0
      ) {
        container.innerHTML =
          '<p class="vnm-no-partner">Tỉnh thành này chưa có đối tác được đăng ký.</p>';
        return;
      }

      var html = "";
      provinceData.partners.forEach(function (p, idx) {
        var typeClass = p.type === "gold" ? "vnm-type-gold" : "vnm-type-silver";
        var iconColor = p.type === "gold" ? COLORS.gold : COLORS.silver;
        var typeLabel = p.type === "gold" ? "Đối tác vàng" : "Đối tác bạc";

        html += '<div class="vnm-partner-item ' + typeClass + '">';

        // Header: icon + tên
        html += '<div class="vnm-partner-header">';
        html +=
          '<svg class="vnm-star-icon" xmlns="http://www.w3.org/2000/svg" ';
        html +=
          'viewBox="0 0 24 24" width="18" height="18" fill="' +
          iconColor +
          '" aria-hidden="true">';
        html += '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77';
        html += 'l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>';
        html += "</svg>";
        html += '<span class="vnm-partner-index">' + (idx + 1) + ".</span>";
        html +=
          '<span class="vnm-partner-name">' +
          VNM_DataBuilder._esc(p.name) +
          "</span>";
        html += "</div>";

        // Loại đối tác
        html += '<div class="vnm-partner-type-label">' + typeLabel + "</div>";

        // Địa chỉ
        if (p.address) {
          html += '<div class="vnm-partner-address">';
          html += '<span class="vnm-label">Địa chỉ:</span> ';
          html += VNM_DataBuilder._esc(p.address);
          html += "</div>";
        }

        // Liên hệ
        if (p.phone) {
          html += '<div class="vnm-partner-phone">';
          html += '<span class="vnm-label">Liên hệ:</span> ';
          html += VNM_DataBuilder._esc(p.phone);
          html += "</div>";
        }

        html += "</div>"; // .vnm-partner-item
      });

      container.innerHTML = html;
    },

    /**
     * Populate dropdown select2 với danh sách các tỉnh có đối tác.
     * Sắp xếp theo tên tỉnh (locale vi).
     *
     * @param {Object} wpData
     */
    populateSelect: function (wpData) {
      var selectEl = document.getElementById("vn_map_province_select");
      if (!selectEl) {
        return;
      }

      // Xây dựng mảng, lọc tỉnh có đối tác, sắp xếp A-Z
      var provinces = Object.keys(wpData)
        .filter(function (code) {
          var d = wpData[code];
          return d && Array.isArray(d.partners) && d.partners.length > 0;
        })
        .map(function (code) {
          return { code: code, name: wpData[code].name || code };
        })
        .sort(function (a, b) {
          return a.name.localeCompare(b.name, "vi", { sensitivity: "base" });
        });

      provinces.forEach(function (p) {
        var opt = document.createElement("option");
        opt.value = p.code;
        opt.textContent = p.name;
        selectEl.appendChild(opt);
      });
    },

    /**
     * Escape HTML để ngăn XSS khi nội dung được đưa vào innerHTML.
     *
     * @param  {string} str
     * @return {string}
     * @private
     */
    _esc: function (str) {
      if (!str) {
        return "";
      }
      var d = document.createElement("div");
      d.textContent = String(str);
      return d.innerHTML;
    },
  };

  window.VNM_DataBuilder = VNM_DataBuilder;
})(window);
