<?php
/**
 * Shortcode handlers.
 *
 * Registers three shortcodes:
 *   [custom_products]              – Product listing grid.
 *   [custom_product_detail id="N"] – Single product detail.
 *   [custom_checkout]              – Cart summary + checkout form.
 *
 * All HTML is output-escaped. Dynamic content is populated client-side via
 * the companion main.js, keeping server-rendered markup to a minimum.
 *
 * @package NTQ_Custom_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NCO_Shortcode {

    public function __construct() {
        add_shortcode( 'custom_products',       [ $this, 'render_products_list' ] );
        add_shortcode( 'custom_product_detail', [ $this, 'render_product_detail' ] );
        add_shortcode( 'custom_checkout',       [ $this, 'render_checkout' ] );
    }

    // -----------------------------------------------------------------------
    // [custom_products]
    // -----------------------------------------------------------------------

    public function render_products_list(): string {
        ob_start();
        ?>
        <div class="nco-wrapper nco-products-page" id="nco-products-wrapper"
             data-detail-url="<?php echo esc_attr( (string) get_option( 'nco_detail_page_url', '' ) ); ?>">
            <div class="nco-products-layout">

                <!-- ── Filter sidebar (col-xl-3 / col-md-4) ── -->
                <div class="nco-filter-sidebar" id="nco-filter-sidebar">
                    <div class="nco-filter-header">
                        <h3 class="nco-filter-title"><?php esc_html_e( 'Bộ lọc', 'ntq-custom-order' ); ?></h3>
                        <button type="button" id="nco-filter-reset" class="nco-filter-reset">
                            <?php esc_html_e( 'Xoá bộ lọc', 'ntq-custom-order' ); ?>
                        </button>
                    </div>

                    <!-- Category -->
                    <div class="nco-filter-group">
                        <h4 class="nco-filter-group-title"><?php esc_html_e( 'Danh mục', 'ntq-custom-order' ); ?></h4>
                        <div class="nco-filter-group-body" id="nco-category-list">
                            <span class="nco-filter-loading"><?php esc_html_e( 'Đang tải…', 'ntq-custom-order' ); ?></span>
                        </div>
                    </div>

                    <!-- Price range -->
                    <div class="nco-filter-group">
                        <h4 class="nco-filter-group-title"><?php esc_html_e( 'Khoảng giá', 'ntq-custom-order' ); ?></h4>
                        <div class="nco-filter-group-body">
                            <div class="nco-price-inputs">
                                <input type="number"
                                       id="nco-price-min"
                                       class="nco-price-input"
                                       placeholder="<?php esc_attr_e( 'Từ', 'ntq-custom-order' ); ?>"
                                       min="0" step="0.01" />
                                <span class="nco-price-sep">&ndash;</span>
                                <input type="number"
                                       id="nco-price-max"
                                       class="nco-price-input"
                                       placeholder="<?php esc_attr_e( 'Đến', 'ntq-custom-order' ); ?>"
                                       min="0" step="0.01" />
                            </div>
                            <button type="button" id="nco-price-apply"
                                    class="nco-btn nco-btn-secondary nco-btn-sm nco-btn-block">
                                <?php esc_html_e( 'Áp dụng', 'ntq-custom-order' ); ?>
                            </button>
                        </div>
                    </div>
                </div><!-- .nco-filter-sidebar -->

                <!-- ── Products main area ── -->
                <div class="nco-products-main">
                    <div class="nco-loading" id="nco-products-loading">
                        <span class="nco-spinner"></span>
                        <?php esc_html_e( 'Đang tải sản phẩm…', 'ntq-custom-order' ); ?>
                    </div>

                    <div class="nco-error nco-hidden" id="nco-products-error"></div>

                    <div class="nco-products-toolbar nco-hidden" id="nco-products-toolbar">
                        <span class="nco-products-count" id="nco-products-count"></span>
                    </div>

                    <div class="nco-products-grid nco-hidden" id="nco-products-grid"></div>

                    <div class="nco-pagination nco-hidden" id="nco-pagination"></div>
                </div><!-- .nco-products-main -->

            </div><!-- .nco-products-layout -->
        </div>
        <?php
        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // [custom_product_detail id="123"]
    // -----------------------------------------------------------------------

    public function render_product_detail( array $atts ): string {
        $atts = shortcode_atts( [ 'id' => '' ], $atts, 'custom_product_detail' );

        // Priority: shortcode attribute → clean-URL path query var → URL query-string parameter.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $product_id = ! empty( $atts['id'] )
            ? absint( $atts['id'] )
            : ( absint( get_query_var( 'nco_product_id', 0 ) )
                ?: absint( $_GET['product_id'] ?? 0 ) );

        $checkout_url = esc_url( (string) get_option( 'nco_checkout_url', '' ) );

        ob_start();
        ?>
        <div class="nco-wrapper nco-product-detail-page"
             id="nco-product-detail-wrapper"
             data-product-id="<?php echo esc_attr( $product_id ); ?>"
             data-checkout-url="<?php echo esc_attr( $checkout_url ); ?>">

            <div class="nco-loading" id="nco-detail-loading">
                <span class="nco-spinner"></span>
                <?php esc_html_e( 'Loading product\u2026', 'ntq-custom-order' ); ?>
            </div>

            <div class="nco-error nco-hidden" id="nco-detail-error"></div>

            <div class="nco-product-detail-inner nco-hidden" id="nco-product-detail-inner"></div>

            <div class="nco-alert nco-alert-success nco-hidden" id="nco-cart-added">
                <span><?php esc_html_e( 'Product added to cart!', 'ntq-custom-order' ); ?></span>
                <a href="<?php echo esc_attr( $checkout_url ); ?>"
                   id="nco-go-checkout"
                   class="nco-link">
                    <?php esc_html_e( 'Go to Checkout &rarr;', 'ntq-custom-order' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // [custom_checkout]
    // -----------------------------------------------------------------------

    public function render_checkout(): string {
        ob_start();
        ?>
        <div class="nco-wrapper nco-checkout-page" id="nco-checkout-wrapper">

            <!-- ── Order confirmation (shown after successful submission) ── -->
            <div class="nco-order-confirmation nco-hidden" id="nco-order-confirmation" role="alert">
                <div class="nco-confirmation-icon" aria-hidden="true">&#10003;</div>
                <h2><?php esc_html_e( 'Order Placed Successfully!', 'ntq-custom-order' ); ?></h2>
                <p id="nco-order-id-msg"></p>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
                   class="nco-btn nco-btn-secondary">
                    <?php esc_html_e( 'Continue Shopping', 'ntq-custom-order' ); ?>
                </a>
            </div>

            <!-- ── Main checkout content ── -->
            <div id="nco-checkout-content">
                <div class="nco-checkout-columns">

                    <!-- Cart summary -->
                    <div class="nco-checkout-col nco-cart-summary">
                        <h2 class="nco-section-title">
                            <?php esc_html_e( 'Order Summary', 'ntq-custom-order' ); ?>
                        </h2>

                        <div id="nco-cart-items-list"></div>

                        <div class="nco-cart-total-row nco-hidden" id="nco-cart-total-row">
                            <span><?php esc_html_e( 'Total:', 'ntq-custom-order' ); ?></span>
                            <strong id="nco-cart-total"></strong>
                        </div>

                        <div class="nco-empty-cart nco-hidden" id="nco-empty-cart">
                            <p><?php esc_html_e( 'Your cart is empty.', 'ntq-custom-order' ); ?></p>
                            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
                               class="nco-btn nco-btn-secondary">
                                <?php esc_html_e( 'Browse Products', 'ntq-custom-order' ); ?>
                            </a>
                        </div>
                    </div><!-- .nco-cart-summary -->

                    <!-- Checkout form -->
                    <div class="nco-checkout-col nco-checkout-form-col nco-hidden" id="nco-form-col">
                        <h2 class="nco-section-title">
                            <?php esc_html_e( 'Your Information', 'ntq-custom-order' ); ?>
                        </h2>

                        <form id="nco-checkout-form" novalidate>
                            <div class="nco-field">
                                <label for="nco_name">
                                    <?php esc_html_e( 'Full Name', 'ntq-custom-order' ); ?>
                                    <span class="nco-required" aria-hidden="true">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="nco_name"
                                    name="customer_name"
                                    placeholder="<?php esc_attr_e( 'Your full name', 'ntq-custom-order' ); ?>"
                                    required
                                    autocomplete="name"
                                />
                            </div>

                            <div class="nco-field">
                                <label for="nco_phone">
                                    <?php esc_html_e( 'Phone Number', 'ntq-custom-order' ); ?>
                                    <span class="nco-required" aria-hidden="true">*</span>
                                </label>
                                <input
                                    type="tel"
                                    id="nco_phone"
                                    name="customer_phone"
                                    placeholder="<?php esc_attr_e( 'e.g. 0912 345 678', 'ntq-custom-order' ); ?>"
                                    required
                                    autocomplete="tel"
                                />
                            </div>

                            <div class="nco-field">
                                <label for="nco_address">
                                    <?php esc_html_e( 'Delivery Address', 'ntq-custom-order' ); ?>
                                    <span class="nco-required" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="nco_address"
                                    name="customer_address"
                                    rows="3"
                                    placeholder="<?php esc_attr_e( 'Street, City, Province', 'ntq-custom-order' ); ?>"
                                    required
                                    autocomplete="street-address"
                                ></textarea>
                            </div>

                            <div class="nco-error nco-hidden" id="nco-checkout-error" role="alert"></div>

                            <button type="submit" id="nco-submit-btn" class="nco-btn nco-btn-primary">
                                <span class="nco-btn-text">
                                    <?php esc_html_e( 'Place Order', 'ntq-custom-order' ); ?>
                                </span>
                                <span class="nco-btn-spinner nco-hidden">
                                    <span class="nco-spinner"></span>
                                </span>
                            </button>
                        </form>
                    </div><!-- .nco-checkout-form-col -->

                </div><!-- .nco-checkout-columns -->
            </div><!-- #nco-checkout-content -->

        </div><!-- .nco-checkout-page -->
        <?php
        return ob_get_clean();
    }
}
