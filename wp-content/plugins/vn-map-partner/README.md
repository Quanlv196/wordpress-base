# VN Map Partner – WordPress Plugin

Hiển thị bản đồ đối tác Việt Nam theo tỉnh thành, clone UI từ file HTML gốc.

---

## Cài đặt

1. Copy thư mục `vn-map-partner/` vào `wp-content/plugins/`
2. Kích hoạt plugin từ **WordPress Admin → Plugins**
3. **Thay thế 2 file simplemaps** (xem hướng dẫn bên dưới)
4. Cập nhật mã tỉnh thành trong `assets/data/provinces.json`
5. Sử dụng shortcode `[vn_map_partner]` trên bất kỳ trang/bài viết nào

---

## ⚠️ Bước bắt buộc: Copy file simplemaps

Plugin cần 2 file từ hệ thống cũ của bạn:

### 1. `mapdata.js`

```
Nguồn: https://ketoan.cloud/static/frontend/cssmap/mapdata.js?v=1.15
Đích:  wp-content/plugins/vn-map-partner/assets/js/mapdata.js
```

### 2. `countrymap.js`

```
Nguồn: https://ketoan.cloud/static/frontend/cssmap/countrymap.js
Đích:  wp-content/plugins/vn-map-partner/assets/js/countrymap.js
```

**Cách tải file:**

- Mở trình duyệt → DevTools (F12) → Tab Network
- Tải lại trang `https://ketoan.cloud/lien-he`
- Lọc theo tên `mapdata` và `countrymap`
- Click vào từng file → tab Response → Copy → Paste vào file tương ứng

---

## Cập nhật mã tỉnh thành (QUAN TRỌNG)

Sau khi có `mapdata.js` thực tế, bạn cần tìm các **state ID** mà simplemaps sử dụng:

1. Mở `assets/js/mapdata.js`
2. Tìm object có dạng:
   ```javascript
   var simplemaps_countrymap_mapdata = {
       states: {
           "vn-hn":  { name: "Ha Noi", ... },
           "vn-hcm": { name: "Ho Chi Minh City", ... },
           ...
       }
   };
   ```
3. Ghi lại các key (VD: `"vn-hn"`, `"vn-hcm"`, v.v.)
4. Mở `assets/data/provinces.json` và cập nhật trường `code` cho từng tỉnh

---

## Cấu trúc plugin

```
vn-map-partner/
├── vn-map-partner.php          # File plugin chính
├── includes/
│   ├── class-cpt.php           # Custom Post Type "province_partner"
│   ├── class-rest-api.php      # REST API /wp-json/vn-map/v1/partners
│   ├── class-shortcode.php     # Shortcode [vn_map_partner]
│   └── class-admin.php         # Admin UI (meta boxes)
├── templates/
│   └── map-shortcode.php       # HTML template của shortcode
├── assets/
│   ├── js/
│   │   ├── mapdata.js          ← THAY THẾ bằng file thực
│   │   ├── countrymap.js       ← THAY THẾ bằng file thực
│   │   ├── map-data-builder.js # Chuyển đổi WP data → simplemaps format
│   │   └── map-init.js         # Khởi tạo bản đồ, xử lý events
│   ├── css/
│   │   └── vn-map-partner.css  # Toàn bộ CSS của plugin
│   └── data/
│       └── provinces.json      # Danh sách 63 tỉnh thành
└── README.md
```

---

## Sử dụng shortcode

```
[vn_map_partner]
```

Tùy chọn:

```
[vn_map_partner height="600px"]
```

---

## Quản lý đối tác

1. Vào **WP Admin → Bản đồ đối tác → Thêm mới**
2. Nhập **Tiêu đề** = Tên công ty đối tác
3. Điền các thông tin:
   - **Tỉnh thành**: Chọn tỉnh từ dropdown (mã phải khớp với mapdata.js)
   - **Loại đối tác**: Vàng hoặc Bạc
   - **Địa chỉ**: Địa chỉ văn phòng
   - **Liên hệ / SĐT**: Người liên hệ và số điện thoại
4. Nhấn **Xuất bản**

> Cache sẽ tự động xóa khi thêm/sửa/xóa đối tác. Thời gian cache mặc định: **1 giờ**.

---

## REST API

**Endpoint:** `GET /wp-json/vn-map/v1/partners`

**Response mẫu:**

```json
{
  "vn-tho": {
    "name": "Thanh Hóa",
    "partners": [
      {
        "name": "CÔNG TY TNHH CÔNG NGHỆ THƯƠNG MẠI SÔNG MÃ",
        "type": "gold",
        "address": "Số 04 Nguyễn Quỳnh, phường Điện Biên, TP. Thanh Hóa",
        "phone": "Mr. Hưng - 0914324727"
      }
    ]
  },
  "vn-tn": {
    "name": "Tây Ninh",
    "partners": [
      {
        "name": "CÔNG TY TNHH TERP VIỆT NAM",
        "type": "silver",
        "address": "",
        "phone": "Ms. Hiền - 0333611163"
      }
    ]
  }
}
```

---

## Yêu cầu hệ thống

- WordPress 5.8+
- PHP 7.4+
- jQuery (có sẵn trong WordPress)

---

## Xóa cache thủ công

Nếu cần xóa cache ngay:

```php
delete_transient('vn_map_partners_data');
```

Hoặc chỉnh sửa bất kỳ bài đối tác nào → Lưu → Cache tự xóa.
