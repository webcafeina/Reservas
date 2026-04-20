<?php
/**
 * Stubs for WordPress classes our unit tests need to type against but which
 * we don't boot a full WP stack to get.
 *
 * Integration tests use the real WordPress via WP-Browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/wpdb-stub.php';

if ( ! class_exists( 'WP_Error' ) ) {
    // phpcs:disable
    class WP_Error {
        /** @var array<string, array<int, string>> */
        public $errors = array();

        /** @var array<string, mixed> */
        public $error_data = array();

        /**
         * @param string|int $code
         * @param mixed      $data
         */
        public function __construct( $code = '', string $message = '', $data = '' ) {
            if ( $code !== '' && $code !== 0 ) {
                $this->errors[ (string) $code ] = array( $message );
                if ( $data !== '' ) {
                    $this->error_data[ (string) $code ] = $data;
                }
            }
        }

        public function get_error_code(): string {
            $keys = array_keys( $this->errors );
            return $keys[0] ?? '';
        }

        public function get_error_message( string $code = '' ): string {
            if ( $code === '' ) {
                $code = $this->get_error_code();
            }
            return $this->errors[ $code ][0] ?? '';
        }

        public function has_errors(): bool {
            return $this->errors !== array();
        }
    }
    // phpcs:enable
}

if ( ! class_exists( 'WP_Post' ) ) {
    // phpcs:disable
    class WP_Post {
        /** @var int */
        public $ID = 0;
        /** @var string */
        public $post_title = '';
        /** @var string */
        public $post_content = '';
        /** @var string */
        public $post_excerpt = '';
        /** @var string */
        public $post_name = '';
        /** @var string */
        public $post_status = 'publish';
        /** @var string */
        public $post_type = 'post';

        /**
         * @param array<string, mixed> $props
         */
        public function __construct( array $props = array() ) {
            foreach ( $props as $k => $v ) {
                $this->{$k} = $v;
            }
        }
    }
    // phpcs:enable
}

if ( ! class_exists( 'WP_Term' ) ) {
    // phpcs:disable
    class WP_Term {
        /** @var int */
        public $term_id = 0;
        /** @var string */
        public $name = '';
        /** @var string */
        public $slug = '';

        /**
         * @param array<string, mixed> $props
         */
        public function __construct( array $props = array() ) {
            foreach ( $props as $k => $v ) {
                $this->{$k} = $v;
            }
        }
    }
    // phpcs:enable
}
