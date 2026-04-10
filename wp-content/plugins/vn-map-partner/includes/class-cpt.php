<?php
/**
 * Đăng ký Custom Post Type: province_partner
 */
defined( 'ABSPATH' ) || exit;

class VNM_CPT {

    public static function register(): void {
        $labels = [
            'name'               => 'Đối tác tỉnh thành',
            'singular_name'      => 'Đối tác',
            'menu_name'          => 'Bản đồ đối tác',
            'name_admin_bar'     => 'Đối tác',
            'add_new'            => 'Thêm mới',
            'add_new_item'       => 'Thêm đối tác mới',
            'new_item'           => 'Đối tác mới',
            'edit_item'          => 'Chỉnh sửa đối tác',
            'view_item'          => 'Xem đối tác',
            'all_items'          => 'Tất cả đối tác',
            'search_items'       => 'Tìm kiếm đối tác',
            'not_found'          => 'Không tìm thấy đối tác nào.',
            'not_found_in_trash' => 'Thùng rác trống.',
        ];

        register_post_type( 'province_partner', [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-location-alt',
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => false, // chỉ dùng qua REST API riêng của plugin
            'capability_type'    => 'post',
        ] );
    }
}
