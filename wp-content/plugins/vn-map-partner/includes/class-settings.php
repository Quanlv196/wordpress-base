<?php
/**
 * Trang cài đặt plugin VN Map Partner.
 * Cho phép cấu hình Register URL qua WP Admin.
 */
defined( 'ABSPATH' ) || exit;

class VNM_Settings {

    const OPTION_GROUP  = 'vnm_settings_group';
    const OPTION_NAME   = 'vnm_settings';
    const MENU_SLUG     = 'vnm-settings';

    /**
     * Đăng ký submenu "Cài đặt" dưới CPT province_partner.
     */
    public static function register(): void {
        add_submenu_page(
            'edit.php?post_type=province_partner',
            'Cài đặt VN Map Partner',
            'Cài đặt',
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    /**
     * Đăng ký settings với WordPress Settings API.
     */
    public static function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ self::class, 'sanitize' ],
                'default'           => [],
            ]
        );

        add_settings_section(
            'vnm_general_section',
            'Cài đặt chung',
            '__return_false',
            self::MENU_SLUG
        );

        add_settings_field(
            'register_url',
            'URL đăng ký đối tác',
            [ self::class, 'render_register_url_field' ],
            self::MENU_SLUG,
            'vnm_general_section'
        );
    }

    /**
     * Sanitize options trước khi lưu.
     *
     * @param mixed $input
     * @return array
     */
    public static function sanitize( $input ): array {
        $output = [];

        if ( isset( $input['register_url'] ) ) {
            $value = trim( $input['register_url'] );
            // Chấp nhận cả đường dẫn tương đối (/path) lẫn URL đầy đủ
            if ( $value !== '' && strpos( $value, '/' ) === 0 ) {
                $output['register_url'] = '/' . implode( '/', array_map( 'sanitize_text_field', explode( '/', ltrim( $value, '/' ) ) ) );
            } else {
                $output['register_url'] = esc_url_raw( $value );
            }
        }

        return $output;
    }

    /**
     * Render field Register URL.
     */
    public static function render_register_url_field(): void {
        $options      = get_option( self::OPTION_NAME, [] );
        $register_url = $options['register_url'] ?? '';
        ?>
        <input
            type="text"
            id="vnm_register_url"
            name="<?php echo esc_attr( self::OPTION_NAME ); ?>[register_url]"
            value="<?php echo esc_attr( $register_url ); ?>"
            class="regular-text"
            placeholder="/dang-ky-doi-tac hoặc https://example.com/dang-ky"
        />
        <p class="description">
            URL hoặc đường dẫn trang đăng ký dành cho đối tác (VD: <code>/dang-ky-doi-tac</code> hoặc <code>https://example.com/dang-ky</code>). Hiển thị trong thông báo "chưa có đối tác" khi người dùng chọn tỉnh thành chưa có dữ liệu.
        </p>
        <?php
    }

    /**
     * Render trang cài đặt.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::MENU_SLUG );
                submit_button( 'Lưu cài đặt' );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Lấy register URL — dùng cho filter 'vnm_register_url'.
     *
     * @return string
     */
    public static function get_register_url(): string {
        $options = get_option( self::OPTION_NAME, [] );
        $url     = $options['register_url'] ?? '';

        return ( $url !== '' ) ? $url : '#';
    }
}
