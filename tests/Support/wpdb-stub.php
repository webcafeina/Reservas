<?php
/**
 * Minimal stub of the WordPress `wpdb` class, used in unit tests that do
 * not boot WordPress itself. Only the members we actually touch are declared.
 *
 * @phpstan-ignore-next-line ignore for ci unit run
 */

declare(strict_types=1);

// Defined at global namespace so `\wpdb` resolves in production and tests.
if ( ! class_exists( 'wpdb' ) ) {
    // phpcs:disable
    class wpdb {
        /** @var string */
        public $prefix = 'wp_';

        /** @var int */
        public $insert_id = 0;

        public function prepare( string $query, $args ): string {
            return $query;
        }

        /**
         * @param mixed $query
         * @param int   $output
         * @return array<int, array<string, mixed>>|object|null
         */
        public function get_results( $query, $output = 0 ) {
            return array();
        }

        /**
         * @param string $query
         * @return int|false
         */
        public function query( $query ) {
            return 0;
        }

        /**
         * @param string               $table
         * @param array<string, mixed> $data
         * @param array<int, string>|string|null $format
         * @return int|false
         */
        public function insert( $table, array $data, $format = null ) {
            return 1;
        }

        /**
         * @param string               $table
         * @param array<string, mixed> $data
         * @param array<string, mixed> $where
         * @param array<int, string>|string|null $format
         * @param array<int, string>|string|null $where_format
         * @return int|false
         */
        public function update( $table, array $data, array $where, $format = null, $where_format = null ) {
            return 1;
        }

        /**
         * @param string $query
         * @return mixed
         */
        public function get_var( $query ) {
            return null;
        }

        /**
         * @param string $query
         * @param int    $output
         * @return array<string, mixed>|object|null
         */
        public function get_row( $query, $output = 0 ) {
            return null;
        }

        /**
         * @param string               $table
         * @param array<string, mixed> $where
         * @param array<int, string>|string|null $where_format
         * @return int|false
         */
        public function delete( $table, array $where, $where_format = null ) {
            return 1;
        }
    }
    // phpcs:enable
}
