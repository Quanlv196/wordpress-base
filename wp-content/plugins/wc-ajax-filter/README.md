# WC AJAX Filter

Lọc sản phẩm WooCommerce bằng AJAX, không reload trang. Hỗ trợ lọc theo danh mục, thương hiệu, khoảng giá với nhiều kiểu giao diện, phân trang và đồng bộ URL.

---

## Yêu cầu

| Thành phần | Phiên bản tối thiểu |
|---|---|
| WordPress | 5.8 |
| WooCommerce | 5.0 |
| PHP | 7.4 |

---

## Cài đặt

1. Upload thư mục `wc-ajax-filter/` vào `/wp-content/plugins/`.
2. Kích hoạt plugin trong **Plugins → Installed Plugins**.
3. Đặt shortcode vào trang/widget bất kỳ (xem bên dưới).

---

## Shortcodes

Plugin cung cấp 5 shortcode độc lập, kết hợp với nhau qua thuộc tính `id` / `filter_ids`.

---

### 1. `[wc_filter]` — Bộ lọc

Hiển thị một widget lọc (danh mục, thương hiệu hoặc giá).

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `id` | string | tự sinh | ID định danh, dùng để kết nối với `[wc_product_list]` |
| `type` | string | `category` | Loại lọc: `category` \| `brand` \| `price` |
| `ui` | string | `checkbox` | Kiểu giao diện: `checkbox` \| `radio` \| `dropdown` \| `tabs` \| `range` |
| `label` | string | *(theo type)* | Tiêu đề hiển thị của filter |
| `show_label` | bool | `true` | Ẩn/hiện tiêu đề |
| `expanded` | bool | `true` | Mở rộng hay thu gọn mặc định |
| `cat_ids` | string | `` | Giới hạn phạm vi lọc theo danh sách ID danh mục (phân cách bằng dấu phẩy) |
| `class` | string | `` | CSS class bổ sung |

**Ví dụ:**

```
[wc_filter id="f1" type="category" ui="checkbox"]
[wc_filter id="f2" type="brand"    ui="tabs"]
[wc_filter id="f3" type="price"    ui="range"]
[wc_filter id="f4" type="category" ui="dropdown" label="Lọc theo danh mục" cat_ids="12,15,20"]
[wc_filter id="f5" type="price"    ui="dropdown" show_label="false"]
```

---

### 2. `[wc_product_list]` — Danh sách sản phẩm

Hiển thị lưới sản phẩm kèm toolbar (số kết quả, chọn số lượng/trang, sắp xếp) và phân trang. Kết nối với một hoặc nhiều `[wc_filter]` qua `filter_ids`.

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `id` | string | tự sinh | ID của danh sách |
| `filter_ids` | string | `` | Danh sách các `id` của `[wc_filter]` cần lắng nghe (phân cách bằng dấu phẩy) |
| `cat_ids` | string | `` | Giới hạn danh mục cả tải đầu và AJAX refresh |
| `per_page` | int | `12` | Số sản phẩm mỗi trang (1–100) |
| `columns` | int | `4` | Số cột desktop (1–6) |
| `tablet` | int | `2` | Số cột tablet (1–4) |
| `mobile` | int | `1` | Số cột mobile (1–2) |
| `orderby` | string | `date` | Sắp xếp theo: `date` \| `price` \| `title` \| `popularity` \| `rating` |
| `order` | string | `DESC` | Thứ tự: `ASC` \| `DESC` |
| `class` | string | `` | CSS class bổ sung |

**Hiển thị card sản phẩm (Flatsome / WooCommerce):**

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `show_title` | bool | `true` | Ẩn/hiện tên sản phẩm |
| `show_price` | bool | `true` | Ẩn/hiện giá |
| `show_rating` | bool | `true` | Ẩn/hiện sao đánh giá |
| `show_add_to_cart` | bool | `true` | Ẩn/hiện nút thêm vào giỏ hàng |
| `show_second_image` | bool | `true` | Ẩn/hiện ảnh hover (second image) |
| `show_view_detail` | bool | `true` | Ẩn/hiện nút "Xem chi tiết" |
| `view_detail_label` | string | `Xem chi tiết` | Tuỳ chỉnh nhãn nút "Xem chi tiết" |
| `image_size` | string | `woocommerce_thumbnail` | Kích thước ảnh (bất kỳ size WP nào) |
| `style` | string | `` | Card style Flatsome: `overlay` \| `button-on-image` \| ... |
| `text_align` | string | `` | Căn chỉnh chữ: `left` \| `center` \| `right` |

**Ví dụ:**

```
[wc_product_list id="list1" filter_ids="f1,f2,f3" per_page="12" columns="4" tablet="2" mobile="1"]

[wc_product_list id="list1" filter_ids="f1" cat_ids="10" per_page="24" columns="3" orderby="price" order="ASC"]

[wc_product_list id="list1" filter_ids="f1,f2"
  columns="4" per_page="12"
  style="overlay" text_align="center"
  show_rating="false"
  show_view_detail="true" view_detail_label="Chi tiết sản phẩm"]
```

---

### 3. `[wc_filter_count]` — Đếm số bộ lọc đang hoạt động

Hiển thị badge số lượng giá trị lọc đang được chọn. JavaScript tự động cập nhật khi filter thay đổi.

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `filter_ids` | string | `` | Danh sách ID `[wc_filter]` cần theo dõi |
| `zero_text` | string | `` | Văn bản hiển thị khi không có filter nào được chọn |
| `class` | string | `` | CSS class bổ sung |

**Ví dụ:**

```
[wc_filter_count filter_ids="f1,f2,f3"]
[wc_filter_count filter_ids="f1,f2" zero_text="Chưa chọn bộ lọc nào"]
```

---

### 4. `[wc_clear_filter]` — Nút xoá tất cả bộ lọc

Hiển thị một nút bấm để reset toàn bộ filter đang kết nối và kích hoạt lại truy vấn sản phẩm.

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `filter_ids` | string | `` | Danh sách ID `[wc_filter]` cần reset |
| `label` | string | `Xoá bộ lọc` | Nhãn của nút |
| `class` | string | `` | CSS class bổ sung |

**Ví dụ:**

```
[wc_clear_filter filter_ids="f1,f2,f3"]
[wc_clear_filter filter_ids="f1,f2" label="Đặt lại bộ lọc"]
```

---

### 5. `[wc_active_filters]` — Danh sách chip bộ lọc đang hoạt động

Hiển thị các chip (tag) cho từng giá trị đang được lọc, kèm nút "×" để bỏ chọn từng mục và nút "xoá tất cả".

| Thuộc tính | Kiểu | Mặc định | Mô tả |
|---|---|---|---|
| `filter_ids` | string | `` | Danh sách ID `[wc_filter]` cần theo dõi |
| `clear_label` | string | `Xoá tất cả` | Nhãn nút xoá tất cả |
| `empty_text` | string | `` | Văn bản khi chưa có filter nào |
| `class` | string | `` | CSS class bổ sung |

**Ví dụ:**

```
[wc_active_filters filter_ids="f1,f2,f3"]
[wc_active_filters filter_ids="f1,f2" clear_label="Xoá tất cả" empty_text="Không có bộ lọc nào"]
```

---

## Ví dụ tổng hợp

### Layout đơn giản (sidebar + lưới)

```
<!-- Sidebar -->
[wc_filter_count filter_ids="f1,f2,f3"]
[wc_clear_filter filter_ids="f1,f2,f3"]

[wc_filter id="f1" type="category" ui="checkbox"]
[wc_filter id="f2" type="brand"    ui="checkbox"]
[wc_filter id="f3" type="price"    ui="range"]

<!-- Nội dung chính -->
[wc_product_list id="list1" filter_ids="f1,f2,f3" per_page="12" columns="4"]
```

---

### Lọc trong một danh mục cụ thể (VD: danh mục ID 10)

```
[wc_filter id="f1" type="category" ui="tabs"     cat_ids="10"]
[wc_filter id="f2" type="brand"    ui="dropdown" cat_ids="10"]
[wc_filter id="f3" type="price"    ui="range"]

[wc_active_filters filter_ids="f1,f2,f3" clear_label="Xoá tất cả"]
[wc_product_list   id="list1" filter_ids="f1,f2,f3" cat_ids="10" per_page="12" columns="3" orderby="popularity"]
```

---

### Lọc sản phẩm mới nhất, 2 cột mobile

```
[wc_filter id="f1" type="category" ui="radio"]
[wc_filter id="f2" type="price"    ui="range"]

[wc_product_list id="list1" filter_ids="f1,f2" per_page="24" columns="4" tablet="3" mobile="2" orderby="date" order="DESC"]
```

---

## Lưu ý

- Mỗi cặp `[wc_filter]` — `[wc_product_list]` phải dùng chung `id` / `filter_ids` để JavaScript kết nối đúng luồng sự kiện.
- Kết quả AJAX được cache bằng transient trong 5 phút. Cache tự vô hiệu khi một sản phẩm được lưu hoặc xoá.
- Có thể mở rộng danh sách loại filter qua hook `wcaf_allowed_filter_types` và tùy chỉnh tùy chọn số sản phẩm/trang qua hook `wcaf_per_page_options`.
