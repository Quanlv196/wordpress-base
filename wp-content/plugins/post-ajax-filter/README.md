# Post AJAX Filter

Lọc bài viết WordPress bằng AJAX, hỗ trợ bộ lọc linh hoạt, phân trang và chia sẻ URL trạng thái.

---

## Cài đặt

1. Upload thư mục `post-ajax-filter` vào `/wp-content/plugins/`.
2. Kích hoạt plugin trong **Quản trị → Plugins**.
3. Dán shortcode vào trang/bài viết bất kỳ.

---

## Shortcode `[post_filter]`

Hiển thị một **widget bộ lọc** (danh mục, thẻ hoặc ô tìm kiếm).  
Phải đặt **trước hoặc bên cạnh** `[post_list]` và khai báo `id` để liên kết.

### Cú pháp

```
[post_filter id="f1" type="category" ui="checkbox"]
```

### Tham số

| Tham số       | Mặc định              | Giá trị hợp lệ                                           | Mô tả                                                                |
| ------------- | --------------------- | -------------------------------------------------------- | -------------------------------------------------------------------- |
| `id`          | _(tự sinh)_           | chuỗi                                                    | ID duy nhất của bộ lọc, dùng để liên kết với `[post_list]`.          |
| `type`        | `category`            | `category` \| `tag` \| `search`                          | Loại bộ lọc.                                                         |
| `ui`          | `checkbox`            | `checkbox` \| `radio` \| `dropdown` \| `tabs` \| `input` | Kiểu giao diện hiển thị.                                             |
| `label`       | _(tự sinh theo type)_ | chuỗi                                                    | Tiêu đề nhãn của bộ lọc.                                             |
| `show_label`  | `true`                | `true` \| `false`                                        | Hiện/ẩn tiêu đề bộ lọc.                                              |
| `cat_ids`     | _(tất cả)_            | `1,2,3`                                                  | Giới hạn danh sách danh mục hiển thị (chỉ dùng với `type=category`). |
| `tag_ids`     | _(tất cả)_            | `4,5,6`                                                  | Giới hạn danh sách thẻ hiển thị (chỉ dùng với `type=tag`).           |
| `placeholder` | _(trống)_             | chuỗi                                                    | Placeholder ô input (chỉ dùng với `type=search`).                    |
| `class`       | _(trống)_             | chuỗi                                                    | CSS class tuỳ chỉnh thêm vào wrapper.                                |

### Ví dụ

```
[post_filter id="filter-cat"    type="category" ui="checkbox"]
[post_filter id="filter-tag"    type="tag"      ui="tabs"]
[post_filter id="filter-search" type="search"   ui="input" placeholder="Nhập từ khoá…"]
```

---

## Shortcode `[post_list]`

Hiển thị **lưới bài viết** kèm toolbar sắp xếp và phân trang AJAX.  
Liên kết với một hoặc nhiều `[post_filter]` qua tham số `filter_ids`.

### Cú pháp

```
[post_list id="list1" filter_ids="filter-cat,filter-search" per_page="9" columns="3"]
```

### Tham số — Cấu hình danh sách

| Tham số      | Mặc định     | Mô tả                                                                 |
| ------------ | ------------ | --------------------------------------------------------------------- |
| `id`         | _(tự sinh)_  | ID duy nhất của danh sách.                                            |
| `filter_ids` | _(không có)_ | ID các `[post_filter]` liên kết, phân cách bởi dấu phẩy.              |
| `cat_ids`    | _(tất cả)_   | Giới hạn cứng danh mục (không thay đổi dù bộ lọc chọn gì).            |
| `tag_ids`    | _(tất cả)_   | Giới hạn cứng thẻ.                                                    |
| `per_page`   | `9`          | Số bài mỗi trang (1–100).                                             |
| `columns`    | `3`          | Số cột trên desktop (1–6).                                            |
| `tablet`     | `2`          | Số cột trên tablet (1–4).                                             |
| `mobile`     | `1`          | Số cột trên mobile (1–2).                                             |
| `orderby`    | `date`       | Sắp xếp mặc định: `date` \| `title` \| `comment_count` \| `modified`. |
| `order`      | `DESC`       | Chiều sắp xếp: `DESC` \| `ASC`.                                       |
| `class`      | _(trống)_    | CSS class tuỳ chỉnh thêm vào wrapper.                                 |

### Tham số — Giao diện thẻ bài viết

| Tham số          | Mặc định   | Mô tả                                                            |
| ---------------- | ---------- | ---------------------------------------------------------------- |
| `style`          | _(trống)_  | Kiểu card: _(trống)_ \| `overlay` \| `shade` \| `badge`.         |
| `show_date`      | `badge`    | Cách hiển thị ngày: `badge` \| `text` \| `false`.                |
| `show_category`  | `false`    | Hiển thị danh mục: `false` \| `true` \| `label`.                 |
| `excerpt`        | `visible`  | Hiển thị tóm tắt: `visible` \| `false`.                          |
| `excerpt_length` | `15`       | Số từ tóm tắt.                                                   |
| `image_height`   | `56%`      | Tỉ lệ chiều cao ảnh (padding-top CSS, ví dụ: `56%`, `75%`).      |
| `image_size`     | `medium`   | Kích thước ảnh WP: `thumbnail` \| `medium` \| `large` \| `full`. |
| `text_align`     | `center`   | Căn chỉnh văn bản: `left` \| `center` \| `right`.                |
| `readmore`       | _(ẩn nút)_ | Văn bản nút Đọc tiếp. Để trống = ẩn nút.                         |
| `readmore_style` | `outline`  | Style nút: `outline` \| `flat`.                                  |
| `readmore_size`  | `small`    | Kích thước nút: `small` \| `normal` \| `large`.                  |

### Ví dụ

```
[post_list
  id="list1"
  filter_ids="filter-cat,filter-search"
  per_page="9"
  columns="3"
  tablet="2"
  mobile="1"
  style="shade"
  show_date="text"
  show_category="true"
  readmore="Xem thêm"
  image_height="56%"
]
```

---

## Ví dụ tổng hợp

```
<!-- Bộ lọc danh mục dạng tab -->
[post_filter id="f-cat" type="category" ui="tabs"]

<!-- Ô tìm kiếm -->
[post_filter id="f-search" type="search" ui="input" placeholder="Tìm bài viết…"]

<!-- Danh sách liên kết với 2 bộ lọc trên -->
[post_list id="main-list" filter_ids="f-cat,f-search" per_page="6" columns="2" tablet="2" mobile="1"]
```

---

## Ghi chú

- Mỗi cặp `[post_filter]` + `[post_list]` phải có **`id` khác nhau** nếu đặt nhiều block trên cùng một trang.
- URL trạng thái bộ lọc được tự động cập nhật để hỗ trợ chia sẻ link.
- Plugin tương thích với theme **Flatsome** (blog card style).
