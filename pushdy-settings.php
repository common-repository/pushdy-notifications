<?php

defined( 'ABSPATH' ) or die('This page may not be accessed directly.');

class Pushdy {
  public static function get_pushdy_settings() {
    $defaults = array(
                  'app_id' => '',
                  'app_uuid' => '',
                  'notification_on_post' => true,
                  'notification_on_post_from_plugin' => false,
                  'is_site_https_firsttime' => 'unset',
                  'is_site_https' => false,
                  'subdomain' => "",
                  'origin' => "", 
                  'default_title' => "",
                  'default_icon' => "",
                  'default_url' => "",
                  'app_rest_api_key' => "",
                  'showNotificationIconFromPostThumbnail' => true,
                  'utm_additional_url_params' => '',
                  'allowed_custom_post_types' => '',
                  'notification_title' => PushdyUtils::decode_entities(get_bloginfo('name')),
                  'use_custom_sdk_init' => false,
                  'show_notification_send_status_message' => true,
                  'woocommerce_abandoned_cart' => false,
                  'woocommerce_abandoned_cart_time' => 2,
                  'woocommerce_abandoned_cart_heading' => '{{__cart}} item(s) in your cart!!!',
                  'woocommerce_abandoned_cart_content' => 'Hey, you forgot {{__cart}} item(s) in your cart, check out now!'
                  );

    $legacies = array(
    );

    $is_new_user = false;

    // If not set or empty, load a fresh empty array
    if (!isset($pushdy_wp_settings)) {
      $pushdy_wp_settings = get_option("PushdyWPSetting");
      if (empty( $pushdy_wp_settings )) {
         $is_new_user = true;
         $pushdy_wp_settings = array();
      }
    }

    // Assign defaults if the key doesn't exist in $pushdy_wp_settings
    reset($defaults);
    foreach ($defaults as $key => $value) {
      if (!array_key_exists($key, $pushdy_wp_settings)) {
          $pushdy_wp_settings[$key] = $value;
      }
    }

    return apply_filters( 'pushdy_get_settings', $pushdy_wp_settings );
  }

  public static function save_pushdy_settings($settings) {
    $pushdy_wp_settings = $settings;
    update_option("PushdyWPSetting", $pushdy_wp_settings);
  }
}
?>
