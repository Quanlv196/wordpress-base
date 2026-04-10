/**
 * =============================================================================
 * VN Map Partner – mapdata.js  (PLACEHOLDER)
 * =============================================================================
 *
 * ⚠️  FILE NÀY LÀ PLACEHOLDER – BẠN CẦN THAY THẾ BẰNG FILE THỰC TẾ
 *
 * Hướng dẫn:
 *   1. Mở trang web gốc của bạn (VD: https://ketoan.cloud/lien-he)
 *   2. Mở DevTools → Tab Sources hoặc Network
 *   3. Tìm và tải file: .../cssmap/mapdata.js
 *   4. Sao chép nội dung file đó và thay thế toàn bộ nội dung file này
 *   5. Tương tự với file countrymap.js
 *
 * Lưu ý quan trọng về province_code:
 *   - Mở mapdata.js thực tế của bạn
 *   - Tìm object "states" hoặc "state_specific"
 *   - Ghi lại các state ID (key của object đó)
 *   - Cập nhật file assets/data/provinces.json với các code tương ứng
 *   - Khi nhập đối tác trong WP Admin, dùng đúng code đó ở trường "Tỉnh thành"
 *
 * Ví dụ state ID hợp lệ trong mapdata.js:
 *   - "vn-hn"  → Hà Nội
 *   - "vn-hcm" → TP. Hồ Chí Minh
 *   - Hoặc số như "12", "56" tuỳ theo phiên bản simplemaps
 * =============================================================================
 */

// Khai báo object tối thiểu để plugin không crash khi mapdata.js chưa được thay thế
var simplemaps_countrymap_mapdata = {
  main_settings: {
    //General settings
    width: "responsive", //'700' or 'responsive'
    background_color: "#FFFFFF",
    background_transparent: "yes",
    border_color: "#ffffff",

    //State defaults
    state_description:
      "Tá»‰nh thÃ nh nÃ y hiá»‡n chÆ°a cÃ³ Ä‘á»‘i tÃ¡c.<br/><ul><li>Äá»‘i vá»›i khÃ¡ch hÃ ng, vui lÃ²ng liÃªn há»‡ 1C Viá»‡t Nam theo sá»‘: <strong>(+84)247 108 8887</strong></li><li>Äá»‘i vá»›i Ä‘á»‘i tÃ¡c, Ä‘á»ƒ trá»Ÿ thÃ nh Ä‘áº¡i diá»‡n cho tá»‰nh thÃ nh nÃ y, vui lÃ²ng Ä‘Äƒng kÃ½ <a href='https://ketoan.cloud/doi-tac'>táº¡i Ä‘Ã¢y</a></li></ul>",
    state_color: "#88A4BC",
    state_hover_color: "#3B729F",
    state_url: "",
    border_size: 1.5,
    all_states_inactive: "no",
    all_states_zoomable: "yes",

    //Location defaults
    location_description: "",
    location_url: "",
    location_color: "#FF0067",
    location_opacity: 0.8,
    location_hover_opacity: 1,
    location_size: 25,
    location_type: "square",
    location_image_source: "frog.png",
    location_border_color: "#FFFFFF",
    location_border: 2,
    location_hover_border: 2.5,
    all_locations_inactive: "no",
    all_locations_hidden: "no",

    //Label defaults
    label_color: "#d5ddec",
    label_hover_color: "#d5ddec",
    label_size: 22,
    label_font: "Arial",
    hide_labels: "no",
    hide_eastern_labels: "no",

    //Zoom settings
    zoom: "yes",
    manual_zoom: "no",
    back_image: "no",
    initial_back: "no",
    initial_zoom: "-1",
    initial_zoom_solo: "no",
    region_opacity: 1,
    region_hover_opacity: 0.6,
    zoom_out_incrementally: "yes",
    zoom_percentage: 0.5,
    zoom_time: 0.5,

    //Popup settings
    popup_color: "white",
    popup_opacity: 0.9,
    popup_shadow: 1,
    popup_corners: 5,
    popup_font: "12px/1.5 Verdana, Arial, Helvetica, sans-serif",
    popup_nocss: "no",

    //Advanced settings
    div: "map",
    auto_load: "no",
    url_new_tab: "no",
    images_directory: "default",
    fade_time: 0.1,
    link_text: "View Website",
    popups: "detect",
  },
  state_specific: {},
  locations: {
    0: {
      lat: "16.7707896",
      lng: "111.3047593",
      color: "#939598",
      name: "HoÃ ng Sa",
    },
    1: {
      lat: "11.7707896",
      lng: "111.3047593",
      color: "#939598",
      name: "TrÆ°á»ng Sa",
    },
  },
  labels: {},
  legend: {
    entries: [],
  },
  regions: {},
};

// Cảnh báo trong console
// if (typeof console !== "undefined" && console.warn) {
//   console.warn(
//     "[VN Map Partner] Bạn đang dùng file mapdata.js PLACEHOLDER.\n" +
//       "Hãy thay thế bằng file mapdata.js thực tế từ hệ thống simplemaps của bạn.\n" +
//       "Xem hướng dẫn trong: wp-content/plugins/vn-map-partner/README.md",
//   );
// }
