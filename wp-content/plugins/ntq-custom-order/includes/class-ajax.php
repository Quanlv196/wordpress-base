<?php
/**
 * AJAX handlers.
 *
 * All three AJAX actions are available to both logged-in and guest users
 * so the shop works without requiring a WordPress account.
 *
 * Every handler:
 *   1. Verifies the nonce from nco_params.nonce (set in main.js via wp_localize_script).
 *   2. Sanitises every request parameter before use.
 *   3. Delegates the HTTP call to NCO_API.
 *   4. Returns a JSON response via wp_send_json_success / wp_send_json_error.
 *
 * @package NTQ_Custom_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NCO_Ajax {

    public function __construct() {
        $actions = [
            'nco_fetch_products',
            'nco_fetch_categories',
            'nco_fetch_product_detail',
            'nco_submit_order',
        ];

        foreach ( $actions as $action ) {
            $method = substr( $action, 4 );
            add_action( 'wp_ajax_'        . $action, [ $this, $method ] );
            add_action( 'wp_ajax_nopriv_' . $action, [ $this, $method ] );
        }
    }

    // -----------------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------------

    /**
     * Verify the AJAX nonce sent by the browser.
     * Terminates with HTTP 403 on failure.
     */
    private function verify_nonce(): void {
        $nonce = isset( $_REQUEST['nonce'] )
            ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'nco_ajax_nonce' ) ) {
            wp_send_json_error(
                [ 'message' => __( 'Security check failed. Please refresh and try again.', 'ntq-custom-order' ) ],
                403
            );
        }
    }

    /** Return a fresh NCO_API instance. */
    private function api(): NCO_API {
        return new NCO_API();
    }

    // -----------------------------------------------------------------------
    // Handler: fetch_categories
    // Action:  nco_fetch_categories
    // -----------------------------------------------------------------------

    public function fetch_categories(): void {
        $this->verify_nonce();

        $result = $this->api()->get_categories();

        if ( $result['success'] ) {
            wp_send_json_success( $result['data'] );
        } else {
            // If the API has no /products/categories endpoint, fall back to
            // fetching all products and extracting unique categories server-side.
            $fallback = $this->api()->get_products();
            if ( $fallback['success'] && is_array( $fallback['data'] ) ) {
                $cats = [];
                foreach ( $fallback['data'] as $product ) {
                    $cat = $product['category'] ?? $product['type'] ?? '';
                    if ( $cat && ! in_array( $cat, $cats, true ) ) {
                        $cats[] = $cat;
                    }
                }
                wp_send_json_success( $cats );
            } else {
                wp_send_json_error( [ 'message' => __( 'Failed to fetch categories.', 'ntq-custom-order' ) ] );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Handler: fetch_products
    // Action:  nco_fetch_products
    //
    // Accepted POST params:
    //   category  (string)  – filter by category via API
    //   price_min (float)   – minimum price (applied server-side if API ignores it)
    //   price_max (float)   – maximum price (same)
    //   per_page  (int)     – items per page hint sent to API (default 100)
    // -----------------------------------------------------------------------

    public function fetch_products(): void {
        $this->verify_nonce();

        $category  = isset( $_POST['category'] )  ? sanitize_text_field( wp_unslash( $_POST['category'] ) )  : '';
        $price_min = isset( $_POST['price_min'] ) ? abs( (float) $_POST['price_min'] )                       : null;
        $price_max = isset( $_POST['price_max'] ) ? abs( (float) $_POST['price_max'] )                       : null;

        // Ask for a large limit so pagination is done client-side (JS handles
        // 15-per-page display). Pass limit so APIs that support it return more.
        $query_params = [ 'limit' => 100 ];

        if ( ! empty( $category ) ) {
            $result = $this->api()->get_products_by_category( $category, $query_params );
        } else {
            $result = $this->api()->get_products( $query_params );
        }

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ?? __( 'Failed to fetch products.', 'ntq-custom-order' ) ] );
            return;
        }

        $products = is_array( $result['data'] ) ? $result['data'] : [];

        // Server-side price filter (API may not support price range natively).
        if ( $price_min !== null || $price_max !== null ) {
            $products = array_values( array_filter( $products, function ( $p ) use ( $price_min, $price_max ) {
                $price = (float) ( $p['price'] ?? 0 );
                if ( $price_min !== null && $price < $price_min ) { return false; }
                if ( $price_max !== null && $price > $price_max ) { return false; }
                return true;
            } ) );
        }

        wp_send_json_success( $products );
    }

    // -----------------------------------------------------------------------
    // Handler: fetch_product_detail
    // Action:  nco_fetch_product_detail
    // -----------------------------------------------------------------------

    public function fetch_product_detail(): void {
        $this->verify_nonce();

        $product_id = isset( $_REQUEST['product_id'] )
            ? absint( $_REQUEST['product_id'] )
            : 0;

        if ( $product_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'ntq-custom-order' ) ] );
        }

        $result = $this->api()->get_product( $product_id );

        if ( $result['success'] ) {
            wp_send_json_success( $result['data'] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ?? __( 'Failed to fetch product.', 'ntq-custom-order' ) ] );
        }
    }

    // -----------------------------------------------------------------------
    // Handler: submit_order
    // Action:  nco_submit_order
    // -----------------------------------------------------------------------

    public function submit_order(): void {
        $this->verify_nonce();

        // -- Sanitise customer fields -----------------------------------------
        $name    = isset( $_POST['customer_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) )
            : '';
        $phone   = isset( $_POST['customer_phone'] )
            ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) )
            : '';
        $address = isset( $_POST['customer_address'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ) )
            : '';

        if ( empty( $name ) || empty( $phone ) || empty( $address ) ) {
            wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'ntq-custom-order' ) ] );
        }

        // -- Decode & validate cart ------------------------------------------
        $cart_raw  = isset( $_POST['cart_items'] ) ? wp_unslash( $_POST['cart_items'] ) : '[]';
        $cart_data = json_decode( $cart_raw, true );

        if ( ! is_array( $cart_data ) || empty( $cart_data ) ) {
            wp_send_json_error( [ 'message' => __( 'Your cart is empty.', 'ntq-custom-order' ) ] );
        }

        // -- Sanitise each cart item -----------------------------------------
        $sanitised_items = [];
        foreach ( $cart_data as $item ) {
            $id  = absint( $item['id'] ?? 0 );
            $qty = absint( $item['quantity'] ?? 1 );

            if ( $id <= 0 || $qty < 1 ) {
                continue;
            }

            $sanitised_items[] = [
                'product_id' => $id,
                'name'       => sanitize_text_field( $item['name'] ?? '' ),
                'price'      => round( abs( (float) ( $item['price'] ?? 0 ) ), 2 ),
                'quantity'   => $qty,
            ];
        }

        if ( empty( $sanitised_items ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid cart data.', 'ntq-custom-order' ) ] );
        }

        // -- Build order payload ----------------------------------------------
        $total = array_sum(
            array_map(
                fn( $i ) => $i['price'] * $i['quantity'],
                $sanitised_items
            )
        );

        $order_data = [
            'customer' => [
                'name'    => $name,
                'phone'   => $phone,
                'address' => $address,
            ],
            'items'    => $sanitised_items,
            'total'    => round( $total, 2 ),
        ];

        // -- Send to API -------------------------------------------------------
        $result = $this->api()->post_order( $order_data );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message'  => __( 'Order placed successfully!', 'ntq-custom-order' ),
                'order_id' => $result['data']['id'] ?? null,
                'order'    => $result['data'],
            ] );
        } else {
            wp_send_json_error( [
                'message' => $result['message'] ?? __( 'Failed to submit order. Please try again.', 'ntq-custom-order' ),
            ] );
        }
    }
}
