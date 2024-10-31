<?php

defined('ABSPATH') or die('This page may not be accessed directly.');

function pushdy_change_footer_admin()
{
    return '';
}
/*
 * Loads js script that includes ajax call with post id
 */

add_action('admin_enqueue_scripts', 'load_javascript');
function load_javascript()
{
    global $post;
    if ($post) {
        wp_register_script('notice_script', plugins_url('notice.js', __FILE__), array('jquery'), '1.1', true);
        wp_enqueue_script('notice_script');
        wp_localize_script('notice_script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'post_id' => $post->ID));
    }
}

add_action('wp_ajax_has_metadata', 'has_metadata');
function has_metadata()
{
    $post_id = $_GET['post_id'];

    if (is_null($post_id)) {
        error_log('Pushdy: could not get post_id');
        $data = array('error' => 'could not get post id');
    } else {

        $status = get_post_meta($post_id, 'status');
        if ($status && is_array($status)) {
            $status = $status[0];
        }

        $response_body = get_post_meta($post_id, 'response_body');
        if ($response_body && is_array($response_body)) {
            $response_body = $response_body[0];
        }

        // reset meta
        delete_post_meta($post_id, 'status');
        delete_post_meta($post_id, 'response_body');

        $data = array('status_code' => $status, 'response_body' => $response_body);
    }

    echo json_encode($data);

    exit;
}

if (isPAWooCommerceEnable()) {

  $pushdy_wp_settings = get_option("PushdyWPSetting");
  if ($pushdy_wp_settings['woocommerce_abandoned_cart']){
    add_action('woocommerce_add_to_cart', 'pushdy_custom_updated_cart', 1);
    add_action('woocommerce_cart_item_removed', 'pushdy_custom_updated_cart', 1);
    add_action('woocommerce_after_cart_item_quantity_update', 'pushdy_custom_updated_cart', 1);
    add_action('wp_footer', 'pushdy_check_product_page');
  }
}

function isPAWooCommerceEnable(){
    return in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins')));
}

function pushdy_check_product_page(){
    if(is_product()){
        global $product;
        if(pushdy_woocommerce_version_check()){
            $product_id = $product->get_id();
        }
        else{
            $product_id = $product->id;
        }
        ?>
        <script type="text/javascript">
            var pa_woo_product_info = <?php echo json_encode(
                    array(
                    'id'=>$product_id,
                    'variant_id'=> 0,
                    'title'=>$product->get_title(),
                    'price'=>$product->get_price(),
                    'price_formatted'=>strip_tags(wc_price($product->get_price())),
                    'type' =>$product->get_type(),
                    'image' => wp_get_attachment_url($product->get_image_id()),
                    'outofstock' => ($product->is_in_stock())?false:true
                    )); ?>
        </script>
        <?php
    }
}

function pushdy_custom_updated_cart(){
  if(!pushdy_subscription_check()){return;}
  global $woocommerce;
  $curr_user = wp_get_current_user();
  $user_info = array();
  if(pushdy_woocommerce_version_check('2.5')){
    $user_info['token'] = $_COOKIE['pushdy_token'];
    $user_info['cart_url'] = wc_get_cart_url();
    $user_info['checkout_url'] = wc_get_checkout_url();
  }
  else{
    $user_info['token'] = $_COOKIE['pushdy_token'];
    $user_info['cart_url'] = $woocommerce->cart->get_cart_url();  
    $user_info['checkout_url'] = $woocommerce->cart->get_checkout_url();
  }
  $user_info['total_items'] = sizeof( WC()->cart->get_cart() );
  $user_info['customer_id'] = $curr_user->ID;
  $user_info['customer_name'] = $curr_user->display_name;
  pushdy_add_abandoned_cart($user_info);
}

function pushdy_woocommerce_version_check( $version = '2.6' ) {
    global $woocommerce;
    return version_compare( $woocommerce->version, $version, ">=" );
}

function pushdy_add_abandoned_cart($user_info = array()) {
  // error_log('Data ID: '.print_r(11, true).PHP_EOL, 3, $_SERVER['DOCUMENT_ROOT'] . "/product-data.log");
  $pushdy_wp_settings = get_option("PushdyWPSetting");
  if ($pushdy_wp_settings['app_rest_api_key']){
    $pushdy_post_url = 'https://api.pushdi.com/commerce';
    $request = array(
      'headers' => array(
                    'content-type' => 'application/json;charset=utf-8',
                    'client-key' => $pushdy_wp_settings['app_rest_api_key'],
          ),
      'body' => json_encode($user_info),
      'timeout' => 5,
      );
    $response = wp_remote_post($pushdy_post_url, $request);
  }
}

function pushdy_subscription_check(){
    return ((isset($_COOKIE['pushdy_subs_status']) && $_COOKIE['pushdy_subs_status']==='subscribed') && isset($_COOKIE['pushdy_token']) && $_COOKIE['pushdy_token']!='');
}

class Pushdy_Admin
{
    /**
     * Increment $RESOURCES_VERSION any time the CSS or JavaScript changes to view the latest changes.
     */
    private static $RESOURCES_VERSION = '42';
    private static $SAVE_POST_NONCE_KEY = 'pushdy_meta_box_nonce';
    private static $SAVE_POST_NONCE_ACTION = 'pushdy_meta_box';
    public static $SAVE_CONFIG_NONCE_KEY = 'pushdy_config_page_nonce';
    public static $SAVE_CONFIG_NONCE_ACTION = 'pushdy_config_page';

    public function __construct()
    {
    }

    public static function init()
    {
        $pushdy = new self();

        if (class_exists('WDS_Log_Post')) {
            function exception_error_handler($errno, $errstr, $errfile, $errline)
            {
                try {
                    switch ($errno) {
                      case E_USER_ERROR:
                          exit(1);
                          break;

                      case E_USER_WARNING:
                          break;

                      case E_USER_NOTICE || E_NOTICE:
                          break;

                      case E_STRICT:
                          break;

                      default:
                          break;
                  }

                    return true;
                } catch (Exception $ex) {
                    return true;
                }
            }

            set_error_handler('exception_error_handler');

            function fatal_exception_error_handler()
            {
                $error = error_get_last();
                try {
                    switch ($error['type']) {
                      case E_ERROR:
                      case E_CORE_ERROR:
                      case E_COMPILE_ERROR:
                      case E_USER_ERROR:
                      case E_RECOVERABLE_ERROR:
                      case E_CORE_WARNING:
                      case E_COMPILE_WARNING:
                      case E_PARSE:
                          pushdy_debug('[CRITICAL ERROR]', '('.$error['type'].') '.$error['message'].' @ '.$error['file'].':'.$error['line']);
                  }
                } catch (Exception $ex) {
                    return true;
                }
            }

            register_shutdown_function('fatal_exception_error_handler');
        }

        if (PushdyUtils::can_modify_plugin_settings()) {
            add_action('admin_menu', array(__CLASS__, 'add_admin_page'));
        }
        if (PushdyUtils::can_send_notifications()) {
            add_action('admin_init', array(__CLASS__, 'add_pushdy_post_options'));
        }

        add_action('save_post', array(__CLASS__, 'on_save_post'), 1, 3);
        add_action('transition_post_status', array(__CLASS__, 'on_transition_post_status'), 10, 3);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_styles'));

        return $pushdy;
    }

    public static function admin_styles()
    {
        wp_enqueue_style('pushdy-admin-styles', plugin_dir_url(__FILE__).'views/css/pushdy-menu-styles.css', false, Pushdy_Admin::$RESOURCES_VERSION);
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id the ID of the post being saved
     */
    public static function on_save_post($post_id, $post, $updated)
    {
        if ($post->post_type == 'wdslp-wds-log') {
            // Prevent recursive post logging
            return;
        }
        // Check if our nonce is set.
        if (!isset($_POST[Pushdy_Admin::$SAVE_POST_NONCE_KEY])) {
            // This is called on every new post ... not necessary to log it.
            return $post_id;
        }

        $nonce = $_POST[Pushdy_Admin::$SAVE_POST_NONCE_KEY];

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($nonce, Pushdy_Admin::$SAVE_POST_NONCE_ACTION)) {

            return $post_id;
        }

        /*
             * If this is an autosave, our form has not been submitted,
             * so we don't want to do anything.
             */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        /* OK, it's safe for us to save the data now. */

        $just_sent_notification = (get_post_meta($post_id, 'pushdy_notification_already_sent', true) == true);

        if ($just_sent_notification) {
            // Reset our flag
            update_post_meta($post_id, 'pushdy_notification_already_sent', false);

            return;
        }

        if (array_key_exists('pushdy_meta_box_present', $_POST)) {
            update_post_meta($post_id, 'pushdy_meta_box_present', true);
        } else {
            update_post_meta($post_id, 'pushdy_meta_box_present', false);
        }

        /* Even though the meta box always contains the checkbox, if an HTML checkbox is not checked, it is not POSTed to the server */
        if (array_key_exists('send_pushdy_notification', $_POST)) {
            update_post_meta($post_id, 'pushdy_send_notification', true);
        } else {
            update_post_meta($post_id, 'pushdy_send_notification', false);
        }
    }

    public static function add_pushdy_post_options()
    {
        // If there is an error or success message we should display, display it now
        function admin_notice_error()
        {
            $pushdy_transient_error = get_transient('pushdy_transient_error');
            if (!empty($pushdy_transient_error)) {
                delete_transient('pushdy_transient_error');
                echo $pushdy_transient_error;
            }

            $pushdy_transient_success = get_transient('pushdy_transient_success');
            if (!empty($pushdy_transient_success)) {
                delete_transient('pushdy_transient_success');
                echo $pushdy_transient_success;
            }
        }
        add_action('admin_notices', 'admin_notice_error');

        // Add our meta box for the "post" post type (default)
        add_meta_box('pushdy_notif_on_post',
                 'Pushdy Push Notifications',
                 array(__CLASS__, 'pushdy_notif_on_post_html_view'),
                 'post',
                 'side',
                 'high');

        // Then add our meta box for all other post types that are public but not built in to WordPress
        $args = array(
      'public' => true,
      '_builtin' => false,
    );
        $output = 'names';
        $operator = 'and';
        $post_types = get_post_types($args, $output, $operator);
        foreach ($post_types  as $post_type) {
            add_meta_box(
        'pushdy_notif_on_post',
        'Pushdy Push Notifications',
        array(__CLASS__, 'pushdy_notif_on_post_html_view'),
        $post_type,
        'side',
        'high'
      );
        }
    }

    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post the post object
     */
    public static function pushdy_notif_on_post_html_view($post)
    {
        $post_type = $post->post_type;
        $pushdy_wp_settings = Pushdy::get_pushdy_settings();

        // Add an nonce field so we can check for it later.
        wp_nonce_field(Pushdy_Admin::$SAVE_POST_NONCE_ACTION, Pushdy_Admin::$SAVE_POST_NONCE_KEY, true);

        // Our plugin config setting "Automatically send a push notification when I publish a post from the WordPress editor"
        $settings_send_notification_on_wp_editor_post = $pushdy_wp_settings['notification_on_post'];

        /* This is a scheduled post and the user checked "Send a notification on post publish/update". */
        $post_metadata_was_send_notification_checked = (get_post_meta($post->ID, 'pushdy_send_notification', true) == true);

        // We check the checkbox if: setting is enabled on Config page, post type is ONLY "post", and the post has not been published (new posts are status "auto-draft")
    $meta_box_checkbox_send_notification = ($settings_send_notification_on_wp_editor_post &&  // If setting is enabled
                                            $post->post_type == 'post' &&  // Post type must be type post for checkbox to be auto-checked
                                            in_array($post->post_status, array('future', 'draft', 'auto-draft', 'pending'))) || // Post is scheduled, incomplete, being edited, or is awaiting publication
                                            ($post_metadata_was_send_notification_checked);

        if (has_filter('pushdy_meta_box_send_notification_checkbox_state')) {
            $meta_box_checkbox_send_notification = apply_filters('pushdy_meta_box_send_notification_checkbox_state', $post, $pushdy_wp_settings);
        }
    ?>
	    <input type="hidden" name="pushdy_meta_box_present" value="true"></input>
      <input type="checkbox" name="send_pushdy_notification" value="true" <?php if ($meta_box_checkbox_send_notification) {
            echo 'checked';
        } ?>></input>
      <label>
        <?php if ($post->post_status == 'publish') {
            echo 'Send notification on '.$post_type.' update';
        } else {
            echo 'Send notification on '.$post_type.' publish';
        } ?>
      </label>
    <?php
    }

    public static function save_config_page($config)
    {
        if (!PushdyUtils::can_modify_plugin_settings()) {
            set_transient('pushdy_transient_error', '<div class="error notice pushdy-error-notice">
                    <p><strong>Pushdy Push:</strong><em> Only administrators are allowed to save plugin settings.</em></p>
                </div>', 86400);

            return;
        }

        $sdk_dir = plugin_dir_path(__FILE__).'sdk_files/';
        $pushdy_wp_settings = Pushdy::get_pushdy_settings();

        $request = array(
          'headers' => array(
                        'content-type' => 'application/json;charset=utf-8',
                        'client-key' => sanitize_text_field($config['app_rest_api_key']),
              ),
          'timeout' => 5,
          );

        $response = wp_remote_post(
          'https://api.pushdi.com/token_info', $request);

        if (is_wp_error($response) || !is_array($response) || !isset($response['body'])) {
            set_transient('pushdy_transient_error', '<div class="error notice pushdy-error-notice">
                    <p><strong>Pushdy Push:</strong><em> Wrong client key.</em></p>
                </div>', 86400);

            return;
        }

        $response_body = json_decode($response['body'], true);
        $pushdy_wp_settings['app_id'] = $response_body['app_id'];
        $pushdy_wp_settings['app_uuid'] = $response_body['app_uuid'];

        $pushdy_wp_settings['is_site_https_firsttime'] = 'set';
        $fields = array(
          'hour' => $config['woocommerce_abandoned_cart_time'],
          'headings' => $config['woocommerce_abandoned_cart_heading'],
          'contents' => $config['woocommerce_abandoned_cart_content']
        );
        if(pushdy_woocommerce_version_check('2.5')){
          $fields['checkout_url'] = wc_get_cart_url();
        }
        else{
          $fields['checkout_url'] = $woocommerce->cart->get_cart_url();
        }
        if (isset($config['woocommerce_abandoned_cart'])){
          $fields['enable'] = true;
        } else {
          $fields['enable'] = false;
        }
        $request_commerce = array(
          'headers' => array(
                        'content-type' => 'application/json;charset=utf-8',
                        'client-key' => sanitize_text_field($config['app_rest_api_key']),
              ),
          'body' => json_encode($fields),
          'timeout' => 5,
        );
        $response = wp_remote_post('https://api.pushdi.com/application/enable_woocomerce', $request_commerce);

        $booleanSettings = array(
      'is_site_https',
      'prompt_auto_register',
      'use_modal_prompt',
      'send_welcome_notification',
      'notification_on_post',
      'notification_on_post_from_plugin',
      'showNotificationIconFromPostThumbnail',
      'showNotificationImageFromPostThumbnail',
      'show_gcm_sender_id',
      'use_custom_manifest',
      'use_custom_sdk_init',
      'show_notification_send_status_message',
      'use_http_permission_request',
      'customize_http_permission_request',
      'use_slidedown_permission_message_for_https',
      'woocommerce_abandoned_cart',
    );
        Pushdy_Admin::saveBooleanSettings($pushdy_wp_settings, $config, $booleanSettings);

        $stringSettings = array(
      'app_rest_api_key',
      'utm_additional_url_params',
      'allowed_custom_post_types',
      'notification_title',
      'custom_manifest_url',
      'http_permission_request_modal_title',
      'http_permission_request_modal_message',
      'http_permission_request_modal_button_text',
      'persist_notifications',
      'woocommerce_abandoned_cart_time',
      'woocommerce_abandoned_cart_content',
      'woocommerce_abandoned_cart_heading'
    );
        Pushdy_Admin::saveStringSettings($pushdy_wp_settings, $config, $stringSettings);

        Pushdy::save_pushdy_settings($pushdy_wp_settings);

        return $pushdy_wp_settings;
    }

    public static function saveBooleanSettings(&$pushdy_wp_settings, &$config, $settings)
    {
        foreach ($settings as $setting) {
            if (array_key_exists($setting, $config)) {
                $pushdy_wp_settings[$setting] = true;
            } else {
                $pushdy_wp_settings[$setting] = false;
            }
        }
    }

    public static function saveStringSettings(&$pushdy_wp_settings, &$config, $settings)
    {
        foreach ($settings as $setting) {
            if (array_key_exists($setting, $config)) {
                $value = $config[$setting];
                $value = PushdyUtils::normalize($value);
                $pushdy_wp_settings[$setting] = $value;
            }
        }
    }

    public static function add_admin_page()
    {
        $Pushdy_menu = add_menu_page('Pushdy',
                                    'Pushdy',
                                    'manage_options',
                                    'pushdy-push',
                                    array(__CLASS__, 'admin_menu')
    );

        Pushdy_Admin::save_config_settings_form();

        add_action('load-'.$Pushdy_menu, array(__CLASS__, 'admin_custom_load'));
    }

    public static function save_config_settings_form()
    {
        // If the user is trying to save the form, require a valid nonce or die
        if (array_key_exists('app_rest_api_key', $_POST)) {
            // check_admin_referer dies if not valid; no if statement necessary
            check_admin_referer(Pushdy_Admin::$SAVE_CONFIG_NONCE_ACTION, Pushdy_Admin::$SAVE_CONFIG_NONCE_KEY);
            $pushdy_wp_settings = Pushdy_Admin::save_config_page($_POST);
        }
    }

    public static function admin_menu()
    {
        require_once plugin_dir_path(__FILE__).'/views/config.php';
    }

    public static function admin_custom_load()
    {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_custom_scripts'));

        $pushdy_wp_settings = Pushdy::get_pushdy_settings();
        if (
          $pushdy_wp_settings['app_rest_api_key'] == ''
      ) {
            function admin_notice_setup_not_complete()
            {
                ?>
              <script>
                  document.addEventListener('DOMContentLoaded', function() {
                      // activateSetupTab('setup/0');
                  });
              </script>
			  <div class="error notice pushdy-error-notice">
				  <p><strong>Pushdy Push:</strong> <em>Your setup is not complete. Please follow the Setup guide to set up web push notifications. Both the App ID and REST API Key fields are required.</em></p>
			  </div>
			  <?php
            }

            add_action('admin_notices', 'admin_notice_setup_not_complete');
        }

        if (!function_exists('curl_init')) {
            function admin_notice_curl_not_installed()
            {
                ?>
			  <div class="error notice pushdy-error-notice">
				  <p><strong>Pushdy Push:</strong> <em>cURL is not installed on this server. cURL is required to send notifications. Please make sure cURL is installed on your server before continuing.</em></p>
			  </div>
			  <?php
            }
            add_action('admin_notices', 'admin_notice_curl_not_installed');
        }
    }

    public static function admin_custom_scripts()
    {
        add_filter('admin_footer_text', 'pushdy_change_footer_admin', 9999); // 9999 means priority, execute after the original fn executes

        wp_enqueue_style('icons', plugin_dir_url(__FILE__).'views/css/icons.css', false, Pushdy_Admin::$RESOURCES_VERSION);
        wp_enqueue_style('semantic-ui', plugin_dir_url(__FILE__).'views/css/semantic-ui.css', false, Pushdy_Admin::$RESOURCES_VERSION);
        wp_enqueue_style('site', plugin_dir_url(__FILE__).'views/css/site.css', false, Pushdy_Admin::$RESOURCES_VERSION);

        wp_enqueue_script('jquery');
        wp_enqueue_script('semantic-ui', plugin_dir_url(__FILE__).'views/javascript/semantic-ui.js', false, Pushdy_Admin::$RESOURCES_VERSION);
        wp_enqueue_script('jquery.cookie', plugin_dir_url(__FILE__).'views/javascript/jquery.cookie.js', false, Pushdy_Admin::$RESOURCES_VERSION);
        wp_enqueue_script('site', plugin_dir_url(__FILE__).'views/javascript/site-admin.js', false, Pushdy_Admin::$RESOURCES_VERSION);
    }

    /**
     * Returns true if more than one notification has been sent in the last minute.
     */
    public static function get_sending_rate_limit_wait_time()
    {
        $last_send_time = get_option('pushdy.last_send_time');
        if ($last_send_time) {
            $time_elapsed_since_last_send = PUSHDY_API_RATE_LIMIT_SECONDS - (current_time('timestamp') - intval($last_send_time));
            if ($time_elapsed_since_last_send > 0) {
                return $time_elapsed_since_last_send;
            }
        }

        return false;
    }

    /**
     * Updates the last sent timestamp, used in rate limiting notifications sent more than 1 per minute.
     */
    public static function update_last_sent_timestamp()
    {
        update_option('pushdy.last_send_time', current_time('timestamp'));
    }

    /**
     * hashes notification-title+timestamp and converts it into a uuid
     * meant to prevent duplicate notification issue started with wp5.0.0.
     *
     * @title - title of post
     * return - uuid of sha1 hash of post title + post timestamp
     */
    public static function uuid($title)
    {
        $now = explode(':', date('z:H:i'));
        $now_minutes = $now[0] * 60 * 24 + $now[1] * 60 + $now[2];
        $prev_minutes = get_option('TimeLastUpdated');
        $prehash = (string) $title;

        if ($prev_minutes !== false && ($now_minutes - $prev_minutes) > 0) {
            update_option('TimeLastUpdated', $now_minutes);
            $timestamp = $now_minutes;
        } elseif ($prev_minutes == false) {
            add_option('TimeLastUpdated', $now_minutes);
            $timestamp = $now_minutes;
        } else {
            $timestamp = $prev_minutes;
        }

        $prehash = $prehash.$timestamp;

        $sha1 = substr(sha1($prehash), 0, 32);

        return substr($sha1, 0, 8).'-'.substr($sha1, 8, 4).'-'.substr($sha1, 12, 4).'-'.substr($sha1, 16, 4).'-'.substr($sha1, 20, 12);
    }

    /**
     * The main function that actually sends a notification to Pushdy.
     */
    public static function send_notification_on_wp_post($new_status, $old_status, $post)
    {
        try {
            if (!function_exists('curl_init')) {
                return;
            }

            $time_to_wait = self::get_sending_rate_limit_wait_time();
            if ($time_to_wait > 0) {
                set_transient('pushdy_transient_error', '<div class="error notice pushdy-error-notice">
                    <p><strong>Pushdy Push:</strong><em> Please try again in '.$time_to_wait.' seconds. Only one notification can be sent every '.PUSHDY_API_RATE_LIMIT_SECONDS.' seconds.</em></p>
                </div>', 86400);

                return;
            }

            $pushdy_wp_settings = Pushdy::get_pushdy_settings();

            /* Returns true if there is POST data */
            $was_posted = !empty($_POST);

            /* When this post was created or updated, the Pushdy meta box in the WordPress post editor screen was visible */
            $pushdy_meta_box_present = $was_posted && array_key_exists('pushdy_meta_box_present', $_POST) && $_POST['pushdy_meta_box_present'] == 'true';
            /* The checkbox "Send notification on post publish/update" on the Pushdy meta box is checked */
            $pushdy_meta_box_send_notification_checked = $was_posted && array_key_exists('send_pushdy_notification', $_POST) && $_POST['send_pushdy_notification'] == 'true';

            /* This is a scheduled post and the Pushdy meta box was present. */
            $post_metadata_was_pushdy_meta_box_present = (get_post_meta($post->ID, 'pushdy_meta_box_present', true) == true);
            /* This is a scheduled post and the user checked "Send a notification on post publish/update". */
            $post_metadata_was_send_notification_checked = (get_post_meta($post->ID, 'pushdy_send_notification', true) == true);

            /* Either we were just posted from the WordPress post editor form, or this is a scheduled notification and it was previously submitted from the post editor form */
            $posted_from_wordpress_editor = $pushdy_meta_box_present || $post_metadata_was_pushdy_meta_box_present;
            /* ********************************************************************************************************* */

            /* Settings related to creating a post outside of the WordPress editor NOT displaying the Pushdy meta box
             ************************************************************************************************************/

            /* Pushdy plugin setting "Automatically send a push notification when I create a post from 3rd party plugins"
             * If set to true, send only if *publishing* a post type *post* from *something other than the default WordPress editor*.
             * The filter hooks "pushdy_exclude_post" and "pushdy_include_post" can override this behavior as long as the option to automatically send from 3rd party plugins is set.
             */
            $settings_send_notification_on_non_editor_post_publish = $pushdy_wp_settings['notification_on_post_from_plugin'];
            $additional_custom_post_types_string = str_replace(' ', '', $pushdy_wp_settings['allowed_custom_post_types']);
            $additional_custom_post_types_array = array_filter(explode(',', $additional_custom_post_types_string));
            $non_editor_post_publish_do_send_notification = $settings_send_notification_on_non_editor_post_publish &&
                                                        ($post->post_type == 'post' || in_array($post->post_type, $additional_custom_post_types_array)) &&
                                                        $old_status !== 'publish';
            /* ********************************************************************************************************* */

            if ($posted_from_wordpress_editor) {
                // Decide to send based on whether the checkbox "Send notification on post publish/update" is checked
                // This post may be scheduled or just submitted from the WordPress editor
                // Metadata may not be saved into post yet, so use $_POST form data if metadata not available
                $do_send_notification = ($was_posted && $pushdy_meta_box_send_notification_checked) ||
                                    (!$was_posted && $post_metadata_was_send_notification_checked);
            } else {
                // This was definitely not submitted via the WordPress editor
                // Decide to send based on whether the 3rd-party plugins setting is checked
                $do_send_notification = $non_editor_post_publish_do_send_notification;
            }

            if (has_filter('pushdy_include_post')) {
                if (apply_filters('pushdy_include_post', $new_status, $old_status, $post)) {
                    $do_send_notification = true;
                }
            }

            if ($do_send_notification) {
                update_post_meta($post->ID, 'pushdy_meta_box_present', false);
                update_post_meta($post->ID, 'pushdy_send_notification', false);

                /* Some WordPress environments seem to be inconsistent about whether on_save_post is called before transition_post_status
                 * This sets the metadata back to true, and will cause a post to be sent even if the checkbox is not checked the next time
                 * We remove all related $_POST data to prevent this
                */
                if ($was_posted) {
                    if (array_key_exists('pushdy_meta_box_present', $_POST)) {
                        unset($_POST['pushdy_meta_box_present']);
                    }
                    if (array_key_exists('send_pushdy_notification', $_POST)) {
                        unset($_POST['send_pushdy_notification']);
                    }
                }

                $notif_content = PushdyUtils::decode_entities(get_the_title($post->ID));

                $site_title = '';
                if ($pushdy_wp_settings['notification_title'] != '') {
                    $site_title = PushdyUtils::decode_entities($pushdy_wp_settings['notification_title']);
                } else {
                    $site_title = PushdyUtils::decode_entities(get_bloginfo('name'));
                }

                if (function_exists('qtrans_getLanguage')) {
                    try {
                        $qtransLang = qtrans_getLanguage();
                        $site_title = qtrans_use($qtransLang, $site_title, false);
                        $notif_content = qtrans_use($qtransLang, $notif_content, false);
                    } catch (Exception $e) {
                    }
                }

                $post_time = get_post_time('U', true, $post);

                if (!$post_time) {
                    error_log("Pushdy: Couldn't get post_time");

                    return;
                }

                $post_time = $post_time + 10;

                $old_uuid_array = get_post_meta($post->ID, 'uuid');
                $uuid = self::uuid($notif_content);
                update_post_meta($post->ID, 'uuid', $uuid);

                $fields = array(
                  'type' => 'web_push',
                  'external_id' => $uuid,
                  'app_id' => $pushdy_wp_settings['app_id'],
                  'headings' => $site_title,
                  'filters' => [], 
                  'data' => array('url' => get_permalink($post->ID)),
                  'contents' => $notif_content,
                );
                
                if ($new_status == 'future') {
                    if ($old_uuid_array && $old_uuid_array[0] != $uuid) {
                        self::cancel_scheduled_notification($post);
                    }
                    
                    $fields['schedule'] = $post_time;
                }

                $config_utm_additional_url_params = $pushdy_wp_settings['utm_additional_url_params'];
                if (!empty($config_utm_additional_url_params)) {
                    $fields['data']['url'] .= '?'.$config_utm_additional_url_params;
                }

                if (has_post_thumbnail($post->ID)) {

                    $post_thumbnail_id = get_post_thumbnail_id($post->ID);
                    // Higher resolution (2x retina, + a little more) for the notification small icon
                    $thumbnail_sized_images_array = wp_get_attachment_image_src($post_thumbnail_id, array(192, 192), true);
                    // Much higher resolution for the notification large image
                    $large_sized_images_array = wp_get_attachment_image_src($post_thumbnail_id, 'large', true);

                    $config_use_featured_image_as_icon = $pushdy_wp_settings['showNotificationIconFromPostThumbnail'] == '1';

                    // get the icon image from wordpress if it exists
                    if ($config_use_featured_image_as_icon) {
                        $thumbnail_image = $thumbnail_sized_images_array[0];
                        $fields['data']['icon'] = preg_replace("/^http:/i", "https:", $thumbnail_image);
                    }
                }

                if (has_filter('pushdy_send_notification')) {
                    $fields = apply_filters('pushdy_send_notification', $fields, $new_status, $old_status, $post);

                    // If the filter adds "do_send_notification: false", do not send a notification
                    if (array_key_exists('do_send_notification', $fields) && $fields['do_send_notification'] == false) {
                        return;
                    }
                }

                $pushdy_post_url = 'https://api.pushdi.com/notification';

                $request = array(
                  'headers' => array(
                                'content-type' => 'application/json;charset=utf-8',
                                'client-key' => $pushdy_wp_settings['app_rest_api_key'],
                      ),
                  'body' => json_encode($fields),
                  'timeout' => 5,
                  );

                $response = wp_remote_post($pushdy_post_url, $request);

                if (is_wp_error($response) || !is_array($response) || !isset($response['body'])) {
                    $status = $response->get_error_code(); 				// custom code for WP_ERROR
                    $error_message = $response->get_error_message();
                    error_log('There was a '.$status.' error returned from Pushdy: '.$error_message);

                    return;
                }
                
                if (isset($response['body'])) {
                    $response_body = json_decode($response['body'], true);
                }

                if (isset($response['response'])) {
                    $status = $response['response']['code'];
                }

                update_post_meta($post->ID, 'response_body', json_encode($response_body));
                update_post_meta($post->ID, 'status', $status);

                if ($status != 200) {
                    error_log('There was a '.$status.' error sending your notification.');
                    if ($status != 0) {
                        set_transient('pushdy_transient_error', '<div class="error notice pushdy-error-notice">
                    <p><strong>Pushdy Push:</strong><em> There was a '.$status.' error sending your notification.</em></p>
                </div>', 86400);
                    } else {
                        // A 0 HTTP status code means the connection couldn't be established
                        set_transient('pushdy_transient_error', '<div class="error notice pushdy-error-notice">
                    <p><strong>Pushdy Push:</strong><em> There was an error establishing a network connection. Please make sure outgoing network connections from cURL are allowed.</em></p>
                </div>', 86400);
                    }
                } else {
                    if (!empty($response)) {

                        // API can send a 200 OK even if the notification failed to send
                        if (isset($response['body'])) {
                            $response_body = json_decode($response['body'], true);
                            update_post_meta($post->ID, 'response_body', $response_body);

                            if (isset($response_body['id'])) {
                                $notification_id = $response_body['id'];
                            } else {
                                error_log('Pushdy: notification id not set in response body');
                            }
                        } else {
                            error_log('Pushdy: body not set in HTTP response');
                        }

                        // updates meta for use in cancelling scheduled notifs
                        update_post_meta($post->ID, 'notification_id', $notification_id);

                        $config_show_notification_send_status_message = $pushdy_wp_settings['show_notification_send_status_message'] == '1';

                        if ($config_show_notification_send_status_message) {
                                set_transient('pushdy_transient_success', '<div class="components-notice is-success is-dismissible">
                  <div class="components-notice__content">
                  <p><strong>Pushdy Push:</strong><em> Successfully a notification to recipients. Go to your app\'s "Delivery" tab to check sent and scheduled messages: <a target="_blank" href="https://app.pushdy.com/apps/">https://app.pushdy.com/apps/</a></em></p>
                  </div>
                    </div>', 86400);
                        }
                    }
                }

                self::update_last_sent_timestamp();

                return $response;
            }
        } catch (Exception $e) {
        }
    }

    public static function was_post_restored_from_trash($old_status, $new_status)
    {
        return $old_status === 'trash' && $new_status === 'publish';
    }

    public static function cancel_scheduled_notification($post)
    {
        $notification_id = get_post_meta($post->ID, 'notification_id', true);
        $pushdy_wp_settings = Pushdy::get_pushdy_settings();

        $pushdy_delete_url = 'https://api.pushdi.com/notification/'.$notification_id;

        $request = array(
      'headers' => array(
                'content-type' => 'application/json;charset=utf-8',
                'client-key' => $pushdy_wp_settings['app_rest_api_key'],
    ),
      'method' => 'DELETE',
      'timeout' => 5,
    );

        $response = wp_remote_get($pushdy_delete_url, $request);

        if (is_wp_error($response) || !is_array($response) || !isset($response['body'])) {
            $status = $response->get_error_code(); 				// custom code for WP_ERROR
            $error_message = $response->get_error_message();
            error_log("Couldn't cancel notification: There was a ".$status.' error returned from Pushdy: '.$error_message);

            return;
        }
    }

    public static function on_transition_post_status($new_status, $old_status, $post)
    {
        if ($post->post_type == 'wdslp-wds-log' || self::was_post_restored_from_trash($old_status, $new_status)) {
            return;
        }

        if ($new_status == 'future') {
            self::send_notification_on_wp_post($new_status, $old_status, $post);

            return;
        }

        if (has_filter('pushdy_include_post')) {
            if (apply_filters('pushdy_include_post', $new_status, $old_status, $post)) {
                self::send_notification_on_wp_post($new_status, $old_status, $post);

                return;
            }
        }

        if (has_filter('pushdy_exclude_post')) {
            if (apply_filters('pushdy_exclude_post', $new_status, $old_status, $post)) {
                return;
            }
        }

        if (!(empty($post) ||
        $new_status !== 'publish' ||
        $post->post_type == 'page')) {
            self::send_notification_on_wp_post($new_status, $old_status, $post);
        }
    }
}

?>
