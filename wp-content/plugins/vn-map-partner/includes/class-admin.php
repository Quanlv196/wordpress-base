<?php
/**
 * Admin UI cho CPT province_partner.
 * Cung cấp meta box để nhập thông tin đối tác.
 */
defined( 'ABSPATH' ) || exit;

class VNM_Admin {

    /**
     * Đăng ký meta box trên trang chỉnh sửa đối tác.
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'vnm_partner_info',
            'Thông tin đối tác',
            [ self::class, 'render_meta_box' ],
            'province_partner',
            'normal',
            'high'
        );
    }

    /**
     * Render nội dung meta box.
     */
    public static function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'vnm_save_meta_' . $post->ID, 'vnm_meta_nonce' );

        $province_code = get_post_meta( $post->ID, '_vnm_province_code', true );
        $province_name = get_post_meta( $post->ID, '_vnm_province_name', true );
        $partner_type  = get_post_meta( $post->ID, '_vnm_partner_type',  true ) ?: 'silver';
        $address       = get_post_meta( $post->ID, '_vnm_address',        true );
        $phone         = get_post_meta( $post->ID, '_vnm_phone',           true );

        $provinces = self::get_provinces_list();
        ?>
        <table class="vnm-meta-table form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="vnm_province_select">Tỉnh thành <span class="required" aria-hidden="true">*</span></label>
                    </th>
                    <td>
                        <select id="vnm_province_select" class="vnm-select2-admin" style="width:100%;max-width:400px;">
                            <option value="">-- Chọn tỉnh thành --</option>
                            <?php foreach ( $provinces as $item ) : ?>
                                <option
                                    value="<?php echo esc_attr( $item['code'] ); ?>"
                                    data-name="<?php echo esc_attr( $item['name'] ); ?>"
                                    <?php selected( $province_code, $item['code'] ); ?>
                                >
                                    <?php echo esc_html( $item['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Hidden fields lưu code & name -->
                        <input type="hidden" id="vnm_province_code" name="vnm_province_code"
                               value="<?php echo esc_attr( $province_code ); ?>" />
                        <input type="hidden" id="vnm_province_name" name="vnm_province_name"
                               value="<?php echo esc_attr( $province_name ); ?>" />

                        <p class="description">
                            Mã tỉnh phải khớp với <code>state ID</code> trong file <code>mapdata.js</code> của bạn.
                            Xem hướng dẫn trong file <code>README.md</code> của plugin.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vnm_partner_type">Loại đối tác <span class="required" aria-hidden="true">*</span></label>
                    </th>
                    <td>
                        <select id="vnm_partner_type" name="vnm_partner_type">
                            <option value="gold"   <?php selected( $partner_type, 'gold' );   ?>>⭐ Đối tác vàng (Trực tiếp hỗ trợ, tư vấn)</option>
                            <option value="silver" <?php selected( $partner_type, 'silver' ); ?>>🥈 Đối tác bạc (Tư vấn, cầu nối liên lạc)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vnm_address">Địa chỉ</label>
                    </th>
                    <td>
                        <input type="text" id="vnm_address" name="vnm_address"
                               class="regular-text"
                               value="<?php echo esc_attr( $address ); ?>"
                               placeholder="VD: Số 04 Nguyễn Quỳnh, phường Điện Biên, TP. Thanh Hóa" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="vnm_phone">Liên hệ / SĐT</label>
                    </th>
                    <td>
                        <input type="text" id="vnm_phone" name="vnm_phone"
                               class="regular-text"
                               value="<?php echo esc_attr( $phone ); ?>"
                               placeholder="VD: Mr. Hùng - 0914324727" />
                        <p class="description">Tên người liên hệ và số điện thoại.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <script>
        jQuery(function ($) {
            var $select = $('#vnm_province_select');

            // Khởi tạo select2 cho dropdown tỉnh thành
            $select.select2({
                width: '100%',
                placeholder: '-- Chọn tỉnh thành --',
                allowClear: true,
            });

            // Đồng bộ hidden fields khi thay đổi
            $select.on('change', function () {
                var $opt = $select.find('option:selected');
                $('#vnm_province_code').val($opt.val() || '');
                $('#vnm_province_name').val($opt.data('name') || '');
            });
        });
        </script>
        <?php
    }

    /**
     * Lưu meta data khi submit form.
     */
    public static function save_meta( int $post_id ): void {
        // Kiểm tra nonce bảo mật
        if ( ! isset( $_POST['vnm_meta_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['vnm_meta_nonce'] ) ),
            'vnm_save_meta_' . $post_id
        ) ) {
            return;
        }

        // Không lưu khi autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Kiểm tra quyền
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Danh sách fields cần lưu (meta_key => post_key)
        $fields = [
            '_vnm_province_code' => 'vnm_province_code',
            '_vnm_province_name' => 'vnm_province_name',
            '_vnm_partner_type'  => 'vnm_partner_type',
            '_vnm_address'       => 'vnm_address',
            '_vnm_phone'         => 'vnm_phone',
        ];

        foreach ( $fields as $meta_key => $post_key ) {
            if ( array_key_exists( $post_key, $_POST ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );

                // Whitelist cho partner_type
                if ( $meta_key === '_vnm_partner_type' ) {
                    $value = in_array( $value, [ 'gold', 'silver' ], true ) ? $value : 'silver';
                }

                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }

    /**
     * Enqueue Select2 cho trang admin của CPT.
     */
    public static function enqueue_scripts( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'province_partner' ) {
            return;
        }

        wp_enqueue_style(
            'vnm-select2-admin',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_script(
            'vnm-select2-admin',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.1.0',
            true
        );
    }

    /**
     * Hiển thị thông báo admin nếu có bài viết dùng mã tỉnh cũ (vn-xx).
     */
    public static function outdated_code_notice(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        // Đếm số bài còn mã cũ (không bắt đầu bằng "VNM")
        $query = new WP_Query( [
            'post_type'      => 'province_partner',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'no_found_rows'  => false,
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_vnm_province_code',
                    'value'   => '^VNM',
                    'compare' => 'NOT REGEXP',
                ],
            ],
        ] );

        if ( $query->found_posts < 1 ) {
            return;
        }

        $list_url = admin_url( 'edit.php?post_type=province_partner' );
        printf(
            '<div class="notice notice-warning"><p><strong>VN Map Partner:</strong> Có <strong>%d</strong> bài đối tác đang dùng mã tỉnh cũ (vn-xx), cần cập nhật lại để marker hiển thị đúng trên bản đồ. <a href="%s">Xem danh sách &rarr;</a></p></div>',
            (int) $query->found_posts,
            esc_url( $list_url )
        );
    }

    /**
     * Đọc danh sách tỉnh thành từ file provinces.json.
     *
     * @return array<int, array{code: string, name: string}>
     */
    private static function get_provinces_list(): array {
        $file = VNM_PATH . 'assets/data/provinces.json';

        if ( ! file_exists( $file ) ) {
            return [];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $json = file_get_contents( $file );
        if ( false === $json ) {
            return [];
        }

        // Strip UTF-8 BOM nếu editor Windows thêm vào
        $json = ltrim( $json, "\xEF\xBB\xBF" );

        $data = json_decode( $json, true );

        if ( ! is_array( $data ) ) {
            return [];
        }

        return $data;
    }
}
