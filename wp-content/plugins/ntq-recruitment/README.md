# Recruitment Manager – Hướng dẫn sử dụng

Plugin quản lý tuyển dụng và việc làm cho WordPress.

---

## Shortcodes

### 1. `[job_filter]`

Hiển thị thanh lọc (filter) với dropdown **Vị Trí**, **Phòng Ban** và **Địa Điểm**.  
Kết hợp với `[job_list]` trên cùng trang để lọc danh sách việc làm bằng AJAX.

**Cú pháp:**
```
[job_filter]
```

**Không có tham số.**

Các dropdown hiển thị trong form:

| Dropdown       | Dữ liệu nguồn                          |
|----------------|----------------------------------------|
| Vị trí         | Tất cả job post đang **publish**       |
| Phòng Ban      | Taxonomy `job_department`             |
| Địa Điểm       | Taxonomy `job_location`               |

---

### 2. `[job_list]`

Hiển thị danh sách các vị trí tuyển dụng đang mở, có phân trang và hỗ trợ lọc AJAX.

**Cú pháp:**
```
[job_list]
[job_list limit="10"]
[job_list limit="5" offset="0"]
```

| Tham số  | Kiểu  | Mặc định | Mô tả |
|----------|-------|----------|-------|
| `limit`  | `int` | `10`     | Số việc làm hiển thị mỗi trang. Tối đa `50`. |
| `offset` | `int` | `0`      | Bỏ qua N việc làm đầu tiên (dùng khi muốn bắt đầu từ vị trí nhất định). |

**Ví dụ – Hiển thị 6 việc làm mới nhất:**
```
[job_list limit="6"]
```

---

### 3. `[job_apply]`

Hiển thị form ứng tuyển. Có thể dùng độc lập hoặc nhúng vào trang tùy chỉnh.

> **Lưu ý:** Trên các trang **single job** (chi tiết việc làm), form ứng tuyển được tự động thêm vào. Bạn **không cần** dùng shortcode này trên trang đó.

**Cú pháp:**
```
[job_apply]
[job_apply job_id="123"]
```

| Tham số  | Kiểu  | Mặc định | Mô tả |
|----------|-------|----------|-------|
| `job_id` | `int` | `0`      | ID của bài đăng việc làm. Nếu để `0`, form sẽ là form ứng tuyển tổng hợp (không gắn với vị trí cụ thể). |

**Ví dụ – Form ứng tuyển cho job ID 42:**
```
[job_apply job_id="42"]
```

**Ví dụ – Form ứng tuyển tổng hợp (có dropdown chọn vị trí):**
```
[job_apply]
```

Khi dùng `[job_apply]` không truyền `job_id`, form sẽ hiển thị thêm dropdown **Vị trí ứng tuyển** lấy từ tất cả job post đang publish. Ứng viên bắt buộc phải chọn trước khi nộp.

---

## Cách thiết lập trang tuyển dụng

### Bước 1 – Tạo trang danh sách việc làm

1. Vào **Trang > Thêm Mới**, đặt tên ví dụ: _"Tuyển Dụng"_.
2. Thêm 2 shortcode sau vào nội dung trang:

```
[job_filter]
[job_list limit="10"]
```

### Bước 2 – Thêm việc làm mới

1. Vào **Việc Làm > Thêm Mới**.
2. Điền tiêu đề, nội dung mô tả công việc.
3. Chọn **Phòng Ban** và **Địa Điểm** ở sidebar phải.
4. Điền **Mức Lương** và **Hạn Nộp Hồ Sơ** trong hộp _"Thông Tin Vị Trí"_.
5. Xuất bản bài đăng.

### Bước 3 – Xem trang chi tiết việc làm

Mỗi việc làm có trang riêng với:
- Nội dung mô tả công việc (cột trái).
- Sidebar phải hiển thị các thông tin **Hạn nộp / Phòng ban / Mức lương / Địa điểm** và form ứng tuyển trực tiếp.

---

## Quản lý hồ sơ ứng tuyển

Vào **Việc Làm > Hồ Sơ Ứng Tuyển** trong WordPress Admin để:

- Xem danh sách tất cả hồ sơ, lọc theo vị trí / phòng ban / địa điểm / trạng thái.
- Xem chi tiết từng hồ sơ và tải CV về.
- Cập nhật trạng thái: **Mới** → **Đang Xem Xét** → **Chấp Nhận** / **Từ Chối**.
- Xóa hồ sơ (file CV trên server cũng được xóa theo).

---

## Yêu cầu

- WordPress **5.8** trở lên
- PHP **7.4** trở lên
- Kích thước file CV tối đa: **5 MB**
- Định dạng file chấp nhận: **PDF, DOC, DOCX**
