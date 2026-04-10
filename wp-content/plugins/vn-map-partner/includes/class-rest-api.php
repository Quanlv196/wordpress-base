<?php
/**
 * REST API endpoint: /wp-json/vn-map/v1/partners
 *
 * Response format:
 * {
 *   "VNM456": {
 *     "name": "Thanh Hóa",
 *     "partners": [
 *       { "name": "...", "type": "gold|silver", "address": "...", "phone": "..." }
 *     ]
 *   }
 * }
 */
defined( 'ABSPATH' ) || exit;

class VNM_REST_API {

    public static function register_routes(): void {
        register_rest_route( 'vn-map/v1', '/partners', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'get_partners' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Trả về danh sách đối tác gom nhóm theo tỉnh thành.
     * Kết quả được cache trong transient để tăng hiệu năng.
     */
    public static function get_partners( WP_REST_Request $request ): WP_REST_Response {
        // Thử lấy từ cache
        $cached = get_transient( VNM_CACHE_KEY );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $query = new WP_Query( [
            'post_type'      => 'province_partner',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ] );

        $result = [];

        foreach ( $query->posts as $post ) {
            $province_code = get_post_meta( $post->ID, '_vnm_province_code', true );
            $province_name = get_post_meta( $post->ID, '_vnm_province_name', true );
            $partner_type  = get_post_meta( $post->ID, '_vnm_partner_type',  true ) ?: 'silver';
            $address       = get_post_meta( $post->ID, '_vnm_address',        true );
            $phone         = get_post_meta( $post->ID, '_vnm_phone',           true );

            // Bỏ qua nếu thiếu mã tỉnh
            if ( empty( $province_code ) ) {
                continue;
            }

            // Sanitize output (dữ liệu đã được sanitize khi lưu)
            $province_code = sanitize_text_field( $province_code );
            $province_name = sanitize_text_field( $province_name );
            $partner_type  = in_array( $partner_type, [ 'gold', 'silver' ], true ) ? $partner_type : 'silver';

            if ( ! isset( $result[ $province_code ] ) ) {
                $result[ $province_code ] = [
                    'name'     => $province_name,
                    'partners' => [],
                ];
            }

            $result[ $province_code ]['partners'][] = [
                'name'    => sanitize_text_field( $post->post_title ),
                'type'    => $partner_type,
                'address' => sanitize_text_field( $address ),
                'phone'   => sanitize_text_field( $phone ),
            ];
        }

        // Lưu cache 1 giờ
        set_transient( VNM_CACHE_KEY, $result, VNM_CACHE_TTL );

        return rest_ensure_response( $result );
    }
}
