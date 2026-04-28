<?php
/**
 * Template shortcode: [vn_map_partner]
 * Render toàn bộ giao diện bản đồ đối tác Việt Nam.
 *
 * Biến có sẵn từ class-shortcode.php:
 *   $atts['height'] - chiều cao canvas bản đồ
 *   $atts['title']  - tiêu đề (tuỳ chọn)
 */
defined( 'ABSPATH' ) || exit;

$map_height     = isset( $atts['height'] ) ? sanitize_text_field( $atts['height'] ) : '500px';
$provinces_json = isset( $provinces_json ) ? $provinces_json : '[]';

// Ảnh huy hiệu từ assets/images/ (đặt file badge-gold.png / badge-silver.png vào thư mục này)
$badge_gold_url   = file_exists( VNM_PATH . 'assets/images/doi-tac-vang.svg' )   ? VNM_URL . 'assets/images/doi-tac-vang.svg'   : null;
$badge_silver_url = file_exists( VNM_PATH . 'assets/images/doi-tac-bac.svg' ) ? VNM_URL . 'assets/images/doi-tac-bac.svg' : null;
?>

<div class="vn-map-wrapper" id="vnm-wrapper-<?php echo esc_attr( uniqid() ); ?>">

    <script>window.VNM_PROVINCES = <?php echo $provinces_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_json_encode output ?>;</script>

    <div class="vn-map-row">

        <!-- ====================== Cột trái: Canvas bản đồ ====================== -->
        <div class="vn-map-col vn-map-canvas-col">
            <div id="vn_map" style="min-height:<?php echo esc_attr( $map_height ); ?>;"></div>
        </div>

        <!-- ====================== Cột phải: Legend + Select + Kết quả ====================== -->
        <div class="vn-map-col vn-map-info-col">

            <!-- Ghi chú hướng dẫn -->
            <p class="vn-map-hint">
                Vui lòng chỉ chuột hoặc kích vào tỉnh thành trên bản đồ mà bạn muốn tra cứu.
                Đối với các đối tác chưa được hiển thị trên bản đồ đối tác,
                vui lòng liên hệ để được giải đáp.
            </p>

            <!-- Legend -->
            <div class="vn-map-legend">

                <!-- Đối tác vàng -->
                <div class="vn-map-legend-item">
                    <span class="vn-legend-color vn-legend-gold"></span>
                    <div class="vn-legend-info">
                        <div class="vn-legend-icon">
                            <?php if ( $badge_gold_url ) : ?>
                                <img src="<?php echo esc_url( $badge_gold_url ); ?>" alt="Đối tác vàng" width="38" height="46" />
                            <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 60" width="38" height="46">
                                <circle cx="25" cy="22" r="18" fill="#f5c518" stroke="#d4a900" stroke-width="2"/>
                                <polygon points="25,12 27.9,19.6 36,20.2 30,25.4 32.1,33.4 25,28.9 17.9,33.4 20,25.4 14,20.2 22.1,19.6" fill="#fff"/>
                                <line x1="18" y1="40" x2="13" y2="58" stroke="#f5c518" stroke-width="3" stroke-linecap="round"/>
                                <line x1="32" y1="40" x2="37" y2="58" stroke="#f5c518" stroke-width="3" stroke-linecap="round"/>
                                <line x1="10" y1="58" x2="40" y2="58" stroke="#f5c518" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Đối tác vàng</strong>
                            <span>(Trực tiếp hỗ trợ, tư vấn khách hàng)</span>
                        </div>
                    </div>
                </div>

                <!-- Đối tác bạc -->
                <div class="vn-map-legend-item">
                    <span class="vn-legend-color vn-legend-silver"></span>
                    <div class="vn-legend-info">
                        <div class="vn-legend-icon">
                            <?php if ( $badge_silver_url ) : ?>
                                <img src="<?php echo esc_url( $badge_silver_url ); ?>" alt="Đối tác bạc" width="38" height="46" />
                            <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 60" width="38" height="46">
                                <circle cx="25" cy="22" r="18" fill="#aaaaaa" stroke="#888" stroke-width="2"/>
                                <polygon points="25,12 27.9,19.6 36,20.2 30,25.4 32.1,33.4 25,28.9 17.9,33.4 20,25.4 14,20.2 22.1,19.6" fill="#fff"/>
                                <line x1="18" y1="40" x2="13" y2="58" stroke="#aaaaaa" stroke-width="3" stroke-linecap="round"/>
                                <line x1="32" y1="40" x2="37" y2="58" stroke="#aaaaaa" stroke-width="3" stroke-linecap="round"/>
                                <line x1="10" y1="58" x2="40" y2="58" stroke="#aaaaaa" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Đối tác bạc</strong>
                            <span>(Tư vấn, cầu nối liên lạc)</span>
                        </div>
                    </div>
                </div>

            </div><!-- /.vn-map-legend -->

            <!-- Dropdown tìm kiếm tỉnh thành -->
            <div class="vn-map-search">
                <select id="vn_map_province_select" class="vn-province-select" aria-label="Chọn tỉnh thành">
                    <option value="">-- Chọn tỉnh thành --</option>
                </select>
            </div>

            <!-- Khu vực hiển thị danh sách đối tác -->
            <div id="vn_map_description" class="vn-map-description" aria-live="polite">
                <p class="vn-map-placeholder">Kích vào một tỉnh thành trên bản đồ hoặc sử dụng ô tìm kiếm để xem danh sách đối tác.</p>
            </div>

        </div><!-- /.vn-map-info-col -->

    </div><!-- /.vn-map-row -->

</div><!-- /.vn-map-wrapper -->
