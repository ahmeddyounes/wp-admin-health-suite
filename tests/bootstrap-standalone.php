<?php
/**
 * PHPUnit bootstrap file for standalone unit tests (no WordPress required)
 *
 * @package WPAdminHealth\Tests
 */

// Define test environment constants.
define( 'WP_ADMIN_HEALTH_TESTS_DIR', __DIR__ );
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// WordPress function stubs for standalone tests.
if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub - returns the string as-is.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Translation stub with HTML escaping.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * HTML escaping stub.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Parse arguments stub.
	 *
	 * @param array $args     Arguments to parse.
	 * @param array $defaults Default values.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Echo translation stub.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused).
	 * @return void
	 */
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Attribute escaping stub.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Translation stub with attribute escaping.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Translation with context stub.
	 *
	 * @param string $text    Text to translate.
	 * @param string $context Context (unused).
	 * @param string $domain  Text domain (unused).
	 * @return string
	 */
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Pluralization stub.
	 *
	 * @param string $single Single form.
	 * @param string $plural Plural form.
	 * @param int    $number Number for pluralization.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitize text field stub.
	 *
	 * @param string $str Text to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return strip_tags( trim( $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Absolute integer stub.
	 *
	 * @param mixed $value Value to convert.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Remove slashes from string stub.
	 *
	 * @param string|array $value Value to unslash.
	 * @return string|array
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'stripslashes_deep' ) ) {
	/**
	 * Deep stripslashes stub.
	 *
	 * @param mixed $value Value to process.
	 * @return mixed
	 */
	function stripslashes_deep( $value ) {
		return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Current user capability check stub.
	 *
	 * @param string $capability Capability to check.
	 * @return bool Always returns true for testing.
	 */
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Admin check stub.
	 *
	 * @return bool Always returns true for testing.
	 */
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode wrapper stub.
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options JSON options.
	 * @param int   $depth   Maximum depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply filters stub - returns value unchanged.
	 *
	 * @param string $hook_name Filter hook name.
	 * @param mixed  $value     Value to filter.
	 * @param mixed  ...$args   Additional arguments.
	 * @return mixed The filtered value (unchanged in stub).
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Do action stub - does nothing.
	 *
	 * @param string $hook_name Action hook name.
	 * @param mixed  ...$args   Additional arguments.
	 * @return void
	 */
	function do_action( $hook_name, ...$args ) {
		// No-op in tests.
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Add filter stub - does nothing.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of arguments.
	 * @return true
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Add action stub - does nothing.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Number of arguments.
	 * @return true
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * URL escaping stub.
	 *
	 * @param string $url URL to escape.
	 * @return string Escaped URL.
	 */
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Get option stub - returns default.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed Default value.
	 */
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Update option stub - always succeeds.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool Always true.
	 */
	function update_option( $option, $value ) {
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Delete option stub - always succeeds.
	 *
	 * @param string $option Option name.
	 * @return bool Always true.
	 */
	function delete_option( $option ) {
		return true;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	/**
	 * SQL escaping stub.
	 *
	 * @param string|array $data Data to escape.
	 * @return string|array Escaped data.
	 */
	function esc_sql( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'esc_sql', $data );
		}
		return addslashes( (string) $data );
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Remove filter stub - always succeeds.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback function.
	 * @param int      $priority  Priority.
	 * @return bool Always true.
	 */
	function remove_filter( $hook_name, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * Remove action stub - always succeeds.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback function.
	 * @param int      $priority  Priority.
	 * @return bool Always true.
	 */
	function remove_action( $hook_name, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Has filter stub - always returns false.
	 *
	 * @param string        $hook_name Hook name.
	 * @param callable|bool $callback  Callback function or false.
	 * @return bool|int Always false.
	 */
	function has_filter( $hook_name, $callback = false ) {
		return false;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	/**
	 * Has action stub - always returns false.
	 *
	 * @param string        $hook_name Hook name.
	 * @param callable|bool $callback  Callback function or false.
	 * @return bool|int Always false.
	 */
	function has_action( $hook_name, $callback = false ) {
		return false;
	}
}

if ( ! function_exists( 'site_url' ) ) {
	/**
	 * Site URL stub.
	 *
	 * @param string $path   Path relative to site URL.
	 * @param string $scheme Scheme (unused).
	 * @return string Site URL.
	 */
	function site_url( $path = '', $scheme = null ) {
		return 'http://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Home URL stub.
	 *
	 * @param string $path   Path relative to home URL.
	 * @param string $scheme Scheme (unused).
	 * @return string Home URL.
	 */
	function home_url( $path = '', $scheme = null ) {
		return 'http://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Admin URL stub.
	 *
	 * @param string $path   Path relative to admin URL.
	 * @param string $scheme Scheme (unused).
	 * @return string Admin URL.
	 */
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'http://example.com/wp-admin' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Plugins URL stub.
	 *
	 * @param string $path   Path relative to plugins URL.
	 * @param string $plugin Plugin file path (unused).
	 * @return string Plugins URL.
	 */
	function plugins_url( $path = '', $plugin = '' ) {
		return 'http://example.com/wp-content/plugins' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Nonce field stub.
	 *
	 * @param int|string $action  Action name.
	 * @param string     $name    Nonce name.
	 * @param bool       $referer Whether to add referer field.
	 * @param bool       $echo    Whether to echo or return.
	 * @return string Hidden input field.
	 */
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test_nonce" />';
		if ( $echo ) {
			echo $field;
		}
		return $field;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Nonce verification stub - always valid.
	 *
	 * @param string     $nonce  Nonce value.
	 * @param int|string $action Action name.
	 * @return int|false Always returns 1 (valid).
	 */
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 1;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Admin referer check stub - always valid.
	 *
	 * @param int|string $action    Action name.
	 * @param string     $query_arg Query argument name.
	 * @return int Always returns 1.
	 */
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return 1;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * Return true callback.
	 *
	 * @return bool True.
	 */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * Return false callback.
	 *
	 * @return bool False.
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( '__return_empty_array' ) ) {
	/**
	 * Return empty array callback.
	 *
	 * @return array Empty array.
	 */
	function __return_empty_array() {
		return array();
	}
}

if ( ! function_exists( '__return_null' ) ) {
	/**
	 * Return null callback.
	 *
	 * @return null Null.
	 */
	function __return_null() {
		return null;
	}
}

if ( ! function_exists( '__return_zero' ) ) {
	/**
	 * Return zero callback.
	 *
	 * @return int Zero.
	 */
	function __return_zero() {
		return 0;
	}
}

if ( ! function_exists( '__return_empty_string' ) ) {
	/**
	 * Return empty string callback.
	 *
	 * @return string Empty string.
	 */
	function __return_empty_string() {
		return '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Check if value is WP_Error stub.
	 *
	 * @param mixed $thing Value to check.
	 * @return bool True if WP_Error, false otherwise.
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Get transient stub - always returns false.
	 *
	 * @param string $transient Transient name.
	 * @return mixed False by default.
	 */
	function get_transient( $transient ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Set transient stub - always succeeds.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Expiration in seconds.
	 * @return bool Always true.
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Delete transient stub - always succeeds.
	 *
	 * @param string $transient Transient name.
	 * @return bool Always true.
	 */
	function delete_transient( $transient ) {
		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanitize key stub.
	 *
	 * @param string $key Key to sanitize.
	 * @return string Sanitized key.
	 */
	function sanitize_key( $key ) {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Sanitize title stub.
	 *
	 * @param string $title    Title to sanitize.
	 * @param string $fallback Fallback title (unused).
	 * @param string $context  Context (unused).
	 * @return string Sanitized title.
	 */
	function sanitize_title( $title, $fallback = '', $context = 'save' ) {
		$title = strip_tags( $title );
		$title = preg_replace( '/[^a-zA-Z0-9\s\-_]/', '', $title );
		$title = str_replace( ' ', '-', $title );
		return strtolower( trim( $title, '-' ) );
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	/**
	 * WP cache get stub - always returns false.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @param bool   $force Whether to force update.
	 * @param bool   $found Whether key was found (passed by reference).
	 * @return mixed False by default.
	 */
	function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/**
	 * WP cache set stub - always succeeds.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $data   Data to cache.
	 * @param string $group  Cache group.
	 * @param int    $expire Expiration in seconds.
	 * @return bool Always true.
	 */
	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * WP cache delete stub - always succeeds.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool Always true.
	 */
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Multisite check stub.
	 *
	 * @return bool Always false for testing.
	 */
	function is_multisite() {
		return false;
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	/**
	 * Get current blog ID stub.
	 *
	 * @return int Always 1.
	 */
	function get_current_blog_id() {
		return 1;
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	/**
	 * Pluck values from array of arrays/objects.
	 *
	 * @param array      $list      Array to pluck from.
	 * @param string|int $field     Field to pluck.
	 * @param string|int $index_key Optional index key.
	 * @return array Plucked values.
	 */
	function wp_list_pluck( $list, $field, $index_key = null ) {
		$results = array();
		foreach ( $list as $item ) {
			$value = is_object( $item ) ? $item->$field : $item[ $field ];
			if ( null !== $index_key ) {
				$key = is_object( $item ) ? $item->$index_key : $item[ $index_key ];
				$results[ $key ] = $value;
			} else {
				$results[] = $value;
			}
		}
		return $results;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Add trailing slash stub.
	 *
	 * @param string $string String to trail.
	 * @return string String with trailing slash.
	 */
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Remove trailing slash stub.
	 *
	 * @param string $string String to untrail.
	 * @return string String without trailing slash.
	 */
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	/**
	 * Get current user stub.
	 *
	 * @return object Mock user object.
	 */
	function wp_get_current_user() {
		return (object) array(
			'ID'           => 1,
			'user_login'   => 'admin',
			'user_email'   => 'admin@example.com',
			'display_name' => 'Admin User',
		);
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WP_Error stub class.
	 */
	class WP_Error {
		/**
		 * Error codes.
		 *
		 * @var array
		 */
		public $errors = array();

		/**
		 * Error data.
		 *
		 * @var array
		 */
		public $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		/**
		 * Get error codes.
		 *
		 * @return array Error codes.
		 */
		public function get_error_codes() {
			return array_keys( $this->errors );
		}

		/**
		 * Get first error code.
		 *
		 * @return string|int Error code.
		 */
		public function get_error_code() {
			$codes = $this->get_error_codes();
			return ! empty( $codes ) ? $codes[0] : '';
		}

		/**
		 * Get error messages.
		 *
		 * @param string|int $code Error code.
		 * @return array Error messages.
		 */
		public function get_error_messages( $code = '' ) {
			if ( empty( $code ) ) {
				$messages = array();
				foreach ( $this->errors as $error_messages ) {
					$messages = array_merge( $messages, $error_messages );
				}
				return $messages;
			}
			return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
		}

		/**
		 * Get first error message.
		 *
		 * @param string|int $code Error code.
		 * @return string Error message.
		 */
		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			$messages = $this->get_error_messages( $code );
			return ! empty( $messages ) ? $messages[0] : '';
		}

		/**
		 * Get error data.
		 *
		 * @param string|int $code Error code.
		 * @return mixed Error data.
		 */
		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}

		/**
		 * Add error.
		 *
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Error data.
		 */
		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		/**
		 * Check if errors exist.
		 *
		 * @return bool True if errors exist.
		 */
		public function has_errors() {
			return ! empty( $this->errors );
		}
	}
}

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Register test namespace autoloader for Mocks and other test classes.
spl_autoload_register(
	function ( $class ) {
		$prefix = 'WPAdminHealth\\Tests\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		// Map namespace components to directories.
		$path_parts = explode( '\\', $relative_class );
		$file_name  = array_pop( $path_parts );

		// Try original case first (for directories like Mocks), then lowercase.
		$file_candidates = array();

		// Build path with original case.
		$file_path = WP_ADMIN_HEALTH_TESTS_DIR;
		if ( ! empty( $path_parts ) ) {
			$file_path .= '/' . implode( '/', $path_parts );
		}
		$file_candidates[] = $file_path . '/' . $file_name . '.php';

		// Build path with lowercase directories.
		$file_path_lower = WP_ADMIN_HEALTH_TESTS_DIR;
		if ( ! empty( $path_parts ) ) {
			$file_path_lower .= '/' . implode( '/', array_map( 'strtolower', $path_parts ) );
		}
		$file_candidates[] = $file_path_lower . '/' . $file_name . '.php';

		// Try each candidate path.
		foreach ( $file_candidates as $file ) {
			if ( file_exists( $file ) ) {
				require $file;
				return;
			}
		}
	}
);

// Load standalone test case.
require_once WP_ADMIN_HEALTH_TESTS_DIR . '/StandaloneTestCase.php';
