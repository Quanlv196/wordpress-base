<?php
/**
 * Admin settings page.
 *
 * Registers a Settings sub-page under Settings > NTQ Custom Order.
 * All saves are nonce-verified and all inputs are sanitised before storage.
 *
 * @package NTQ_Custom_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NCO_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }

    // -----------------------------------------------------------------------
    // Menu registration
    // -----------------------------------------------------------------------

    public function add_menu(): void {
        add_options_page(
            __( 'NTQ Custom Order', 'ntq-custom-order' ),
            __( 'NTQ Custom Order', 'ntq-custom-order' ),
            'manage_options',
            'ntq-custom-order',
            [ $this, 'render_page' ]
        );
    }

    // -----------------------------------------------------------------------
    // Settings page renderer
    // -----------------------------------------------------------------------

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ntq-custom-order' ) );
        }

        $notice_type    = '';
        $notice_message = '';

        // Handle form save.
        if ( isset( $_POST['nco_save'] ) ) {
            $raw_nonce = isset( $_POST['nco_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['nco_nonce'] ) )
                : '';

            if ( ! wp_verify_nonce( $raw_nonce, 'nco_save_settings' ) ) {
                $notice_type    = 'error';
                $notice_message = __( 'Security check failed. Please refresh and try again.', 'ntq-custom-order' );
            } else {
                update_option( 'nco_api_endpoint',  esc_url_raw( wp_unslash( $_POST['nco_api_endpoint']  ?? '' ) ) );
                update_option( 'nco_api_token',     sanitize_text_field( wp_unslash( $_POST['nco_api_token']     ?? '' ) ) );
                update_option( 'nco_checkout_url',  esc_url_raw( wp_unslash( $_POST['nco_checkout_url']  ?? '' ) ) );
                update_option( 'nco_detail_page_url', esc_url_raw( wp_unslash( $_POST['nco_detail_page_url'] ?? '' ) ) );
                flush_rewrite_rules();

                $notice_type    = 'success';
                $notice_message = __( 'Settings saved.', 'ntq-custom-order' );
            }
        }

        $endpoint     = (string) get_option( 'nco_api_endpoint', '' );
        $token        = (string) get_option( 'nco_api_token', '' );
        $checkout_url = (string) get_option( 'nco_checkout_url', '' );
        $detail_url   = (string) get_option( 'nco_detail_page_url', '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'NTQ Custom Order — Settings', 'ntq-custom-order' ); ?></h1>

            <?php if ( $notice_message ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice_message ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'nco_save_settings', 'nco_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <!-- API Endpoint -->
                    <tr>
                        <th scope="row">
                            <label for="nco_api_endpoint">
                                <?php esc_html_e( 'API Endpoint URL', 'ntq-custom-order' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="nco_api_endpoint"
                                name="nco_api_endpoint"
                                value="<?php echo esc_attr( $endpoint ); ?>"
                                class="regular-text"
                                placeholder="https://fakestoreapi.com"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Base URL of the Mock API. The plugin appends /products, /products/{id}, and /orders automatically.', 'ntq-custom-order' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- API Token -->
                    <tr>
                        <th scope="row">
                            <label for="nco_api_token">
                                <?php esc_html_e( 'API Authorization Token', 'ntq-custom-order' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="nco_api_token"
                                name="nco_api_token"
                                value="<?php echo esc_attr( $token ); ?>"
                                class="regular-text"
                                placeholder="your-bearer-token"
                                autocomplete="off"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Sent as: Authorization: Bearer {token}. Leave blank if the API does not require authentication.', 'ntq-custom-order' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Checkout Page URL -->
                    <tr>
                        <th scope="row">
                            <label for="nco_checkout_url">
                                <?php esc_html_e( 'Checkout Page URL', 'ntq-custom-order' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="nco_checkout_url"
                                name="nco_checkout_url"
                                value="<?php echo esc_attr( $checkout_url ); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr( home_url( '/checkout/' ) ); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Full URL of the page containing [custom_checkout]. Used for the "Go to Checkout" link on product detail pages.', 'ntq-custom-order' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Product Detail Page URL -->
                    <tr>
                        <th scope="row">
                            <label for="nco_detail_page_url">
                                <?php esc_html_e( 'Product Detail Page URL', 'ntq-custom-order' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="nco_detail_page_url"
                                name="nco_detail_page_url"
                                value="<?php echo esc_attr( $detail_url ); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr( home_url( '/products/' ) ); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Full URL of the page containing [custom_product_detail]. Product links will use the format {this-url}/{id}, e.g. /products/15. Save settings to apply the rewrite rule.', 'ntq-custom-order' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Settings', 'ntq-custom-order' ), 'primary', 'nco_save' ); ?>
            </form>

            <hr />

            <!-- Shortcode reference -->
            <h2><?php esc_html_e( 'Available Shortcodes', 'ntq-custom-order' ); ?></h2>
            <table class="widefat striped" style="max-width:720px">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Shortcode', 'ntq-custom-order' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'ntq-custom-order' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[custom_products]</code></td>
                        <td><?php esc_html_e( 'Product listing grid fetched from the API.', 'ntq-custom-order' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[custom_product_detail id="1"]</code></td>
                        <td><?php esc_html_e( 'Single product detail. The id attribute can be omitted — the shortcode will read ?product_id= from the URL.', 'ntq-custom-order' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[custom_checkout]</code></td>
                        <td><?php esc_html_e( 'Cart summary + checkout form. Submits the order to the API via AJAX.', 'ntq-custom-order' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Quick Setup Guide', 'ntq-custom-order' ); ?></h2>
            <ol style="max-width:720px;line-height:1.8;">
                <li><?php esc_html_e( 'Set the API Endpoint URL above (e.g. https://fakestoreapi.com).', 'ntq-custom-order' ); ?></li>
                <li><?php esc_html_e( 'Create three WordPress pages: Products, Product Detail, Checkout.', 'ntq-custom-order' ); ?></li>
                <li><?php esc_html_e( 'Add [custom_products] to the Products page.', 'ntq-custom-order' ); ?></li>
                <li><?php esc_html_e( 'Add [custom_product_detail] to the Product Detail page.', 'ntq-custom-order' ); ?></li>
                <li><?php esc_html_e( 'Add [custom_checkout] to the Checkout page, then paste its URL into the Checkout Page URL field above.', 'ntq-custom-order' ); ?></li>
                <li><?php esc_html_e( 'Save settings. Done!', 'ntq-custom-order' ); ?></li>
            </ol>
        </div>
        <?php
    }
}
