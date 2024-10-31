<?php

defined('ABSPATH') or die('This page may not be accessed directly.');

if (!PushdyUtils::can_modify_plugin_settings()) {
    // Exit if the current user does not have permission
    die('Insufficient permissions to access config page.');
}

// The user is just viewing the config page; this page cannot be accessed directly
$pushdy_wp_settings = Pushdy::get_pushdy_settings();
?>

<header class="pushdy">
  <a href="https://www.pushdy.vn" target="_blank">
    <div class="pushdy logo" id="logo-pushdy" style="width: 250px; height: 52px; margin: 0 auto;">&nbsp;</div>
  </a>
</header>
<div class="outer site pushdy container">
  <div class="ui site pushdy container" id="content-container">
    <div class="ui pointing stackable menu">
      <!-- <a class="item" data-tab="setup">Setup</a> -->
      <a class="active item" data-tab="configuration">Cấu hình</a>
    </div>
    <div class="ui borderless shadowless active tab segment" style="z-index: 1; padding-top: 0; padding-bottom: 0;" data-tab="configuration">
    <div class="ui special padded raised stack segment">
      <form class="ui form" role="configuration" action="#" method="POST">
        <?php
        // Add an nonce field so we can check for it later.
        wp_nonce_field(Pushdy_Admin::$SAVE_CONFIG_NONCE_ACTION, Pushdy_Admin::$SAVE_CONFIG_NONCE_KEY, true);
        ?>
        <div class="ui dividing header">
          <i class="setting icon"></i>
          <div class="content">
            Thiết lập tài khoản
          </div>
        </div>
        <div class="ui borderless shadowless segment">
          <div class="field">
            <label>Trang của bạn phải sử dụng kết nối HTTPS (SSL)<i class="tiny circular help icon link" role="popup" data-html="<p>Kiểm tra nếu trang của bạn là HTTPS:</p><img src='<?php echo PUSHDY_PLUGIN_URL.'views/images/settings/https-url.png'; ?>' width=619>" data-variation="flowing"></i></label>
          </div>
          <div class="field">
            <label>Client Key<i class="tiny circular help icon link" role="popup" data-title="Rest API Key" data-content="Client Key ứng dụng của bạn. Bạn có thể tìm nó trong https://dashboard.pushdy.com/#/application" data-variation="wide"></i></label>
            <input type="text" name="app_rest_api_key" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr($pushdy_wp_settings['app_rest_api_key']); ?>">
          </div>
          <!-- <div class="field">
            <label>App Id</label>
            <input type="text" name="app_id" value="<?php echo esc_attr($pushdy_wp_settings['app_id']); ?>">
          </div>
          <div class="field">
            <label>App Uuid</label>
            <input type="text" name="app_uuid" value="<?php echo esc_attr($pushdy_wp_settings['app_uuid']); ?>">
          </div> -->
        </div>
        <div class="ui dividing header">
          <i class="desktop icon"></i>
          <div class="content">
            Thiết lập thông báo
          </div>
        </div>
        <div class="ui borderless shadowless segment">
          <div class="field">
            <div class="ui toggle checkbox">
              <input type="checkbox" name="showNotificationIconFromPostThumbnail" value="true" <?php if ($pushdy_wp_settings['showNotificationIconFromPostThumbnail']) {
                       echo 'checked';
                   } ?>>
              <label>Sử dụng hình ảnh bải viết làm biểu tượng của thông báo<i class="tiny circular help icon link" role="popup" data-title="" data-content="Sử dụng hình ảnh bải viết làm biểu tượng của thông báo (biểu tượng nhỏ). Hỗ trợ trình duyệt Chrome và Firefox." data-variation="wide"></i></label>
            </div>
          </div>
          <div class="field">
              <label>Tiêu đề thông báo<i class="tiny circular help icon link" role="popup" data-html="Tiêu đề sử dụng cho mọi thông báo được gửi đi. Mặc định là tiêu đề trang của bạn." data-variation="wide"></i></label>
              <input type="text" name="notification_title" placeholder="<?php echo PushdyUtils::decode_entities(get_bloginfo('name')); ?>" value="<?php echo esc_attr(@$pushdy_wp_settings['notification_title']); ?>">
          </div>
        </div>
        <div class="ui dividing header">
          <i class="wizard icon"></i>
          <div class="content">
            Thiết lập thông báo tự động
          </div>
        </div>
        <div class="ui borderless shadowless segment">
          <div class="field">
            <div class="ui toggle checkbox">
              <input type="checkbox" name="notification_on_post" value="true" <?php if ($pushdy_wp_settings['notification_on_post']) {
                      echo 'checked';
                  } ?>>
              <label>Tự động gửi thông báo khi có bài viết mới được xuất bản từ Wordpress editor<i class="tiny circular help icon link" role="popup" data-title="" data-variation="wide"></i></label>
            </div>
          </div>
          <div class="field">
            <div class="ui toggle checkbox">
              <input type="checkbox" name="notification_on_post_from_plugin" value="true" <?php if (@$pushdy_wp_settings['notification_on_post_from_plugin']) {
                      echo 'checked';
                  } ?>>
              <label>Tự động gửi thông báo khi có bài viết mới được xuất bản từ bên thứ 3<i class="tiny circular help icon link" role="popup" data-title="" data-variation="wide"></i></label>
            </div>
          </div>
        </div>
        <div class="ui dividing header">
          <i class="area chart icon"></i>
          <div class="content">
            Thiết lập thông số UTM
          </div>
        </div>
        <div class="ui borderless shadowless segment">
          <div class="field">
            <label>Bổ sung thuộc tính vào đường dẫn<i class="tiny circular help icon link" role="popup" data-variation="wide"></i></label>
            <input type="text" placeholder="utm_medium=ppc&utm_source=adwords&utm_campaign=snow%20boots&utm_content=durable%20%snow%boots" name="utm_additional_url_params" value="<?php echo PushdyUtils::html_safe(@$pushdy_wp_settings['utm_additional_url_params']); ?>">
          </div>
        </div>
        <div class="ui dividing header">
          <i class="wizard icon"></i>
          <div class="content">
            Woocommerce
          </div>
        </div>
        <div class="ui borderless shadowless segment">
          <div class="field">
            <div class="ui toggle checkbox">
              <input type="checkbox" name="woocommerce_abandoned_cart" value="true" <?php if ($pushdy_wp_settings['woocommerce_abandoned_cart']) {
                      echo 'checked';
                  } ?>>
              <label style="display: inline;">Thông báo quên sản phẩm trong giỏ hàng sau</label><input type="number" name="woocommerce_abandoned_cart_time" style="width: 4.5rem;" value="<?php echo esc_attr(@$pushdy_wp_settings['woocommerce_abandoned_cart_time']); ?>"><span style="font-family: 'Lato';font-size: 1.2rem!important;font-weight: 500;"> giờ</span>
            </div>
            <div>
              <label>Tiêu đề</label>
              <input type="text" name="woocommerce_abandoned_cart_heading" value="<?php echo esc_attr($pushdy_wp_settings['woocommerce_abandoned_cart_heading']); ?>">
            </div>
            <div>
              <label>Nội dung</label>
              <input type="text" name="woocommerce_abandoned_cart_content" value="<?php echo esc_attr($pushdy_wp_settings['woocommerce_abandoned_cart_content']); ?>">
            </div>
          </div>
        </div> 
        </div>
        <button class="ui large teal button" type="submit">Save</button>
        <div class="ui inline validation nag">
            <span class="title">
              Your Pushdy subdomain cannot be empty or less than 4 characters. Use the same one you entered on the platform settings at pushdy.com.
            </span>
          <i class="close icon"></i>
        </div>
      </form>
    </div>
    </div>
  </div>
</div>
