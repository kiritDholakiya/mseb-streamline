<?php
/**
 * Plugin Name: Streamline Addon for Magnetic Strategy Booking Engine
 * Plugin URI:
 * Author: Magnetic Strategy
 * Author URI:
 * Version: 1.0.0
 * Text Domain: streamline-mseb
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'MSBE_Streamline' ) ) {

    class MSBE_Streamline {
        /**
         * set VRB Engine name.
         *
         * @var string
         * @since 1.0.0
         */
        public $vrb_engine = 'streamline';

        /**
         * VRB Settings.
         *
         * @var array|VRB_Settings
         * @since 1.0.0
         */
        public $vrb_settings;
        public $api_key = '';
        public $secret_key = '';
        public $company_code = '';
        public $end_point = '';
        public $property_id;
        public $property_meta = array();
        public $unit_id;
        public $unit_meta = array();
        public $property_fetch = 1;
        public $total_property = '';
        public $api_url;

        public function __construct() {


            // Plugin Folder Path.
            if ( ! defined( 'GDVRB_STREAMLINE_DIR' ) ) {
                define( 'GDVRB_STREAMLINE_DIR', plugin_dir_path( __FILE__ ) );
            }
            // Plugin Folder URL.
            if ( ! defined( 'GDVRB_STREAMLINE_URL' ) ) {
                define( 'GDVRB_STREAMLINE_URL', plugin_dir_url( __FILE__ ) );
            }

            $this->vrb_settings = vrb_get_settings();
            $this->api_key      = get_option( 'streamline_token_key' );
            $this->secret_key   = get_option( 'streamline_token_secret' );
            $this->api_url      = $this->vrb_settings['booking_engines']['streamline']['streamline_endpoint'];
            $this->end_point    = rtrim( get_option( 'streamline_end_point' ), "/" );

            $this->includes();

            //add booking engines
            add_filter( 'vrb_available_booking_engines', array( $this, 'add_booking_engine' ), 10, 1 );

            //add strimliine settings
            add_filter( 'vrb_registered_settings', array( $this, 'settings' ), 10, 1 );

            // Import Properties
            add_action( 'wp_ajax_import_streamline_properties_list', array( $this, 'import_streamline_properties' ) );

            //sync single properties
            add_action( 'wp_ajax_sync_single_property', array( $this, 'sync_single_property_data' ) );

            //streamline_discount_pricing
            add_action( 'streamline_discount_pricing', array( $this, 'get_discount_pricing' ) );

            //show additional filed in admin penal
            add_filter( 'admin_property_information_fields', array( $this, 'show_additional_fields_in_admin' ), 10, 1 );

            //sync featured properties
            add_action( 'sync_streamline_featured_properties', array(
                    $this,
                    'sync_streamline_featured_properties_function'
            ) );

            //sync inactive properties
            add_action( 'sync_streamline_inactive_properties', array(
                    $this,
                    'sync_streamline_inactive_properties_function'
            ) );


            //frontend enque script
            add_action( 'wp_enqueue_scripts', array( $this, 'streamline_enqueue_scripts' ) );

            //admin enque script
            //add_action( 'admin_enqueue_scripts', array( $this, 'streamline_enqueue_admin_scripts') );

            //add coupon box
            add_action( 'checkout_properties_price_before', array(
                    $this,
                    'add_coupon_box_checkout_properties_price_before'
            ), 10 );

            //applaying coupon code
            add_action( 'wp_ajax_applying_coupon', array( $this, 'applying_coupon' ) );
            add_action( 'wp_ajax_nopriv_applying_coupon', array( $this, 'applying_coupon' ) );

            //Initialize Ribbon & Sync Meta Box
            add_action( 'add_meta_boxes', array( $this, 'streamline_meta_boxes' ) );

            //Save Ribbon Data
            add_action( 'save_post', array( $this, 'rbn_save_meta_box' ) );

            //sync single prtoperties
            add_action( 'wp_ajax_sync_single_property', array( $this, 'sync_single_property_data' ) );
            add_action( 'wp_ajax_nopriv_sync_single_property', array( $this, 'sync_single_property_data' ) );

            //Make reservation
            add_filter( 'vrb_booking_streamline_engine', array( $this, 'add_streamline_booking' ), 10, 3 );

            //checking property availability
            add_filter( 'vrb_check_' . $this->vrb_engine . '_property_availability', array(
                    $this,
                    'streamline_property_check_availability'
            ), 999, 2 );

            //stripe key for froendtend
            add_action( 'wp_ajax_vrb_process_checkout_stripe_key', array( $this, 'process_checkout_stripe_key' ) );
            add_action( 'wp_ajax_nopriv_vrb_process_checkout_stripe_key', array(
                    $this,
                    'process_checkout_stripe_key'
            ) );

            //save streamline booking engine key
            add_action( 'wp_ajax_save_streamline_api_key', array( $this, 'save_streamline_api_key' ) );
            add_action( 'wp_ajax_nopriv_save_streamline_api_key', array( $this, 'save_streamline_api_key' ) );

            // disconnect streamline key
            add_action( 'wp_ajax_disconnect_streamline_api_key', array( $this, 'disconnect_streamline_api_key' ) );
            add_action( 'wp_ajax_nopriv_disconnect_streamline_api_key', array(
                    $this,
                    'disconnect_streamline_api_key'
            ) );

            add_filter( 'cron_schedules', array( $this, 'set_custom_cron_schedule' ) );

          
            if ( ! wp_next_scheduled( 'sync_properties' ) ) {
                wp_schedule_event( strtotime('today 9:00 UTC'), 'daily', 'sync_properties' );
            }

            add_action( 'sync_properties', array( $this, 'sync_properties_function' ) );

            if ( ! wp_next_scheduled( 'sync_streamline_token' ) ) {
                wp_schedule_event( time(), 'every_six_hour', 'sync_streamline_token' );
            }

            add_action( 'sync_streamline_token', array( $this, 'sync_streamline_token' ) );

			if ( ! wp_next_scheduled( 'sync_streamline_delete_properties' ) ) {
                // Runs daily at midnight (server time)
                wp_schedule_event( time(), 'every_two_hours', 'sync_streamline_delete_properties' );
            }

			//sync delete properties
            add_action( 'sync_streamline_delete_properties', array( $this, 'sync_streamline_properties_to_site_function'));


            if ( ! wp_next_scheduled( 'sync_streamline_reviews_only' ) ) {
                wp_schedule_event( strtotime('today 6:00 UTC'), 'daily', 'sync_streamline_reviews_only' );
            }

            add_action( 'sync_streamline_reviews_only', array( $this, 'sync_reviews_only_function' ) );

        }

        public function sync_reviews_only_function( $args = array() ) {
            set_time_limit( -1 );

            $limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 10;
            $limit  = max( 1, min( 25, $limit ) );
            $dry_run = ! empty( $args['dry_run'] );
            $offset = (int) get_option( 'mseb_review_sync_offset', 0 );

            $result = array(
                    'limit'               => $limit,
                    'offset_before'       => $offset,
                    'offset_after'        => 0,
                    'total_publish_posts' => 0,
                    'processed_count'     => 0,
                    'processed_posts'     => array(),
                    'skipped_no_property' => array(),
                    'dry_run'             => $dry_run,
            );

            $count_posts = wp_count_posts( 'properties' );
            $total       = isset( $count_posts->publish ) ? (int) $count_posts->publish : 0;
            $result['total_publish_posts'] = $total;

            if ( $total <= 0 ) {
                update_option( 'mseb_review_sync_offset', 0 );
                return $result;
            }

            if ( $offset >= $total ) {
                $offset = 0;
                $result['offset_before'] = 0;
            }

            $properties = get_posts( array(
                    'fields'         => 'ids',
                    'posts_per_page' => $limit,
                    'offset'         => $offset,
                    'post_type'      => 'properties',
                    'post_status'    => 'publish',
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
            ) );

            // If offset points past the end due to content changes, restart from 0.
            if ( empty( $properties ) && $offset > 0 ) {
                $offset     = 0;
                $properties = get_posts( array(
                        'fields'         => 'ids',
                        'posts_per_page' => $limit,
                        'offset'         => 0,
                        'post_type'      => 'properties',
                        'post_status'    => 'publish',
                        'orderby'        => 'ID',
                        'order'          => 'ASC',
                        'no_found_rows'  => true,
                ) );
            }


            foreach ( $properties as $property_postid ) {
                $property_id = get_post_meta( $property_postid, 'property_id', true );

                if ( empty( $property_id ) ) {
                    $result['skipped_no_property'][] = (int) $property_postid;
                    continue;
                }
                if ( ! $dry_run ) {

                    $this->sync_property_review( $property_id, $property_postid );
                }
                $result['processed_posts'][] = array(
                        'post_id'     => (int) $property_postid,
                        'property_id' => (string) $property_id,
                );
            }

            $next_offset = $offset + count( $properties );
            if ( $next_offset >= $total ) {
                $next_offset = 0;
            }

            update_option( 'mseb_review_sync_offset', $next_offset );

            $result['processed_count'] = count( $result['processed_posts'] );
            $result['offset_after']    = $next_offset;
            $progress_processed        = $offset + count( $properties );
            if ( $progress_processed > $total ) {
                $progress_processed = $total;
            }
            $result['progress_percent'] = $total > 0 ? round( ( $progress_processed / $total ) * 100, 2 ) : 0;
            if ( $next_offset === 0 && ! empty( $properties ) ) {
                $result['progress_percent'] = 100;
            }

            $this->write_import_log( array(
                    'review_sync_batch' => $result,
            ) );

            return $result;
        }


        public function sync_streamline_token() {
            $this->write_import_log( "Cron is working" );

            $expiration_date = get_option( 'streamline_token_expiration' );
            if ( ! $expiration_date || $expiration_date == '' ) {
                $this->write_import_log( "No expiration date found, fetching from API..." );
                $response = $this->callStreamlineMagneticstrategyAPI( 'token-expiration' );
                $this->write_import_log( $response );

                if ( ! empty( $response['data']['expiration'] ) ) {
                    $expiration_date = $response['data']['expiration'];
                    update_option( 'streamline_token_expiration', $expiration_date );
                } else {
                    $this->write_import_log( "Expiration date not found in API response, renewing token anyway." );
                    $this->renewToken();

                    return;
                }
            }

            // Convert expiration date to timestamp
            $expiration_timestamp = strtotime( $expiration_date );

            // 2 days before expiration
            $renewal_threshold = $expiration_timestamp - ( 2 * DAY_IN_SECONDS );

            if ( time() >= $renewal_threshold ) {
                $this->write_import_log( "Token is within 2 days of expiration or expired, renewing..." );
                $this->renewToken();
            } else {
                $this->write_import_log( "Token is still valid, no renewal needed." );
            }
        }


        function sync_properties_function() {
            set_time_limit( - 1 );
            $this->write_import_log( 'Cron called' );
            $properties_per_hour = 10;

            $last_synced_index = (int) get_option( 'last_synced_property_index', 0 );

            $properties = get_posts( array(
                    'fields'         => 'ids', // Only get post IDs
                    'posts_per_page' => $properties_per_hour,
                    'post_type'      => 'properties',
                    'post_status'    => 'publish',
                    'offset'         => $last_synced_index, // Skip already synced properties
            ) );

            if ( ! empty( $properties ) ) {
                foreach ( $properties as $property_postid ) {
                    sleep( 5 ); // Delay to prevent server/API overload

                    $property_id   = get_post_meta( $property_postid, 'property_id', true );
                    $response_args = $this->sync_single_property( $property_id, $property_postid );
                }

                // Update the index of the last synced property
                $new_synced_index = $last_synced_index + count( $properties );
                update_option( 'last_synced_property_index', $new_synced_index );
            } else {
                // Reset the index if no properties are left to sync
                update_option( 'last_synced_property_index', 0 );
                error_log( "All properties have been synced. Resetting index to 0." );
            }
        }

        public function set_custom_cron_schedule( $schedules ) {
            $schedules['every_six_hour'] = array(
                    'interval' => 21600, // 6 hours in seconds
                    'display'  => __( 'Every 6 Hours' ),
            );

            $schedules['every_one_hour'] = array(
                    'interval' => 3600, // 1 hour in seconds
                    'display'  => __( 'Every 1 Hour' ),
            );

			$schedules['every_two_hours'] = array(
                    'interval' => 7200, // 1 hour in seconds
                    'display'  => __( 'Every 2 Hour' ),
            );

            return $schedules;
        }

        public function includes() {
            require_once GDVRB_STREAMLINE_DIR . 'includes/mseb-helper.php';
        }

        public function add_booking_engine( $engines ) {
            $engines['streamline'] = __( 'Streamline', 'streamline-mseb' );

            return $engines;
        }

        public function check_authentication() {

            $params          = array();
            $expiration_date = get_option( 'streamline_token_expiration' );
            $get_new_token   = 0;
            if ( ! $expiration_date || $expiration_date == '' ) {
                // $response = $this->callStreamlineMagneticstrategyAPI( 'check-authentication' );
                $response = $this->callStreamlineMagneticstrategyAPI( 'token-expiration' );
                if ( ! empty( $response['data']['expiration'] ) ) {
                    $expiration_date = $response['data']['expiration'];
                    update_option( 'streamline_token_expiration', $expiration_date );
                } else {
                    $get_new_token = 1;
                }
            }


            if ( time() >= strtotime( $expiration_date ) || $get_new_token == 1 ) {

                // if(!$this->renewToken()){
                // 	return false;
                // }
            }

            return true;
        }

        public function callStreamlineMagneticstrategyAPI( $route_name, $params = array() ) {

            $url = base64_encode( home_url() );

            // Define the Streamline endpoint with the route
            $streamline_endpoint = $this->api_url . $route_name . '?referer=' . $url;

            // referer=aHR0cHM6Ly9hcHJlc3JlbnRhbHMuY29t
            

            // Add an additional 'auth_key' parameter to validate the request
            $params['auth_key'] = base64_encode( '987589' );

            if ( ! empty( $this->secret_key ) ) {
                $params['token_secret'] = $this->secret_key;
                $params['token_key']    = $this->api_key;
            } else {
                $params['company_code'] = $this->company_code;
            }

            // Initialize the cURL request
            $curl = curl_init();

            curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type:application/x-www-form-urlencoded' ) );
            curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $params ) );  // Send data as form data
            curl_setopt( $curl, CURLOPT_POST, 1 );
            curl_setopt( $curl, CURLOPT_URL, $streamline_endpoint );
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

            // Execute the cURL request
            $result = curl_exec( $curl );

            if ( $result === false ) {
                return 'Curl error: ' . curl_error( $curl );
            } else {
                return json_decode( $result, true );
            }

            curl_close( $curl );
        }

        public function renewToken() {
            $params['token_secret'] = $this->secret_key;
            $params['token_key']    = $this->api_key;

            $response = $this->callStreamlineMagneticstrategyAPI( 'renew-expired-token', $params );

            if ( ! empty( $response['data'] ) ) {
                $token_key    = $response['data']['token_key'];
                $token_secret = $response['data']['token_secret'];

                $expiration_date = $response['data']['enddate'];

                update_option( 'streamline_token_expiration', $expiration_date );

                $settings                                                = vrb_get_settings();
                $settings['booking_engines']['streamline']['api_key']    = $token_key;
                $settings['booking_engines']['streamline']['secret_key'] = $token_secret;
                // update_option( "vrb_settings", $settings );
                update_option( "streamline_token_key", $token_key );
                update_option( "streamline_token_secret", $token_secret );

                return true;
            } else {
                $this->write_import_log( $response['status']['code'] . ': ' . $response['status']['description'] );

                return false;
            }
        }

        public function settings( $settings ) {
            $settings['tabs']['booking_engines']['sections']['streamline'] = array(
                    'name'   => __( 'Streamline', 'streamline-mseb' ),
                    'fields' => array(
                            'authentication'                  => array(
                                    'id'            => 'authentication',
                                    'name'          => '<h3>' . __( 'Authentication', 'streamline-mseb' ) . '</h3>',
                                    'desc'          => '',
                                    'type'          => 'header',
                                    'tooltip'       => true,
                                    'tooltip_title' => __( 'Streamline authentication settings', 'streamline-mseb' ),
                                    'tooltip_desc'  => __( 'This below settings for streamline authentication', 'streamline-mseb' ),
                            ),
                            'streamline_app_auth'             => array(
                                    'id'          => 'streamline_app_auth',
                                    'name'        => __( 'Connect To Streamline App', 'streamline-mseb' ),
                                    'desc'        => __( 'Connect To Streamline App.', 'streamline-mseb' ),
                                    'type'        => 'app_auth',
                                    'placeholder' => __( 'Enter Grant Code.', 'streamline-mseb' ),
                            ),
                            'streamline_endpoint'             => array(
                                    'id'          => 'streamline_endpoint',
                                    'name'        => __( 'Streamline Endpoint', 'streamline-mseb' ),
                                    'desc'        => __( 'This URL used to connect to site streamline.', 'streamline-mseb' ),
                                    'type'        => 'text',
                                    'placeholder' => __( 'Enter URL', 'streamline-mseb' ),
                            ),
                            'import'                          => array(
                                    'id'            => 'import',
                                    'name'          => '<h3>' . __( 'Import', 'streamline-mseb' ) . '</h3>',
                                    'desc'          => '',
                                    'type'          => 'header',
                                    'tooltip_title' => __( 'Import Settings', 'streamline-mseb' ),
                                    'tooltip_desc'  => __( 'This below settings for streamline import settings', 'streamline-mseb' ),
                            ),
                            'import_single_properties'        => array(
                                    'id'   => 'import_single_properties',
                                    'name' => __( 'Allow to import single properties', 'streamline-mseb' ),
                                    'type' => 'checkbox',
                            ),
                            'import_properties_automatically' => array(
                                    'id'   => 'import_properties_automatically',
                                    'name' => __( 'Allow to import properties automatically', 'streamline-mseb' ),
                                    'type' => 'checkbox',
                            ),
                            'use_streamshare'                 => array(
                                    'id'             => 'use_streamshare',
                                    'name'           => __( 'Use StreamsShare', 'streamline-mseb' ),
                                    'booking_engine' => $this->vrb_engine,
                                    'type'           => 'checkbox',
                            ),
                            'import_properties_list'          => array(
                                    'id'             => 'import_properties_list',
                                    'name'           => __( 'Property List', 'streamline-mseb' ),
                                    'booking_engine' => $this->vrb_engine,
                                    'type'           => 'import_properties_list',
                                    'button_label'   => __( 'Fetch Properties from PMS', 'guesty-mseb' ),
                            )
                    )
            );

            return $settings;
        }

        public function streamline_enqueue_admin_scripts() {
            // Register the JavaScript file for Admin
            wp_register_script( 'streamline-backend', GDVRB_STREAMLINE_URL . 'assets/js/streamline-backend.js', [ 'jquery' ], time() );

            // Localize the script with AJAX parameters for Admin
            wp_localize_script( 'streamline-backend', 'ajax_streamline_params', array(
                    'home_url'   => home_url(),
                    'ajax_url'   => admin_url( 'admin-ajax.php' ),
                    'ajax_nonce' => wp_create_nonce( 'script_js_nonce' )
            ) );

            // Enqueue the script for Admin
            wp_enqueue_script( 'streamline-backend' );
        }

        public function import_streamline_properties() {

            $authentication = $this->check_authentication();

            if ( ! $authentication ) {
                $this->write_import_log( 'auth failed' );
                wp_send_json( array(
                        'success' => false,
                        'msg'     => __( 'Authentication is not valid. Please check Authentication API Key and End point.' )
                ) );
            }
            global $vrb_options;
            $use_streamshare = get_option_value( 'use_streamshare', $vrb_options );
            $syncProperty_params = array(
                    "page_results_number" => '',
                    "page_number"         => '',
                    "use_streamshare" =>$use_streamshare
            );

            $response = $this->callStreamlineMagneticstrategyAPI( 'get-property-list', $syncProperty_params );

            // Initialize properties array
            $properties = [];

            if ( ! empty( $response['data']['property'] ) && ! empty( $response['data']['property'] ) ) {
                foreach ( $response['data']['property'] as $property ) {
                    $post_id      = vrb_check_property_exists( $property['id'] );
                    $properties[] = [
                            'property_id' => $property['id'],
                            'image'       => $property['default_thumbnail_path'],
                            'post_id'     => $post_id,
                            'name'        => $property['name'],
                            'last_sync'   => $post_id > 0 ? get_post_meta( $post_id, 'last_sync_date', true ) : '',
                    ];
                }
                // Update option with fetched properties
                update_option( "{$this->vrb_engine}_fetch_properties", $properties );
            } else {
                $msg = 'Property not fetched';
                // $msg = !empty($response['results']) ? 'Property not found' : 'call fail: ' . $response;
                if ( $response['status'] && $response['status']['code'] == 'E0012' ) {
                    $msg = $response['status']['description'];
                }
                wp_send_json( [
                        'success' => false,
                        'msg'     => __( $msg ),
                ] );
                exit;
            }

            // Send successful response
            wp_send_json( [
                    'success'    => true,
                    'properties' => $properties,
                    'msg'        => __( 'Fetch all properties' ),
            ] );
        }

        public function sync_single_property_data() {
            $property_post_id = $_POST['post_id'] ?? null;
            $property_id      = $_POST['property_id'] ?? '';
            $property_engine  = $_POST['engine'] ?? '';

            if ( empty( $property_id ) ) {
                $response_args['success'] = false;
                $response_args['message'] = __( 'Property not found.', 'vacation-rental-booking' );
            } else {
                $response_args = $this->sync_single_property( $property_id, $property_post_id );
            }

            wp_send_json( $response_args );

        }

        public function sync_single_property( $property_id, $post_id = 0 ) {
            if ( ! $this->check_authentication() ) {
                return [
                        'message' => __( 'Sorry, something went wrong. Please try later. Maybe the endpoint URL is not valid or no properties were found to sync.', 'vacation-rental-booking' ),
                        'success' => false,
                ];
            }

            if ( empty( $property_id ) ) {
                return [
                        'message' => __( 'Sorry, no property found to sync.', 'vacation-rental-booking' ),
                        'success' => false,
                ];
            }

            $params = array(
                "unit_id"                  => $property_id,
                "get_prices_starting_from" => true,
                "show_advance_date"        => true,
                "show_ota_amenities"       => true,
                "include_custom_fields"     => true
            );

            $property_response = $this->callStreamlineMagneticstrategyAPI( "get-property-info", $params );
        
			if (
				isset($property_response['status'], $property_response['status']['code']) 
				&& $property_response['status']['code'] === 'E0050'
			) {
				if ($post_id > 0) {
					$this->delete_property($post_id);
				}
			}
		

            if ( is_array( $property_response ) && ! empty( $property_response ) ) {
                if ( is_array( $property_response ) && ! empty( $property_response ) ) {
                    $property_post_id = $this->set_property( $property_response['data'], $post_id );
                    if ( $property_post_id && $property_post_id != "" ) {
                        $this->set_unit( $property_response['data'], $property_post_id );
                    }

                    return [
                            'message'   => __( 'Property sync completed.', 'vacation-rental-booking' ),
                            'success'   => true,
                            'last_sync' => get_post_meta( $property_post_id, 'last_sync_date', true ),
                    ];
                } else {
                    return [
                            'message'   => __( 'Property not synced.', 'vacation-rental-booking' ),
                            'success'   => false,
                            'last_sync' => get_post_meta( $post_id, 'last_sync_date', true ),
                    ];
                }
            } else {

            }
        }

        public function set_property( $property, $post_id = 0 ) 
        {

            $amenities_array = $location_array = $neighborhood_array = array();

            if ( isset( $property['unit_amenities'] ) && ! empty( $property['unit_amenities'] ) ) {
                foreach ( $property['unit_amenities']['amenity'] as $key => $amenities ) {


                    if ( $amenities['amenity_show_on_website'] == 'yes' ) {
                        $amenities_parent_name = $amenities['group_name'];
                        $amenities_parent_id   = 0;

                        $excluded_categories = apply_filters( 'custom_excluded_amenities', [] );
                        if ( in_array( $amenities_parent_name, $excluded_categories ) ) {
                            continue;
                        }

                        if ( term_exists( $amenities_parent_name, 'properties_amenities' ) === null ) {
                            $amenities_parent    = wp_insert_term( $amenities_parent_name, 'properties_amenities', array( 'slug' => sanitize_title( $amenities_parent_name ) ) );
                            $amenities_parent_id = $amenities_parent['term_id'];
                        } else {
                            $amenities_parent    = term_exists( $amenities_parent_name, 'properties_amenities' );
                            $amenities_parent_id = $amenities_parent['term_id'];
                        }
                        $amenities_array[] = $amenities_parent_id;

                        if ( term_exists( $amenities['amenity_name'], 'properties_amenities' ) === null ) { // array is returned if taxonomy is given
                            $amenities_category = wp_insert_term( $amenities['amenity_name'], 'properties_amenities', array(
                                    'slug'   => sanitize_title( $amenities['amenity_name'] ),
                                    'parent' => $amenities_parent_id
                            ) );
                        } else {
                            $amenities_category = term_exists( $amenities['amenity_name'], 'properties_amenities' );
                        }

                        $amenities_array[] = $amenities_category['term_id'];
                    }
                }
            }

            if ( isset( $property['propertyType'] ) && ! empty( $property['propertyType'] ) ) {
                $type = $property['propertyType'];
                if ( $type === 'Apartment' ) {
                    $type = 'Condo/Apt';
                }
                if ( term_exists( $type, 'properties_types' ) === null ) {
                    $action_cat = wp_insert_term( $type, 'properties_types', array( 'slug' => sanitize_title( $type ) ) );
                } else {
                    $action_cat = term_exists( $type, 'properties_types' );
                }
                $property_type_array[] = $action_cat['term_id'];
            }

            $property_name   = $city = $latitude = $longitude = $neighborhood = '';
            $country_term_id = $state_term_id = $city_term_id = '';

            if ( isset( $property['name'] ) && $property['name'] != '' ) {
                $property_name = $property['name'];
            }

            if ( isset( $property['city'] ) && $property['city'] != '' ) {
                $city = $property['city'];
                if ( term_exists( $city, 'location' ) === null ) {
                    $city_args = array(
                            'slug' => sanitize_title( $city ),
                    );
                    if ( $state_term_id != '' ) {
                        $city_args['parent'] = $state_term_id;
                    }
                    $city_term = wp_insert_term( $city, 'location', $city_args );
                } else {
                    $city_term = term_exists( $city, 'location' );
                }

                $city_term_id = $city_term['term_id'];
                array_push( $location_array, $city_term_id );
            }

            if ( isset( $property['location_latitude'] ) && $property['location_latitude'] != "" ) {
                $latitude = $property['location_latitude'];
            }
            if ( isset( $property['location_longitude'] ) && $property['location_longitude'] != "" ) {
                $longitude = $property['location_longitude'];
            }

            $gallery_image = '';
            if ( isset( $property['gallery']['image'] ) && ! empty( $property['gallery']['image'] ) ) {
                $gallery_image = $this->gallery_images( $property['gallery']['image'] );
            }

            $virtual_tour_url = $virtual_tour_image_overlay_url = array();
            if ( $virtual_tour_url ) {
                $virtual_tour_url = $property['virtual_tour_url'];
            }

            if ( $virtual_tour_image_overlay_url ) {
                $virtual_tour_image_overlay_url = $property['virtual_tour_image_overlay_url'];
            }

            $params = array(
                "unit_id" => $property['id'],
            );


            $property_extra_info = $this->callStreamlineMagneticstrategyAPI('get-single-property-info', $params);
            $hide_property_on_website = false;
            if ( $property_extra_info['data']['not_show_on_website'] === 1 ) {
                $hide_property_on_website = true;
            }

            $property_meta = apply_filters( 'vrb_property_data_save', array(
                    'post_data' => array(
                            'post_title'   => $property_name,
                            'post_type'    => 'properties',
                            'post_status'  => 'publish',
                            'post_name'    => sanitize_title( $property_name ),
                            'post_content' => $property['description'],
                            'post_author'  => 4,
                            'meta_input'   => array(
                                    'property_id'                    => $property['id'],
                                    'short_description'              => $property['short_description'],
                                    'property_address'               => $property['address'],
                                    'addressLine2'                   => '',
                                    'property_city'                  => $property['city'],
                                    'property_state'                 => $property['state_name'],
                                    'property_country'               => $property['country_name'],
                                    'property_zipcode'               => $property['zip'],
                                    'property_latitude'              => $property['location_latitude'],
                                    'property_longitude'             => $property['location_longitude'],
                                    'property_image_gallery'         => $gallery_image,
                                    'thumbnail_image'                => $property['default_thumbnail_path'],
                                    'vrb_engine'                     => $this->vrb_engine,
                                    'view_name'                      => $property['view_name'],
                                    'condo_type_name'                => $property['condo_type_name'],
                                    'virtual_tour_url'               => $virtual_tour_url,
                                    '_yoast_wpseo_title'             => $property['seo_title'],
                                    '_yoast_wpseo_metadesc'          => $property['seo_description'],
                                    'seo_keywords'                   => $property['seo_keywords'],
                                    'location_area_name'             => $property['location_area_name'],
                                    'virtual_tour_image_overlay_url' => $virtual_tour_image_overlay_url,
                                    'bedrooms'                       => $property['bedrooms_number'],
                                    'bathrooms'                      => $property['bathrooms_number'],
                                    'half_bathroom_count' => isset($property['half_bathroom_count']) ? $property['half_bathroom_count'] : '',
                                    'guests'                         => $property['max_occupants'],
                                    'property_rating_name'           => $property['property_rating_name'],
                                    'property_rating_points'         => $property['property_rating_points'],
                                    'max_pets'                       => $property['max_pets'],
                                    'location_original_id'           => $property['location_original_id'],
                                    'home_type'                      => $property['home_type'],
                                    'check-in-hour'                  => $property['check_in_time'],
                                    'check-out-hour'                 => $property['check_out_time'],
                            ),
                            'tax_input'    => array(
                                    'properties_amenities' => $amenities_array,
                                    'location'             => $location_array,
                                    'property_area'        => $neighborhood_array,
                            )
                    ),
            ), $post_id );

            $post_id = vrb_check_property_exists( $property['id'] );
            
            $is_active = ( $property['status_name'] === 'Active' );

            if ( $post_id > 0 ) {

                if ( $is_active ) {
                    $property_meta['post_data']['ID'] = $post_id;
                    unset($property_meta['post_data']['post_author']);
                    $this->update_property( $property_meta );
                } else {
                    $this->delete_property( $post_id );
                    return; // nothing else to do
                }

            } elseif ( $is_active ) {
                $post_id = $this->save_property( $property_meta );
            }

            // Stop if post not created/updated
            if ( $post_id <= 0 ) {
                return;
            }

            /**
             * Assign taxonomies
             */
            wp_set_post_terms( $post_id, $amenities_array, 'properties_amenities', false );
            wp_set_post_terms( $post_id, $neighborhood_array, 'property_area', false );
            wp_set_post_terms( $post_id, $location_array, 'location', false );

            /**
             * Last Sync Date
             */
            update_post_meta(
                $post_id,
                'last_sync_date',
                current_datetime()->format( 'Y-m-d h:i:s a' )
            );

            wp_update_post([
                'ID'          => $post_id,
                'post_status' => $hide_property_on_website ? 'draft' : 'publish',
            ]);

            return $post_id;

        }

        public function sync_property_review( $property_id, $post_id ) {

            // Fetch last 2 days (safe buffer)
                $days_to_fetch = 2;

            $enddate   = date('Y-m-d');
            $startdate = date('Y-m-d', strtotime("-{$days_to_fetch} days"));

            // Get existing comments
            $existingComments = get_comments([
                    'post_id' => $post_id,
                    'type'    => 'review',
                    'status'  => 'all'
            ]);

            // Maps
            $existing_review_map = [];
            $existing_comment_signatures = [];

            foreach ($existingComments as $c) {
                $stored_review_id = get_comment_meta($c->comment_ID, 'streamline_review_id', true);

                if ($stored_review_id !== '') {
                    $existing_review_map[(string) $stored_review_id] = [
                            'comment_id' => (int) $c->comment_ID,
                            'content'    => $c->comment_content,
                    ];
                }

                $signature = strtolower(trim($c->comment_author)) . '|' . strtolower(trim($c->comment_content));
                $existing_comment_signatures[$signature] = true;
            }

            // Single API call (no chunking)
            $params = array(
                    'unit_id'            => $property_id,
                    'return_all'         => 2,
                    'startdate'          => $startdate,
                    'enddate'            => $enddate,
                    'show_booking_dates' => 1,
                    'feedback_limit'     => 500
            );

            $response = $this->callStreamlineMagneticstrategyAPI('get-property-review', $params);

            if (
                    isset($response['data']['comments']) &&
                    !empty($response['data']['comments'])
            ) {

                $feedbacks = $response['data']['comments'];

                // Normalize to array
                if (isset($feedbacks['id'])) {
                    $feedbacks = [$feedbacks];
                }

                foreach ($feedbacks as $review) {

                    if (empty(trim($review['comments'] ?? ''))) continue;

                    $review_id = intval($review['id'] ?? 0);
                    if (!$review_id) continue;

                    $author_name  = sanitize_text_field(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''));
                    $comment_text = sanitize_textarea_field($review['comments']);
                    $signature    = strtolower(trim($author_name)) . '|' . strtolower(trim($comment_text));

                    $points = !empty($review['points']) ? $review['points'] : 5;

                    // ✅ Existing review → update if changed
                    if (isset($existing_review_map[(string) $review_id])) {

                        $existing_entry = $existing_review_map[(string) $review_id];
                        $stored_content = strtolower(trim($existing_entry['content']));
                        $new_content    = strtolower(trim($comment_text));

                        if ($stored_content !== $new_content) {

                            wp_update_comment([
                                    'comment_ID'      => $existing_entry['comment_id'],
                                    'comment_content' => $comment_text,
                            ]);

                            update_comment_meta($existing_entry['comment_id'], 'vrb_rating', $points);
                            update_comment_meta($existing_entry['comment_id'], 'review_title', sanitize_text_field($review['title'] ?? ''));

                            // Update signature map
                            unset($existing_comment_signatures[
                                  strtolower(trim($author_name)) . '|' . strtolower(trim($existing_entry['content']))
                                    ]);

                            $existing_comment_signatures[$signature] = true;
                            $existing_review_map[(string) $review_id]['content'] = $comment_text;
                        }

                        continue;
                    }

                    // ✅ Duplicate fallback check (old imports)
                    if (isset($existing_comment_signatures[$signature])) {
                        continue;
                    }

                    // ✅ Insert new review
                    $comment_data = [
                            'comment_post_ID'      => $post_id,
                            'comment_author'       => $author_name,
                            'comment_content'      => $comment_text,
                            'comment_author_email' => '',
                            'comment_author_url'   => '',
                            'comment_type'         => 'review',
                            'comment_date'         => !empty($review['creation_date'])
                                    ? date('Y-m-d H:i:s', strtotime($review['creation_date']))
                                    : current_time('mysql'),
                            'comment_approved'     => 1,
                    ];

                    $comment_id = wp_insert_comment($comment_data);

                    if ($comment_id) {

                        add_comment_meta($comment_id, 'streamline_review_id', $review_id);
                        add_comment_meta($comment_id, 'property_unit_id', $property_id);
                        add_comment_meta($comment_id, 'status_id', $review['status_id'] ?? '');
                        add_comment_meta($comment_id, 'review_title', sanitize_text_field($review['title'] ?? ''));
                        add_comment_meta($comment_id, 'vrb_rating', $points);
                        add_comment_meta($comment_id, 'reservation_id', sanitize_text_field($review['reservation_id'] ?? ''));
                        add_comment_meta($comment_id, 'creation_date', sanitize_text_field($review['creation_date'] ?? ''));

                        if (!empty($review['enddate'])) {
                            add_comment_meta($comment_id, 'vrb_rating_date', date('Y-m-d', strtotime($review['enddate'])));
                        }

                        $existing_review_map[(string) $review_id] = [
                                'comment_id' => $comment_id,
                                'content'    => $comment_text,
                        ];

                        $existing_comment_signatures[$signature] = true;
                    }
                }
            }
        }


        public function save_property( $property_meta ) {
            if ( ! empty( $property_meta ) ) {
                //Create New Post
                $property_post_id = wp_insert_post( $property_meta['post_data'] );

                if ( ! is_wp_error( $property_post_id ) ) {
                    return $property_post_id;
                } else {
                    return false;
                }
            }
        }

        public function update_property( $property_meta ) {
            if ( empty( $property_meta ) ) {
                return;
            }

            wp_update_post( $property_meta['post_data'] );
        }

        public function delete_property( $property_post_id ) {
            wp_delete_post( $property_post_id, true );
        }

        public function write_import_log( $log ) {
            if ( WP_DEBUG === true ) {
                if ( is_array( $log ) || is_object( $log ) ) {
                    error_log( print_r( $log, true ) );
                } else {
                    error_log( $log );
                }
            }

            return;
        }

        public function gallery_images( $api_images ) {
            $images = [];

            foreach ( $api_images as $key => $image ) {
                $images[ $key ] = [
                        'label'        => $image['description'] ?? '',
                        'original_uri' => $image['image_path'] ?? '',
                        'thumb_uri'    => $image['thumbnail_path'] ?? '',
                ];
            }

            return $images;
        }

        public function set_property_availability( $property_id, $post_id ) {
            global $wpdb;

            $start_date = date( 'm/d/Y' );
            $end_date   = date( 'm/d/Y', strtotime( '+1 year' ) );

            $params = array(
                    "startdate" => $start_date,
                    "enddate"   => $end_date,
                    "unit_id"   => $property_id
            );

            $response = $this->callStreamlineMagneticstrategyAPI( 'get-property-rates', $params );

            $propertyAvailabilityTbl = $wpdb->prefix . 'property_availability';

            $wpdb->query( 'START TRANSACTION' );

            try {
                $result = $wpdb->query( $wpdb->prepare( "DELETE FROM $propertyAvailabilityTbl WHERE property_id = %d", $post_id ) );

                $availabilities = '';
                $entries        = [];
                if ( isset( $response['data'] ) && $response['data'] != "" ) {
                    $availabilities = $response['data'];

                    foreach ( $availabilities as $key => $availability ) {
                        $status        = 'Y';
                        $allowCheckIn  = 1;
                        $allowCheckOut = 1;
                        if ( isset( $availability['booked'] ) && $availability['booked'] == 1 ) {
                            $allowCheckIn  = 0;
                            $allowCheckOut = 0;
                            $status        = 'N';
                        }


                        $entries[] = [
                                'property_id'     => $post_id,
                                'date'            => $availability['date'],
                                'price'           => $availability['rate'],
                                'status'          => $status,
                                'allow_check_in'  => $allowCheckIn,
                                'allow_check_out' => $allowCheckOut,
                                'minimum_night'   => $availability['minStay'],
                        ];
                    }

                    // Insert all entries into the table
                    foreach ( $entries as $entry ) {
                        $wpdb->insert( $propertyAvailabilityTbl, $entry, [ '%d', '%s', '%s', '%s', '%d', '%d', '%d' ] );
                    }

                    $wpdb->query( 'COMMIT' );
                }

                $wpdb->query( 'COMMIT' );

            } catch ( \Throwable $th ) {
                $wpdb->query( 'ROLLBACK' );
                error_log( $th->getMessage() );
            }

        }

        public function set_unit( $property, $post_id ) {
            //We directly use property meta because we don't have units
            $property_id = $property['id'];

            $unit_meta = apply_filters( 'vrb_unit_data_save', array(
                    'post_data' => array(
                            'post_title'  => $property['name'],
                            'post_type'   => 'units',
                            'post_status' => 'publish',
                            'meta_input'  => array(
                                    'unit_id'          => $property['id'],
                                    'property_id'      => $property['id'],
                                    'property_post_id' => $post_id,
                                    'description'      => $property['description'],
                                    'propertyType'     => $property['home_type'],
                                    'bathrooms'        => $property['bathrooms_number'],
                                    'bedrooms'         => $property['bedrooms_number'],
                                    'guests'           => $property['max_occupants'],
                            ),
                    ),
            ) );

            $unit_post_id = $this->check_unit_exists( $property_id );

            $this->write_import_log( 'unit_post_id - ' . $unit_post_id );

            if ( $unit_post_id ) {
                $unit_meta['post_data']['ID'] = $unit_post_id;
                $this->update_unit_data( $unit_meta );
            } else {
                $unit_post_id = $this->save_unit( $unit_meta );
            }

            //Update RoomDetails
            $this->set_unit_room_detail( $property_id, $post_id );
            //Update Price
            $this->set_unit_price( $property_id, $post_id );

            //Sync Block date for property
            $this->set_property_availability( $property_id, $post_id );

        }

        public function check_unit_exists( $unit_id ) {

            $unit_post_id = '';
            $args         = array(
                    'post_type'   => 'units',
                    'post_status' => 'publish',
                    'meta_query'  => array(
                            array(
                                    'key'     => 'unit_id',
                                    'value'   => $unit_id,
                                    'compare' => '='
                            )
                    )
            );

            $query = new WP_Query( $args );

            if ( $query->have_posts() ):
                while ( $query->have_posts() ):
                    $query->the_post();
                    $unit_post_id = get_the_ID();
                endwhile;
                wp_reset_postdata();
            else :
                $unit_post_id = false;
            endif;

            return $unit_post_id;
        }

        public function save_unit( $unit_meta ) {
            if ( ! empty( $unit_meta ) ) {
                //Create New Post
                $unit_post_id = wp_insert_post( $unit_meta['post_data'] );

                if ( ! is_wp_error( $unit_post_id ) ) {
                    return $unit_post_id;
                } else {
                    return false;
                }
            }
        }

        public function update_unit_data( $unit_meta ) {
            $this->write_import_log( 'update_unit_data' );
            if ( empty( $unit_meta ) ) {
                return;
            }
            $this->write_import_log( $unit_meta );
            wp_update_post( $unit_meta['post_data'] );
        }

        public function set_unit_room_detail( $property_id, $post_id ) {
            $params   = array( 'unit_id' => $property_id );
            $response = $this->callStreamlineMagneticstrategyAPI( 'property-room-details', $params );

            if ( ! empty( $response['data'] ) ) {
                //Store Room Details in property meta...
                update_post_meta( $post_id, 'room_details', $response['data']['room_details'] );
            } else {
                $this->write_import_log( $response['status']['code'] . ": " . $response['status']['description'] );
            }

            return false;
        }

        public function set_unit_price( $property_id, $post_id ) {
            $params   = array( 'unit_id' => $property_id );
            $response = $this->callStreamlineMagneticstrategyAPI( 'property-unit-price', $params );

            if ( isset( $response['status_type'] ) && $response['status_code'] == 403 ) {
                $this->write_import_log( $response['message'] );

                return false;
            }


            if ( ! empty( $response['data'] ) ) {

                //Store Seasonal Price data
                update_post_meta( $post_id, 'season_price', $response['data']['rates'] );

                //get daily price & currency
                foreach ( $response['data']['rates'] as $rate ) {

                    update_post_meta( $post_id, 'daily_price', (double) str_replace( array(
                            ',',
                            '$'
                    ), '', $rate['daily_first_interval_price'] ) );

                    //Update price
                    update_post_meta( $post_id, 'currency', $rate['currency'] );
                    break;
                }

                return $response['data'];
            } else {
                $this->write_import_log( $response['status']['code'] . ": " . $response['status']['description'] );
            }

            return false;
        }


        public function get_discount_pricing( $property_post_id, $unit_post_id, $output_html = true ) {
            $check_in  = $_REQUEST['check_in'];
            $check_out = $_REQUEST['check_out'];
            if ( $check_in != '' && $check_out != '' ) {
                $check_in  = date( "Y-m-d", strtotime( $check_in ) );
                $check_out = date( "Y-m-d", strtotime( $check_out ) );
            } else {
                $check_in  = date( "Y-m-d" );
                $check_out = date( "Y-m-d", strtotime( '+7 days' ) );
            }

            $check_in_date  = new DateTime( $check_in );
            $check_out_date = new DateTime( $check_out );
            $total_nights   = $check_out_date->diff( $check_in_date )->format( '%a' );

            $vrb_settings = vrb_get_settings();
            $api_key      = $this->api_key;
            $end_point    = $vrb_settings['booking_engines']['streamline']['end_point'];
            $discount_url = $end_point . 'properties/' . $property_post_id . '/units/' . $unit_post_id . '/rates';

            $rates_response = wp_remote_get(
                    $discount_url,
                    array(
                            'method'  => 'GET',
                            'headers' => array(
                                    'Authorization' => 'Token ' . $api_key,
                                    'Accept'        => 'application/vnd.direct.v1',
                                    'Content-Type'  => 'application/json',
                            )
                    )
            );
            $rate_response  = json_decode( $rates_response['body'], true );

            if ( ! empty( $rate_response ) && isset( $rate_response['discounts'] ) ) {
                if ( ! empty( $rate_response['discounts'] ) ) {
                    $dynamic_price_data = $rate_response['discounts'];
                    foreach ( $rate_response['discounts'] as $key => $dynamic_price ) {
                        $discount_name = $dynamic_price['name'];
                        if ( $discount_name == 'Week discount' ) {
                            $stay_period_str = $dynamic_price['range'];
                            $stay_start      = $stay_period_str[0];
                            $stay_end        = $stay_period_str[1];

                            if ( $total_nights >= $stay_start && $total_nights <= $stay_end ) {
                                $discounted_price = $dynamic_price['percent'];
                                if ( $output_html ) {
                                    $discount_price = number_format( $discounted_price, 0 ) . '%';
                                    echo '<div class="property-offer-wrap"><div class="property-offer">' . $discount_price . ' Off</div></div>';
                                } else {
                                    return number_format( $discounted_price, 0 );
                                }
                            }
                        }
                        if ( $discount_name == 'Month discount' ) {
                            $stay_period_str = $dynamic_price['range'];
                            $stay_start      = $stay_period_str[0];

                            if ( $total_nights >= $stay_start ) {
                                $discounted_price = $dynamic_price['percent'];
                                if ( $output_html ) {
                                    $discount_price = number_format( $discounted_price, 0 ) . '%';
                                    echo '<div class="property-offer-wrap"><div class="property-offer">' . $discount_price . ' Off</div></div>';
                                } else {
                                    return number_format( $discounted_price, 0 );
                                }
                            }
                        }

                    }
                }
            }
        }

        public function show_additional_fields_in_admin( $fields ) {
            $fields['view_name']          = 'View Name';
            $fields['condo_type_name']    = 'Condo Type';
            $fields['location_area_name'] = 'Loation Area';

            return $fields;
        }

        public function sync_streamline_featured_properties_function() {

            $this->write_import_log( 'Featured cron called' );
            $params      = array(
                    "location_area_name" => "Park City"
            );
            $block_dates = array();

            $response = $this->callStreamlineMagneticstrategyAPI( 'get-property-list-wordpress', $params );

            if ( isset( $response['status_type'] ) && $response['status_code'] == 403 ) {
                $this->write_import_log( $response['message'] );
                exit;
            }

            if ( isset( $response['data'] ) && ! empty( $response['data'] ) ) {
                foreach ( $response['data']['property'] as $propertykey => $property ) {
                    $post_id         = get_property_post_id( $property['id'] );
                    $properties_id[] = $property['id'];
                    $start_date      = date( 'm/d/Y' );
                    $end_date        = date( 'm/d/Y', strtotime( '60 days' ) );
                    $params          = array(
                            "startdate" => $start_date,
                            "enddate"   => $end_date,
                            "unit_id"   => $property['id']
                    );
                    $datesresponse   = $this->callStreamlineMagneticstrategyAPI( 'blocked-days-for-unit', $params );
                    $seletecdates    = array();
                    if ( ! empty( $datesresponse['data'] ) ) {
                        foreach ( $datesresponse['data']['blocked_days']['blocked'] as $dateskey => $dates ) {
                            $end_date = new DateTime( $dates['enddate'] );
                            $period   = new DatePeriod(
                                    new DateTime( $dates['startdate'] ),
                                    new DateInterval( 'P1D' ),
                                    $end_date->modify( '+1 day' )
                            );
                            foreach ( $period as $key => $date ) {
                                $seletecdates[] = $date->format( 'Y-m-d' );
                            }
                        }
                    }
                    $countDates = array_values( array_unique( $seletecdates ) );
                    update_post_meta( $post_id, 'availability_count', count( $countDates ) );
                }
            }
        }

        public function sync_streamline_inactive_properties_function() {
            $this->write_import_log( 'Inactive properties removed from site cron called' );
            $IAparams = array(
                    "status_id" => 2
            );

            $IAresponse = $this->callStreamlineMagneticstrategyAPI( 'get-property-list', $IAparams );

            if ( isset( $IAresponse['status_type'] ) && $IAresponse['status_code'] == 403 ) {
                $this->write_import_log( $IAresponse['message'] );
                exit;
            }

            $IAproperty_data = $IAresponse['data']['property'];
            if ( isset( $IAproperty_data ) && ! empty( $IAproperty_data ) ) {
                foreach ( $IAproperty_data as $key => $property ) {
                    $post_id = $this->check_property_exists( $property['id'] );
                    if ( $post_id ) {
                        $this->write_import_log( " Remove Inactive properties ID " . $property['id'] );
                        $this->delete_property_unit( $post_id );
                    } else {
                        $this->write_import_log( " Property not exists.. " . $property['id'] );
                    }
                }
            }
        }

        public function sync_streamline_delete_properties_function() {
            $this->write_import_log( 'deleted properties removed from site cron called' );
            $IAparams   = array(
                    "status_id" => 3
            );
            $IAresponse = $this->callStreamlineMagneticstrategyAPI( 'get-property-list', $IAparams );

            if ( isset( $IAresponse['status_type'] ) && $IAresponse['status_code'] == 403 ) {
                $this->write_import_log( $IAresponse['message'] );
                exit;
            }

            $IAproperty_data = $IAresponse['data']['property'];

            if ( isset( $IAproperty_data ) && ! empty( $IAproperty_data ) ) {
                foreach ( $IAproperty_data as $key => $property ) {
                    $post_id = $this->check_property_exists( $property['id'] );
                    if ( $post_id ) {
                        $this->write_import_log( " Remove Deleted property ID " . $property['id'] );
                        $this->delete_property_unit( $post_id );
                    } else {
                        $this->write_import_log( " Property not exists.. " . $property['id'] );
                    }
                }
            }
        }

        public function sync_streamline_properties_to_site_function() {
            // Collect all API property IDs
            $authentication = $this->check_authentication();

            if ( ! $authentication ) {
                $this->write_import_log( 'auth failed' );
                wp_send_json( array(
                        'success' => false,
                        'msg'     => __( 'Authentication is not valid. Please check Authentication API Key and End point.' )
                ) );
            }

            $syncProperty_params = array(
                    "page_results_number" => '',
                    "page_number"         => ''
            );

            $response = $this->callStreamlineMagneticstrategyAPI( 'import-properties', $syncProperty_params );

            if ( ! empty( $response['data']['property'] ) && ! empty( $response['data']['property'] ) ) {
                $api_property_ids = wp_list_pluck( $response['data']['property'], 'id' );

                // Get all existing property posts
                $existing_posts = get_posts( array(
                        'post_type'   => 'properties',
                        'post_status' => 'any',
                        'numberposts' => - 1,
                        'fields'      => 'ids',
                        'meta_query'  => array(
                                array(
                                        'key'     => 'property_id',
                                        'compare' => 'EXISTS',
                                )
                        )
                ) );

                // Build map property_id → post_id
                $property_map = array();
                foreach ( $existing_posts as $post_id ) {
                    $prop_id = get_post_meta( $post_id, 'property_id', true );
                    if ( ! empty( $prop_id ) ) {
                        $property_map[ $prop_id ] = $post_id;
                    }
                }

                // Find property_ids that exist in DB but not in API
                foreach ( $property_map as $prop_id => $post_id ) {
                    if ( ! in_array( $prop_id, $api_property_ids ) ) {
                        // Delete post permanently
                        wp_delete_post( $post_id, true );
                        $this->write_import_log( "Deleted property ID {$prop_id} (post ID {$post_id}) because it's missing in API." );
                    }
                }
            }
        }

        /**
         * Check if property exists in local DB
         */
        public function check_property_exists( $property_id ) {
            $args  = array(
                    'post_type'      => 'properties',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                            array(
                                    'key'   => 'property_id',
                                    'value' => $property_id,
                            ),
                    ),
                    'fields'         => 'ids'
            );
            $query = new WP_Query( $args );

            return ! empty( $query->posts ) ? $query->posts[0] : false;
        }

        /**
         * Delete property post safely
         */
        public function delete_property_unit( $post_id ) {
            if ( get_post_type( $post_id ) === 'properties' ) {
                wp_delete_post( $post_id, true ); // true = force delete
                $this->write_import_log( "Post ID $post_id deleted." );
            }
        }

        public function streamline_enqueue_scripts() {
            global $vrb_options;
            wp_register_script( 'streamline-js', GDVRB_STREAMLINE_URL . 'assets/js/streamline.js', [ 'jquery' ], time() );

            wp_localize_script( 'streamline-js', 'ajax_params', array(
                    'home_url'   => home_url(),
                    'ajax_url'   => admin_url( 'admin-ajax.php' ),
                    'ajax_nonce' => wp_create_nonce( 'script_js_nonce' )
            ) );

            wp_enqueue_script( 'streamline-js' );

            // if( is_page( $vrb_options['general']['pages']['checkout_page'] ) ){
            // 	wp_enqueue_script( 'stripe__js', 'https://js.stripe.com/v3/', array(), VRB_VERSION, true );
            // 	wp_enqueue_script( 'streamline-stripe-js', GDVRB_STREAMLINE_URL.'assets/js/stripe-checkout.js' , ['jquery'], time() );
            // }

        }

        public function add_coupon_box_checkout_properties_price_before( $post_id ) {
            //include GDVRB_STREAMLINE_DIR.'/templates/single-property/single-property-coupon-box.php';
        }

        public function applying_coupon() {
            $coupon      = $_POST['coupon'];
            $check_in    = $_POST['check_in'];
            $check_out   = $_POST['check_out'];
            $num_adult   = $_POST['num_adult'];
            $num_child   = $_POST['num_child'];
            $property_id = $_POST['property_id'];
            $unit_id     = $_POST['unit_id'];

            $params = array(
                    "startdate"       => $check_in,
                    "enddate"         => $check_out,
                    "occupants"       => $num_adult,
                    "occupants_small" => $num_child,
                    "unit_id"         => $unit_id,
                    "coupon_code"     => $coupon,
            );

            $verify_coupon_response = $this->callStreamlineMagneticstrategyAPI( 'get-reservation-price', $params );


            if ( ! empty( $verify_coupon_response['data']['coupon_id'] ) ) {
                wp_send_json( array(
                        'success'  => true,
                        'msg'      => __( 'Valid Coupon Code.' ),
                        'response' => $coupon
                ) );
            } else {
                wp_send_json( array(
                        'success' => false,
                        'msg'     => __( 'Invalid Coupon Code.' ),
                ) );
            }
        }

        public function streamline_meta_boxes() {
            add_meta_box( 'ribbon-meta-box', __( 'Ribbon Field', 'cwr' ), array(
                    $this,
                    'rbn_display_callback'
            ), 'properties', 'side', 'high' );
            add_meta_box( 'sync-meta-box', __( 'Sync Property', 'cwr' ), array(
                    $this,
                    'sync_single_box'
            ), 'properties', 'side', 'high' );
        }

        public function rbn_save_meta_box( $post_id ) {
            if ( array_key_exists( 'rbn_value', $_POST ) ) {
                update_post_meta( $post_id, 'rbn_display_callback', $_POST['rbn_value'] );
            }
        }

        public function rbn_display_callback( $post ) {
            ?>
            <div class="rbn_box">
                <style scoped>
                    .rbn_box {
                        display: grid;
                        grid-template-columns: max-content 1fr;
                        grid-row-gap: 10px;
                        grid-column-gap: 20px;
                    }

                    .rbn_field {
                        display: contents;
                    }
                </style>
                <?php $meta_element_class = get_post_meta( $post->ID, 'rbn_display_callback', true ); ?>
                <p class="meta-options rbn_field">
                    <label for="rbn_value">Select Ribbon Type</label>
                    <select name='rbn_value' id='rbn_value'>
                        <option value="None" <?php selected( $meta_element_class, 'None' ); ?>>None</option>
                        <option value="Coming Soon" <?php selected( $meta_element_class, 'Coming Soon' ); ?>>Coming
                            Soon
                        </option>
                        <option value="New" <?php selected( $meta_element_class, 'New' ); ?>>New</option>
                    </select>
                </p>
            </div>
            <?php
        }

        public function sync_single_box( $post ) {
            global $vrb_options;
            wp_nonce_field( 'sync_property_box', 'sync_property_box_nonce' );

            $import_single_properties = get_option_value( 'import_single_properties', $vrb_options );
            if ( $import_single_properties ) {
                $last_sync_date = get_post_meta( get_the_ID( $post ), 'last_sync_date', true ); ?>
                <style>
                    #property_sync_container .spinner.active {
                        display: block;
                        visibility: visible;
                    }

                    .last-update-text {
                        padding: 10px 0 0;
                        display: flex;
                    }
                </style>
                <div id="property_sync_container">
                    <a href="javascript:void(0);" class="button-primary sync_property_btn"
                       data-post_id="<?php echo get_the_ID( $post ); ?>">SYNC</a>
                    <span class="spinner"></span>
                </div>
                <?php if ( ! empty( $last_sync_date ) ) { ?>
                    <div class="last-update-text">
                        <div>Last Sync : <?php echo date( "m/d/Y h:i:s a", strtotime( $last_sync_date ) ); ?></div>
                    </div>
                <?php }
            } else {
                echo "If you want to sync single property manually then enable option from vacation rental settings.";
            }
        }

        public function add_streamline_booking( $success, $posted_data, $payment_response ) {

            $response = array();
            global $vrb_options;
            $authentication = $this->check_authentication();
            if ( ! $authentication ) {

                $this->write_import_log( 'auth failed' );

                $response['success'] = false;
                $response['message'] = __( 'Authentication is not valid. Please check Authentication API Key and End point.', 'vacation-rental-booking' );

                return $response;
            }

            write_log( '---------------------------------------------------------------------------- Booking Process Start ----------------------------------------------------------------------------' );

            if ( ! wp_verify_nonce( $posted_data['checkout_nonce_field'], 'checkout_nonce_action' ) ) {

                write_log( 'Nonce invalid', 'nonce is not valid' );

                $response['success'] = false;
                $response['message'] = __( 'Sorry, Checkout nonce wrong. Please reload the page.', 'vacation-rental-booking' );

                return $response;
            }

            if ( $posted_data['check_in'] == '' || $posted_data['check_out'] == '' ) {
                write_log( 'Dates is not selected', 'date not set' );
                $response['success'] = false;
                $response['message'] = __( 'Sorry, Please select dates.', 'vacation-rental-booking' );

                return $response;
            }

            $check_in  = $posted_data['check_in']; // convert into m/d/Y format
            $check_out = $posted_data['check_out']; // convert into m/d/Y format


            // create json to send in streamline api.
            $params = array(
                    'unit_id'                      => $posted_data['unit_id'],
                    'first_name'                   => $posted_data['first_name'],
                    'last_name'                    => $posted_data['last_name'],
                    'email'                        => $posted_data['email'],
                    'phone'                        => $posted_data['phone'],
                    'mobile_phone'                 => $posted_data['phone'],
                    'address'                      => $posted_data['address1'],
                    'city'                         => ( isset( $posted_data['city'] ) && $posted_data['city'] != '' ) ? $posted_data['city'] : '',
                    "country_name"                 => "US",
                    'zip'                          => $posted_data['postal_code'],
                    'startdate'                    => $check_in,
                    'enddate'                      => $check_out,
                    'occupants'                    => $posted_data['adults'],
                    'occupants_small'              => empty( $posted_data['children'] ) ? 0 : $posted_data['children'],
                    'pets'                         => 0,
                    'credit_card_number'           => str_replace( ' ', '', $posted_data['card_number'] ),
                    'credit_card_cid'              => $posted_data['cvv'],
                    'credit_card_expiration_month' => $posted_data['expiration_month'],
                    'credit_card_expiration_year'  => $posted_data['expiration_year'],
                    'referrer_url'                 => home_url( '/checkout/' ),
                    'coupon_code'                  => $posted_data['coupon_box'],
            );

            $fees_ids = explode( '|', $posted_data['fees_ids'] );

            if ( ! empty( $fees_ids ) ) {
                foreach ( $fees_ids as $fee ) {
                    if ( ! empty( $fee ) ) {
                        $params[ $fee ] = 1;
                    }
                }
            }

            $writeLog = array(
                    'unit_id'         => $posted_data['unit_id'],
                    'first_name'      => $posted_data['first_name'],
                    'last_name'       => $posted_data['last_name'],
                    'email'           => $posted_data['email'],
                    'phone'           => $posted_data['phone'],
                    'mobile_phone'    => $posted_data['phone'],
                    'address'         => $posted_data['address1'],
                    'city'            => ( isset( $posted_data['city'] ) && $posted_data['city'] != '' ) ? $posted_data['city'] : '',
                    "country_name"    => "US",
                    'zip'             => $posted_data['postal_code'],
                    'startdate'       => $check_in,
                    'enddate'         => $check_out,
                    'occupants'       => $posted_data['adults'],
                    'occupants_small' => empty( $posted_data['children'] ) ? 0 : $posted_data['children'],
                    'pets'            => 0,
                    'referrer_url'    => home_url( '/checkout/' ),
                    'coupon_code'     => $posted_data['coupon_box'],
            );

            write_log( '---------------------------------Reservations args Start---------------------------------' );
            write_log( $writeLog, 'Step - 4 Send this Reservations args to streamline' );
            write_log( '---------------------------------Reservations args End---------------------------------' );

            $reservations_response = $this->callStreamlineMagneticstrategyAPI( 'make-reservation', $params );

            if ( isset( $reservations_response['status_type'] ) && $reservations_response['status_code'] == 403 ) {
                $response['success'] = false;
                $response['message'] = __( $reservations_response['message'], 'vacation-rental-booking' );

                return $response;
            }


            write_log( '---------------------------------Reservations Response Start---------------------------------' );
            write_log( $reservations_response, 'Step - 5 Reservations Response from streamline' );
            write_log( '---------------------------------Reservations Response End---------------------------------' );

            if ( isset( $reservations_response['status']['code'] ) && $reservations_response['status']['code'] === 'E0101' ) {
                $response['success'] = false;
                $response['message'] = __( $reservations_response['status']['description'], 'vacation-rental-booking' );

                return $response;
            }

            if ( ! empty( $reservations_response['data'] ) ) {
                $booking                      = $reservations_response['data']['reservation'];
                $response['_id']              = $booking['confirmation_id'];
                $response['confirmationCode'] = $booking['confirmation_id'];
                $response['success']          = true;

                $post_id = get_property_post_id( $posted_data['property_id'] );
                $this->sync_single_property( $post_id );

                return $response;
            } else {
                $response['success'] = false;
                $response['message'] = __( $reservations_response['status']['description'], 'vacation-rental-booking' );

                return $response;
            }

        }

        // public function insert_review( $review, $property_post_id ) {
        //     global $wpdb;
        //     if ( isset( $review['guest_name'] ) ) {
        //         $this->write_import_log( 'single review from API' . json_encode( $review ) );
        //         $sql = 'SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = "' . $property_post_id . '" AND comment_author = "' . $review['guest_name'] . '"';
        //         $this->write_import_log( 'SQL' . $sql );
        //         $wpdb->get_row( $sql, ARRAY_A );
        //         if ( $wpdb->num_rows ) {
        //             //$comment_id = get_comment( $query['comment_ID'] );
        //             $this->write_import_log( 'Already exists' );

        //         } else {

        //             $review_date = date( "Y-m-d", strtotime( $review['creation_date'] ) );

        //             $comment = wp_insert_comment( array(
        //                     'comment_post_ID'      => $property_post_id,
        //                     'comment_author'       => $review['guest_name'],
        //                     'comment_author_email' => '',
        //                     'comment_author_url'   => '',
        //                     'comment_content'      => $review['comments'],
        //                     'comment_type'         => 'review',
        //                     'comment_author_IP'    => '',
        //                     'comment_agent'        => '',
        //                     'comment_date'         => $review_date,
        //                     'comment_approved'     => 1,
        //                     'comment_meta'         => array(
        //                             'title'           => $review['title'],
        //                             'review_id'       => $review['id'],
        //                             'vrb_rating'      => $review['points'],
        //                             'vrb_rating_date' => $review_date
        //                     )
        //             ) );

        //             if ( is_wp_error( $comment ) ) {
        //                 $this->write_import_log( $comment->get_error_message() );
        //             }
        //         }
        //     }

        // }

        public function get_property_unit_ID( $post_id ) {
            global $wpdb;

            $unit_id      = $wpdb->get_row( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'unit_id' AND meta_value = '" . $post_id . "' ", ARRAY_A );
            $unit_post_id = '';
            if ( isset( $unit_id ) && $unit_id != "" ) {
                $unit_post_id = $unit_id['post_id'];
            }

            return $unit_post_id;
        }

        public function streamline_property_check_availability( $engine_response, $args ) {
            global $wpdb;
            $authentication = $this->check_authentication();
            if ( ! $authentication ) {
                $this->write_import_log( 'auth failed' );
                wp_send_json( array(
                        'success' => false,
                        'msg'     => __( 'Authentication is not valid. Please check Authentication API Key and End point.' )
                ) );
            }

            if ( empty( $args['children'] ) ) {
                $args['children'] = 0;
            }

            $unit_id = $this->get_property_unit_ID( $args['post_id'] );

            $startDate     = new DateTime( $args['check_in'] );
            $endDate       = new DateTime( $args['check_out'] );
            $date_diff     = $startDate->diff( $endDate );
            $total_nights  = $date_diff->days;
            $new_check_in  = $startDate->format( 'Y-m-d' );
            $new_check_out = $endDate->format( 'Y-m-d' );

            /* Check minimum night base on Check-In */
            $property_availability_tbl = $wpdb->prefix . 'property_availability';
            $query                     = $wpdb->prepare(
                    "SELECT minimum_night FROM $property_availability_tbl 
				WHERE property_id = %d 
				AND date = %s ",
                    $args['post_id'], $new_check_in
            );

            $min_days_booking = $wpdb->get_var( $query );

            if ( empty( $min_days_booking ) ) {
                $min_days_booking = 0;
            }

            if ( $total_nights < $min_days_booking ) {
                $engine_response['available'] = false;
                $engine_response['message']   = __( 'Sorry, Please select min. ' . $min_days_booking . ' nights for your stay', 'vacation-rental-booking' );

                return $engine_response;
            }

            if(!empty($args['coupon_box'])){
                $args['coupon_code'] = $args['coupon_box'];
            }
            $params = array(
                    "startdate"                  => $args['check_in'],
                    "enddate"                    => $args['check_out'],
                    "occupants"                  => $args['adults'],
                    "occupants_small"            => $args['children'],
                    "unit_id"                    => $args['property_id'],
                    "coupon_code"                => $args['coupon_code'],
                    "include_coupon_information" => 1
            );

            $fees_ids = explode( '|', $args['fees_ids'] );

            if ( ! empty( $fees_ids ) ) {
                foreach ( $fees_ids as $fee ) {
                    if ( ! empty( $fee ) ) {
                        $params[ $fee ] = 1;
                    }
                }
            }
            $return = array();

            $verify_property_response = $this->callStreamlineMagneticstrategyAPI( 'verify-property-availability', $params );

            if ( empty( $verify_property_response ) ) {
                wp_send_json( array(
                        'available' => false,
                        'success'   => false,
                        'message'   => 'Something want to wrong. Please check again.'
                ) );
            }

            if ( isset( $verify_property_response['status_type'] ) && $verify_property_response['status_code'] == 403 ) {
                wp_send_json( array(
                        'available' => false,
                        'success'   => false,
                        'message'   => $verify_property_response['message']
                ) );
            }


            if ( $verify_property_response['status']['code'] == 'E0031' ) {
                wp_send_json( array(
                        'available' => false,
                        'message'   => $verify_property_response['status']['description']
                ) );
            }

            $response = $this->callStreamlineMagneticstrategyAPI( 'get-reservation-price', $params ); 

            $cleaning_fee  = 0;
            $total_tax     = 0;
            $booking_fee   = 0;
            $total_tax_fee = $booking_fee_array = $cleaning_fee_array = array();

            if ( isset( $response['data'] ) && ! empty( $response['data'] ) ) {
                $return['available']        = true;
                $data                       = $response['data'];
                $length                     = count( $data['reservation_days'] );
                $sub_total                  = $data['price'];

                $total_discount = 0;

                if (!empty($data['reservation_days'])) {
                    foreach ($data['reservation_days'] as $day) {
                        if (!empty($day['discount'])) {
                            $total_discount += (float) $day['discount'];
                        }
                    }
                }
                $price_after_discount = $data['price'] + $total_discount;
                $avg_price                  = $price_after_discount / $length;
                $return['per_night_amount'] = $avg_price;

                //Create Fee Object
                $return['rental_fee'][] = array(
                        'label' => vrb_currency_symbol() . number_format( $avg_price, 2 ) . ' avg. × ' . $length . ' nights',
                        'value' => '<b>' . vrb_currency_symbol() . number_format( $price_after_discount, 2 ) . '</b>'
                );

                if ($total_discount > 0 && ! empty( $data['coupon_information']['coupon_code'] )) {
                    $return['rental_fee'][] = array(
                            'label' => '<b style="color:#008000;">Discount</b>',
                            'value' => '<span style="color:#008000;">-' . vrb_currency_symbol() . number_format($total_discount, 2) . '</span>'
                    );

                    $return['discount_info'] = [
                            'coupon_code' => $data['coupon_information']['coupon_code'],
                            'coupon_amount' => $data['coupon_information']['coupon_amount'],
                    ];
                }

                if ( ! empty( $data['taxes_details'] ) ) {
                    if ( empty( $data['taxes_details']['id'] ) ) {
                        foreach ( $data['taxes_details'] as $tax ) {
                            if ( ! empty( $tax['value'] ) ) {
                                $total_tax_fee[] = array(                          'label' => $tax['name'],
                                        'value' => vrb_currency_symbol() . number_format( $tax['value'], 2 )
                                );
                                $sub_total       = $sub_total + $tax['value'];
                                $total_tax       += $tax['value'];
                            }
                        }
                    } else {
                        if ( ! empty( $data['taxes_details']['value'] ) ) {
                            $tax             = $data['taxes_details'];
                            $total_tax_fee[] = array(
                                    'label' => $tax['name'],
                                    'value' => vrb_currency_symbol() . number_format( $tax['value'], 2 )
                            );
                            $sub_total       = $sub_total + $tax['value'];
                            $total_tax       += $tax['value'];
                        }
                    }

                    $return['total_tax']     = vrb_currency_symbol() . number_format( $total_tax, 2 );
                    $return['total_tax_fee'] = $total_tax_fee;
                }

                if ( ! empty( $data['required_fees'] ) ) {
                    if ( empty( $data['required_fees']['id'] ) ) {
                        foreach ( $data['required_fees'] as $fee ) {
                            if ( $fee['name'] != "Cleaning Fee" ) {
                                if ( ! empty( $fee['value'] ) ) {
                                    $booking_fee_array[] = array(
                                            'label' => $fee['name'],
                                            'value' => vrb_currency_symbol() . number_format( $fee['value'], 2 )
                                    );
                                    $sub_total           = $sub_total + $fee['value'];
                                    $booking_fee         += $fee['value'];
                                }
                            } else {
                                if ( $fee['name'] == "Cleaning Fee" ) {
                                    $cleaning_fee_array[] = array(
                                            'label' => $fee['name'],
                                            'value' => vrb_currency_symbol() . number_format( $fee['value'], 2 )
                                    );
                                    $sub_total            = $sub_total + $fee['value'];
                                    $cleaning_fee         += $fee['value'];
                                }
                            }
                        }
                    } else {
                        if ( ! empty( $data['required_fees']['value'] ) ) {
                            $fee                 = $data['required_fees'];
                            $booking_fee_array[] = array(
                                    'label' => $fee['name'],
                                    'value' => vrb_currency_symbol() . number_format( $fee['value'], 2 )
                            );
                            $sub_total           = $sub_total + $fee['value'];
                            $booking_fee         += $fee['value'];
                        }
                    }

                    $return['total_booking_fee']          = vrb_currency_symbol() . number_format( $booking_fee, 2 );
                    $return['total_cleaning_fee']         = vrb_currency_symbol() . number_format( $cleaning_fee, 2 );
                    $return['total_cleaning_fee_details'] = $cleaning_fee_array;
                    $return['total_booking_fee_details']  = $booking_fee_array;
                }

                // $return['rental_fee'][] = array('label' => '<b>Sub Total</b>','value'=> '<b>'.vrb_currency_symbol().number_format($sub_total,2).'</b>');

                if ( ! empty( $data['optional_fees'] ) ) {

                    $return['additional_options'][] = array( 'label' => '<b>Extra Services</b>', 'value' => '' );

                    foreach ( $data['optional_fees'] as $opt ) {
                        if ( ! empty( $opt['value'] ) ) {
                            if ( $opt['description'] == $opt['name'] ) {
                                $opt['description'] = '';
                            }
                            $return['additional_options'][] = array(
                                    'field_name'  => 'optional_fee_' . $opt['id'],
                                    'label'       => $opt['name'],
                                    'value'       => vrb_currency_symbol() . number_format( $opt['value'], 2 ),
                                    "description" => $opt['description'],
                                    'id'          => $opt['id'],
                                    'active'      => $opt['active']
                            );

                            if ( $opt['active'] == 1 ) {
                                $sub_total = $sub_total + $opt['value'];
                            }
                        }
                    }
                }

                $return['sub_total']   = vrb_currency_symbol() . number_format( $sub_total, 2 );
                $return['final_price'] = vrb_currency_symbol() . number_format( $sub_total, 2 );
                $return['deposit_due'] = vrb_currency_symbol() . number_format( $data['due_today'], 2 );

                if ( ! empty( $args['coupon_code'] ) && isset( $data['coupon_discount'] ) && (float) $data['coupon_discount'] == 0 ) {
                    $return['coupon_error']  = true;
                    $return['coupon_message'] = __( 'Sorry, this coupon code is not valid or does not apply to this property.', 'vacation-rental-booking' );
                }
            } else {
                $return = array(
                        'available' => false,
                        'message'   => $response['status']['code'] . ": " . $response['status']['description']
                );
            }

            
            return $return;

        }

        public function process_checkout_stripe_key() {
            // Verify nonce for security
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'vrb_checkout_nonce' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
            }

            // Retrieve the Stripe publishable key and decode it
            $stripe_key = base64_decode( get_option( 'stripe_publishable_key' ) );

            // Check if the Stripe key is valid and return appropriate response
            if ( $stripe_key ) {
                wp_send_json_success( [
                        'message' => 'Stripe publishable key found.',
                        'key'     => $stripe_key
                ] );
            } else {
                wp_send_json_error( [ 'message' => 'Stripe publishable key not found.' ], 404 );
            }

            // Terminate script execution
            wp_die();
        }

        public function save_streamline_api_key() {
            $type = $_POST['type'];

            if ( isset( $type ) && $type == 'stripe-key' ) {
                $stripe_publishable_key = isset( $_POST['publishable_key'] ) ? sanitize_text_field( $_POST['publishable_key'] ) : '';
                update_option( 'stripe_publishable_key', base64_encode( $stripe_publishable_key ) );
            }

            $response = array(
                    'success' => true,
                    'message' => 'Key saved successfully!',
            );

            wp_send_json_success( $response );
        }

        public function disconnect_streamline_api_key() {

            $type = $_POST['type'];

            if ( isset( $type ) && $type == 'stripe-key' ) {
                update_option( 'stripe_publishable_key', '' );
            }

            $response = array(
                    'success' => true,
                    'message' => 'Keys disconnected successfully!',
            );

            wp_send_json_success( $response );

        }


    }
}

add_action( 'init', function () {
    if ( class_exists( 'Vacation_Rental_Booking' ) ) {
        $MSBE_Streamline = new MSBE_Streamline;
    }
} );