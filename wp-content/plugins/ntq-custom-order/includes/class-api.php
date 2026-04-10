<?php
/**
 * API integration layer.
 *
 * Wraps all remote HTTP calls to the configured mock API endpoint.
 * Uses wp_remote_get / wp_remote_post so WordPress handles SSL, proxies,
 * and timeouts automatically.
 *
 * @package NTQ_Custom_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NCO_API {

    /** @var string Base URL from plugin settings. */
    private string $endpoint;

    /** @var string Bearer token from plugin settings. */
    private string $token;

    /** @var int HTTP request timeout in seconds. */
    private int $timeout = 30;

    public function __construct() {
        $this->endpoint = (string) get_option( 'nco_api_endpoint', '' );
        $this->token    = (string) get_option( 'nco_api_token', '' );
    }

    // -----------------------------------------------------------------------
    // Public API methods
    // -----------------------------------------------------------------------

    /**
     * GET /products
     *
     * @param array $query_params Optional query-string params, e.g. ['limit'=>20].
     * @return array{success:bool,data:mixed,message?:string}
     */
    public function get_products( array $query_params = [] ): array {
        $url = $this->build_url( 'products' );
        if ( ! empty( $query_params ) ) {
            $url = add_query_arg( $query_params, $url );
        }
        return $this->get( $url );
    }

    /**
     * GET /products/category/{category}
     *
     * Used when the API natively supports category-level filtering.
     * Falls back to get_products() with query-param if the endpoint 404s.
     *
     * @param string $category
     * @param array  $query_params Additional query-string params.
     * @return array{success:bool,data:mixed,message?:string}
     */
    public function get_products_by_category( string $category, array $query_params = [] ): array {
        $url = $this->build_url( 'products/category/' . rawurlencode( $category ) );
        if ( ! empty( $query_params ) ) {
            $url = add_query_arg( $query_params, $url );
        }
        $result = $this->get( $url );

        // If the dedicated endpoint is not supported, fall back to
        // a generic /products call with category as a query param.
        if ( ! $result['success'] ) {
            $result = $this->get_products( array_merge( $query_params, [ 'category' => $category ] ) );
        }

        return $result;
    }

    /**
     * GET /products/categories
     *
     * Returns the list of categories supported by the API.
     *
     * @return array{success:bool,data:mixed,message?:string}
     */
    public function get_categories(): array {
        return $this->get( $this->build_url( 'products/categories' ) );
    }

    /**
     * GET /products/{id}
     *
     * @param int $id Product ID.
     * @return array{success:bool,data:mixed,message?:string}
     */
    public function get_product( int $id ): array {
        return $this->get( $this->build_url( 'products/' . $id ) );
    }

    /**
     * POST /orders
     *
     * @param array $order_data Sanitised order payload.
     * @return array{success:bool,data:mixed,message?:string}
     */
    public function post_order( array $order_data ): array {
        return $this->post( $this->build_url( 'orders' ), $order_data );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function build_url( string $path ): string {
        return trailingslashit( $this->endpoint ) . ltrim( $path, '/' );
    }

    private function default_args(): array {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ( ! empty( $this->token ) ) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return [
            'timeout' => $this->timeout,
            'headers' => $headers,
        ];
    }

    private function get( string $url ): array {
        $response = wp_remote_get( $url, $this->default_args() );
        return $this->parse_response( $response );
    }

    private function post( string $url, array $body ): array {
        $args         = $this->default_args();
        $args['body'] = wp_json_encode( $body );

        $response = wp_remote_post( $url, $args );
        return $this->parse_response( $response );
    }

    /**
     * Normalise a wp_remote_* response into a consistent array shape.
     *
     * @param array|\WP_Error $response
     * @return array{success:bool,data:mixed,message?:string}
     */
    private function parse_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            $this->log( 'WP_Error: ' . $msg );

            return [
                'success' => false,
                'message' => $msg,
                'data'    => null,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code >= 200 && $code < 300 ) {
            return [
                'success' => true,
                'data'    => $data,
            ];
        }

        /* translators: %d = HTTP status code returned by the remote API. */
        $message = sprintf( __( 'API request failed with HTTP %d.', 'ntq-custom-order' ), $code );
        $this->log( $message . ' Response body: ' . $body );

        return [
            'success' => false,
            'message' => $message,
            'data'    => $data,
        ];
    }

    /**
     * Write a debug entry when WP_DEBUG_LOG is enabled.
     *
     * @param string $message
     */
    private function log( string $message ): void {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[NTQ Custom Order] ' . $message );
        }
    }
}
