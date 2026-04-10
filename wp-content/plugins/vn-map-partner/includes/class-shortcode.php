<?php
/**
 * Shortcode [vn_map_partner]
 * Render toàn bộ giao diện bản đồ đối tác.
 */
defined( 'ABSPATH' ) || exit;

class VNM_Shortcode {

    public static function register(): void {
        add_shortcode( 'vn_map_partner', [ self::class, 'render' ] );
    }

    /**
     * Render HTML cho shortcode.
     *
     * Tham số shortcode:
     *   height  - chiều cao canvas bản đồ (mặc định: 500px)
     *   title   - tiêu đề khu vực legend
     */
    public static function render( $atts ): string {
        $atts = shortcode_atts( [
            'height' => '500px',
            'title'  => '',
        ], $atts, 'vn_map_partner' );

        // Đảm bảo scripts được load khi shortcode được dùng trong builder
        if ( ! wp_script_is( 'vnm-map-init', 'enqueued' ) ) {
            self::enqueue_scripts();
        }

        ob_start();
        include VNM_PATH . 'templates/map-shortcode.php';
        return ob_get_clean();
    }

    /**
     * Enqueue tất cả scripts và styles cần thiết.
     */
    public static function enqueue_scripts(): void {
        // --- CSS ---
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_style(
            'vnm-styles',
            VNM_URL . 'assets/css/vn-map-partner.css',
            [ 'select2' ],
            VNM_VERSION
        );

        // --- JS simplemaps ---
        // mapdata.js phải load TRƯỚC countrymap.js
        // Không có dependency vì là thư viện thuần JavaScript
        wp_enqueue_script(
            'vnm-mapdata',
            VNM_URL . 'assets/js/mapdata.js',
            [],
            VNM_VERSION,
            true  // footer
        );

        // Đổi tên div target từ "map" (mặc định trong mapdata.js gốc) sang "vn_map"
        // để simplemaps render đúng element trong template của plugin.
        // Simplemaps dùng property "div" (không phải "map_div"),
        // cũng tắt state_url và bật auto_load để map tự render khi gọi .load().
        wp_add_inline_script(
            'vnm-mapdata',
            'if (typeof simplemaps_countrymap_mapdata !== "undefined") {
                simplemaps_countrymap_mapdata.main_settings = simplemaps_countrymap_mapdata.main_settings || {};
                simplemaps_countrymap_mapdata.main_settings.div        = "vn_map";
                simplemaps_countrymap_mapdata.main_settings.state_url  = "";
                simplemaps_countrymap_mapdata.main_settings.auto_load  = "no";
            }',
            'after'
        );

        wp_enqueue_script(
            'vnm-countrymap',
            VNM_URL . 'assets/js/countrymap.js',
            [ 'vnm-mapdata' ],
            VNM_VERSION,
            true
        );

        // --- Select2 JS ---
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.1.0',
            true
        );

        // --- Plugin JS ---
        wp_enqueue_script(
            'vnm-data-builder',
            VNM_URL . 'assets/js/map-data-builder.js',
            [ 'jquery' ],
            VNM_VERSION,
            true
        );

        wp_enqueue_script(
            'vnm-map-init',
            VNM_URL . 'assets/js/map-init.js',
            [ 'jquery', 'select2', 'vnm-mapdata', 'vnm-countrymap', 'vnm-data-builder' ],
            VNM_VERSION,
            true
        );

        // Truyền cấu hình sang JavaScript qua wp_localize_script
        wp_localize_script( 'vnm-map-init', 'VNM_CONFIG', [
            'rest_url'   => esc_url_raw( rest_url( 'vn-map/v1/partners' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'plugin_url' => VNM_URL,
            'version'    => VNM_VERSION,
        ] );
    }
}
