<?php
/**
 * API module.
 * Handles all functions that are executing remote calls.
 */

/**
 * The main API class.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly      

class Cloud_Uploads_Api_Handler {
  
	/**
	 * The API server.
	 *ś
	 * @var string (URL)
	 */
	public $server_root = 'https://s3uploads.com/';

	/**
	 * Path to the REST API on the server.
	 *
	 * @var string (URL)
	 */
	protected $rest_api = 'api/v1/';

	/**
	 * The complete REST API endpoint. Defined in constructor.
	 *
	 * @var string (URL)
	 */
	protected $server_url = '';

	/**
	 * Stores the API token used for authentication.
	 *
	 * @var string
	 */
	protected $api_token = '';

	/**
	 * Stores the site_id from the API.
	 *
	 * @var int
	 */
	protected $api_site_id = '';

	/**
	 * Holds the last API error that occured (if any)
	 *
	 * @var string
	 */
	public $api_error = '';

	/**
	 * Set up the API module.
	 *
	 * @internal
	 */
	public function __construct() {

		if ( defined( 'CLOUD_UPLOADS_CUSTOM_API_SERVER' ) ) {
			$this->server_root = trailingslashit( CLOUD_UPLOADS_CUSTOM_API_SERVER );
		}
		$this->server_url = $this->server_root . $this->rest_api;

		$this->api_token   = get_site_option( 'cloud_uploads_apitoken' );
		$this->api_site_id = get_site_option( 'cloud_uploads_site_id' );

		// Schedule automatic data update on the main site of the network.
		if ( is_main_site() ) {
			if ( ! wp_next_scheduled( 'cloud_uploads_sync' ) ) {
				wp_schedule_event( time(), 'daily', 'cloud_uploads_sync' );
			}

			add_action( 'cloud_uploads_sync', [ $this, 'get_site_data' ] );
			add_action( 'wp_ajax_nopriv_cloud-uploads-refresh', [ &$this, 'remote_refresh' ] );
		}
	}

	/**
	 * Returns true if the API token is defined.
	 *
	 * @return bool
	 */
	public function has_token() {
		return ! empty( $this->api_token );
	}

	/**
	 * Returns the API token.
	 *
	 * @return string
	 */
	public function get_token() {
		return $this->api_token;
	}

	/**
	 * Updates the API token in the database.
	 *
	 * @param string $token The new API token to store.
	 */
	public function set_token( $token ) {
		$this->api_token = $token;
		update_site_option( 'cloud_uploads_apitoken', $token );
	}

	/**
	 * Returns the site_id.
	 *
	 * @return int
	 */
	public function get_site_id() {
		return $this->api_site_id;
	}

	/**
	 * Updates the API site_id in the database.
	 *
	 * @param int $site_id The new site_id to store.
	 */
	public function set_site_id( $site_id ) {
		$this->api_site_id = $site_id;
		update_site_option( 'cloud_uploads_site_id', $site_id );
	}

		/**
	 * Returns the canonical site_url that should be used for the site on the site.
	 *
	 * Define CLOUD_UPLOADS_SITE_URL to override or make static the url it should show as
	 *  in the site. Defaults to network_site_url() which may be dynamically filtered
	 *  by some plugins and hosting providers.
	 *
	 * @return string
	 */
	public function network_site_url() {
		return defined( 'CLOUD_UPLOADS_SITE_URL' ) ? CLOUD_UPLOADS_SITE_URL : network_site_url();
	}

	
	/**
	 * Get site data from API, normally cached for 12hrs.
	 *
	 * @param bool $force_refresh
	 *
	 * @return mixed|void
	 */
	public function get_site_data( $force_refresh = false ) {

		if ( ! $this->has_token() || ! $this->get_site_id() ) {
			return false;
		}

		if ( ! $force_refresh ) {
			$data = get_site_option( 'cloud_uploads_api_data' );
			if ( $data ) {
				$data = json_decode( $data );

				if ( $data->refreshed >= ( time() - HOUR_IN_SECONDS * 12 ) ) {
					return $data;
				}
			}
		}


		$result = $this->call( "site/" . $this->get_site_id(), [], 'GET' );
		if ( $result ) {
			$result->refreshed = time();
			//json_encode to prevent object injections
			update_site_option( 'cloud_uploads_api_data', json_encode( $result ) );

			return $result;
		}

		return $data; //if a temp API issue default to using cached data
	}

		/**
	 * Perform the initial Oauth activation for API.
	 *
	 * @param $temp_token
	 *
	 * @return bool
	 */
	public function authorize( $temp_token ) {
		$result = $this->call( 'token', [ 'temp_token' => $temp_token ], 'POST' );
		if ( $result ) {
			$this->set_token( $result->api_token );
			$this->set_site_id( $result->site_id );

			return $this->get_site_data( true );
		}

		return false;
	}

	/**
	 * Returns the canonical home_url that should be used for the site on the site.
	 *
	 * Define CLOUD_UPLOADS_HUB_HOME_URL to override or make static the url it should show as
	 *  in the site. Defaults to network_home_url() which may be dynamically filtered
	 *  by some plugins and hosting providers.
	 *
	 * @return string
	 */
	public function network_home_url() {
		if ( defined( 'CLOUD_UPLOADS_HOME_URL' ) ) {
			return CLOUD_UPLOADS_HOME_URL;
		} else {
			return network_home_url();
		}
	}

	/**
	 * Returns the full URL to the specified REST API endpoint.
	 *
	 * This is a function instead of making the property $server_url public so
	 * we have better control and overview of the requested pages:
	 * It's easy to add a filter or add extra URL params to all URLs this way.
	 *
	 * @param string $endpoint The endpoint to call on the server.
	 *
	 * @return string The full URL to the requested endpoint.
	 */
	public function rest_url( $endpoint ) {
		if ( preg_match( '!^https?://!', $endpoint ) ) {
			$url = $endpoint;
		} else {
			$url = $this->server_url . $endpoint;
		}

		return $url;
	}

		/**
	 * Makes an API call and returns the results.
	 *
	 * @param string $remote_path The API function to call.
	 * @param array  $data        Optional. GET or POST data to send.
	 * @param string $method      Optional. GET or POST.
	 * @param array  $options     Optional. Array of request options.
	 *
	 * @return object|boolean Results of the API call response body.
	 */
	public function call( $remote_path, $data = [], $method = 'GET', $options = [] ) {
		$link = $this->rest_url( $remote_path );

		$options = wp_parse_args(
			$options,
			[
				'timeout'    => 25,
				'user-agent' => 'Cloud Uploads/' . CLOUD_UPLOADS_VERSION . ' (+' . network_site_url() . ')',
				'headers'    => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
			]
		);

		if ( $this->has_token() ) {
			$options['headers']['Authorization'] = 'Bearer ' . $this->get_token();
		}

		if ( 'GET' == $method ) {
			if ( ! empty( $data ) ) {
				$link = add_query_arg( $data, $link );
			}
			$response = wp_remote_get( $link, $options );
		} elseif ( 'POST' == $method ) {
			$options['body'] = json_encode( $data );
			$response        = wp_remote_post( $link, $options );
		}

		// Add the request-URL to the response data.
		if ( $response && is_array( $response ) ) {
			$response['request_url'] = $link;
		}

		if ( defined( 'CLOUD_UPLOADS_API_DEBUG' ) && CLOUD_UPLOADS_API_DEBUG ) {
			$log = '[CLOUD_UPLOADS API call] %s | %s: %s (%s)';
			if ( defined( 'CLOUD_UPLOADS_API_DEBUG_ALL' ) && CLOUD_UPLOADS_API_DEBUG_ALL ) {
				$log .= "\nRequest options: %s\nResponse: %s";
			}

			$resp_body = wp_remote_retrieve_body( $response );

			if ( $response && is_array( $response ) ) {
				$debug_data = sprintf( "%s %s\n", wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_response_message( $response ) );
				$debug_data .= var_export( wp_remote_retrieve_headers( $response ), true ) . PHP_EOL; // WPCS: var_export() ok.
				$debug_data .= $resp_body;
			} else {
				$debug_data = '';
			}

			$msg = sprintf(
				$log,
				CLOUD_UPLOADS_VERSION,
				$method,
				$link,
				wp_remote_retrieve_response_code( $response ),
				wp_json_encode( $options ),
				$debug_data
			);
			error_log( $msg );
		}

		//if there is an auth problem
		if ( $this->has_token() && in_array( wp_remote_retrieve_response_code( $response ), [ 401, 403, 404 ] ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			if ( isset( $body->code ) && in_array( $body->code, [ 'missing_api_token', 'invalid_site', 'invalid_api_key' ] ) ) {
				$this->set_token( '' );
			}
		}

		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			if ( ! isset( $options['blocking'] ) || false !== $options['blocking'] ) {
				$this->parse_api_error( $response );
			}

			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( json_last_error() ) {
			$this->parse_api_error( json_last_error_msg() );

			return false;
		}

		return $body;
	}

	/**
	 * Parses an HTTP response object (or other value) to determine an error
	 * reason. The error reason is added to the PHP error log.
	 *
	 * @param string|WP_Error|array $response String, WP_Error object, HTTP response array.
	 */
	protected function parse_api_error( $response ) {
		$error_code = wp_remote_retrieve_response_code( $response );
		if ( ! $error_code ) {
			$error_code = 500;
		}
		$this->api_error = '';

		$body = is_array( $response )
			? wp_remote_retrieve_body( $response )
			: false;

		if ( is_scalar( $response ) ) {
			$this->api_error = $response;
		} elseif ( is_wp_error( $response ) ) {
			$this->api_error = $response->get_error_message();
		} elseif ( is_array( $response ) && ! empty( $body ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$this->api_error = $data['message'];
			}
		}

		$url = '(unknown URL)';
		if ( is_array( $response ) && isset( $response['request_url'] ) ) {
			$url = $response['request_url'];
		}

		if ( empty( $this->api_error ) ) {
			$this->api_error = sprintf(
				'HTTP Error: %s "%s"',
				$error_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		// Collect back-trace information for the logfile.
		$caller_dump = '';
		if ( defined( 'CLOUD_UPLOADS_API_DEBUG' ) && CLOUD_UPLOADS_API_DEBUG ) {
			$trace     = debug_backtrace();
			$caller    = [];
			$last_line = '';
			foreach ( $trace as $level => $item ) {
				if ( ! isset( $item['class'] ) ) {
					$item['class'] = '';
				}
				if ( ! isset( $item['type'] ) ) {
					$item['type'] = '';
				}
				if ( ! isset( $item['function'] ) ) {
					$item['function'] = '<function>';
				}
				if ( ! isset( $item['line'] ) ) {
					$item['line'] = '?';
				}

				if ( $level > 0 ) {
					$caller[] = $item['class'] .
					            $item['type'] .
					            $item['function'] .
					            ':' . $last_line;
				}
				$last_line = $item['line'];
			}
			$caller_dump = "\n\t# " . implode( "\n\t# ", $caller );

			if ( is_array( $response ) && isset( $response['request_url'] ) ) {
				$caller_dump = "\n\tURL: " . $response['request_url'] . $caller_dump;
			}
		}

		// Log the error to PHP error log.
		error_log(
			sprintf(
				'[CLOUD_UPLOADS API Error] %s | %s (%s [%s]) %s',
				CLOUD_UPLOADS_VERSION,
				$this->api_error,
				$url,
				$error_code,
				$caller_dump
			),
			0
		);
	}
  
}