<?php

defined('ABSPATH') or die('This page may not be accessed directly.');

function pushdy_debug()
{
    if (!defined('PUSHDY_DEBUG') && !class_exists('WDS_Log_Post')) {
        return;
    }
    $numargs = func_num_args();
    $arg_list = func_get_args();
    $bt = debug_backtrace();
    $output = '['.$bt[1]['function'].'] ';
    for ($i = 0; $i < $numargs; ++$i) {
        $arg = $arg_list[$i];

        if (is_string($arg)) {
            $arg_output = $arg;
        } else {
            $arg_output = var_export($arg, true);
        }

        if ($arg === '') {
            $arg_output = '""';
        } elseif ($arg === null) {
            $arg_output = 'null';
        }

        $output = $output.$arg_output.' ';
    }
    $output = substr($output, 0, -1);
    $output = substr($output, 0, 1024); // Restrict messages to 1024 characters in length
    if (defined('PUSHDY_DEBUG')) {
        error_log('Pushdy: '.$output);
    }
    if (class_exists('WDS_Log_Post')) {
        $num_log_posts = wp_count_posts('wdslp-wds-log', 'readable');
        // Limit the total number of log entries to 500
        if ($num_log_posts && property_exists($num_log_posts, 'publish') && $num_log_posts->publish < 500) {
            WDS_Log_Post::log_message($output, '', 'general');
        }
    }
}

function pushdy_debug_post($post)
{
    if (!$post) {
        return;
    }

    return pushdy_debug('Post:', array('ID' => $post->ID,
                        'Post Date' => $post->post_date,
                        'Modified Date' => $post->post_modified,
                        'Title' => $post->post_title,
                        'Status:' => $post->post_status,
                        'Type:' => $post->post_type, ));
}

class Pushdy_Public
{
    public function __construct()
    {
    }

    public static function init()
    {
        add_action('wp_head', array(__CLASS__, 'pushdy_header'), 10);
    }

    // For easier debugging of sites by identifying them as WordPress
    public static function insert_pushdy_stamp()
    {
        ?>
      <meta name="pushdy" content="wordpress-plugin"/>
    <?php
    }

    public static function pushdy_header()
    {
        $pushdy_wp_settings = Pushdy::get_pushdy_settings();
        if ($pushdy_wp_settings['app_uuid']){       
        ?>
    <?php
    $sdk_dir = plugin_dir_url(__FILE__).'sdk_files/PushdySDKWorker.js.php';
    echo '<script src="https://sdk.pushdi.com/js/generated/'. $pushdy_wp_settings['app_uuid'] . '.js" async></script>';
    ?>
    <script>

        var PushdyIns = window.PushdyIns || [];
        PushdyIns.push(function() {
          PushdyIns.initApp({
            sw_path: <?php
                echo '\''.$sdk_dir.'\'';
            ?>
          });
        });

    </script>

<?php
        }
    }
}
?>
