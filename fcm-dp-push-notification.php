<?php
/*
    Plugin Name: FCM Push Notification from WordPress
    Description: Notify your users using Firebase Cloud Messaging (FCM) when content is published or updated
    Version: 1.4.2
    Author: @anthlasserre [monapp.club]
    Author URI: https://monapp.club/
    License: GPL2
    License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined("FCMDPPLGPN_VERSION_CURRENT")) define("FCMDPPLGPN_VERSION_CURRENT", '1');
if (!defined("FCMDPPLGPN_URL")) define("FCMDPPLGPN_URL", plugin_dir_url(__FILE__));
if (!defined("FCMDPPLGPN_PLUGIN_DIR")) define("FCMDPPLGPN_PLUGIN_DIR", plugin_dir_path(__FILE__));
if (!defined("FCMDPPLGPN_PLUGIN_NM")) define("FCMDPPLGPN_PLUGIN_NM", 'FCM Push Notification from WP');
if (!defined("FCMDPPLGPN_TRANSLATION")) define("FCMDPPLGPN_TRANSLATION", 'fcmdpplgpn_translation');

/* FCMDPPLGPN -> FCM DP(dprogrammer) PLG(plugin) PN(Push Notification) */
class FCMDPPLGPN_Push_Notification
{

    public function __construct()
    {

        // Installation and uninstallation hooks
        register_activation_hook(__FILE__, array($this, 'fcmdpplgpn_activate'));
        register_deactivation_hook(__FILE__, array($this, 'fcmdpplgpn_deactivate'));
        add_action('admin_menu', array($this, 'fcmdpplgpn_setup_admin_menu'));
        add_action('admin_init', array($this, 'fcmdpplgpn_settings'));

        add_action('add_meta_boxes', array($this, 'fcmdpplgpn_featured_meta'), 1);
        add_action('save_post', array($this, 'fcmdpplgpn_meta_save'), 1);
        add_filter('plugin_action_links_fcm-push-notification-from-wp/fcm-dp-push-notification.php', array($this, 'fcmdpplgpn_settings_link'));
    }

    function fcmdpplgpn_featured_meta()
    {
        //add_meta_box( 'fcmdpplgpn_ckmeta_send_notification', __( 'Push Notification', FCMDPPLGPN_TRANSLATION ), array($this, 'fcmdpplgpn_meta_callback'), 'post', 'side', 'high', null );

        /* set meta box to a post type */
        $args  = array(
            'public' => true,
        );

        $post_types = get_post_types($args, 'objects');

        if ($post_types) { // If there are any custom public post types.

            foreach ($post_types  as $post_type) {
                if ($post_type->name != 'attachment') {
                    if ($this->get_options_posttype($post_type->name)) {
                        add_meta_box('fcmdpplgpn_ckmeta_send_notification', esc_attr(__('Push Notification', FCMDPPLGPN_TRANSLATION)), array($this, 'fcmdpplgpn_meta_callback'), $post_type->name, 'side', 'high', null);
                    }
                }
            }
        }
    }

    function fcmdpplgpn_meta_callback($post)
    {
        global $pagenow;
        wp_nonce_field(basename(__FILE__), 'fcmdpplgpn_nonce');
        $fcmdpplgpn_stored_meta = get_post_meta($post->ID);
        $checked = get_option('fcmdpplgpn_disable') != 1; //$fcmdpplgpn_stored_meta['send-fcm-checkbox'][0];

?>

        <p>
            <span class="fcm-row-title"><?php echo esc_html(__('Check if send a push notification: ', FCMDPPLGPN_TRANSLATION)); ?></span>
        <div class="fcm-row-content">
            <label for="send-fcm-checkbox">
                <?php if (in_array($pagenow, array('post-new.php'))) { ?>
                    <input type="checkbox" name="send-fcm-checkbox" id="send-fcm-checkbox" value="1" <?php if (isset($fcmdpplgpn_stored_meta['send-fcm-checkbox'])) checked($checked, '1'); ?> />
                <?php } else { ?>
                    <input type="checkbox" name="send-fcm-checkbox" id="send-fcm-checkbox" value="1" />
                <?php } ?>
                <?php echo esc_attr(__('Send Push Notification', FCMDPPLGPN_TRANSLATION)); ?>
            </label>

        </div>
        </p>

<?php
    }

    /**
     * Saves the custom meta input
     */
    function fcmdpplgpn_meta_save($post_id)
    {

        // Checks save status - overcome autosave, etc.
        $is_autosave = wp_is_post_autosave($post_id);
        $is_revision = wp_is_post_revision($post_id);
        $is_valid_nonce = (isset($_POST['fcmdpplgpn_nonce']) && wp_verify_nonce($_POST['fcmdpplgpn_nonce'], basename(__FILE__))) ? 'true' : 'false';

        // Exits script depending on save status
        if ($is_autosave || $is_revision || !$is_valid_nonce) {
            return;
        }

        remove_action('wp_insert_post', array($this, 'fcmdpplgpn_on_post_save'), 10);

        // Checks for input and saves - save checked as yes and unchecked at no
        if (isset($_POST['send-fcm-checkbox'])) {
            update_post_meta($post_id, 'send-fcm-checkbox', '1');
        } else {
            update_post_meta($post_id, 'send-fcm-checkbox', '0');
        }

        add_action('wp_insert_post', array($this, 'fcmdpplgpn_on_post_save'), 10, 3);
    }

    function fcmdpplgpn_on_post_save($post_id, $post, $update)
    {


        if (get_option('fcmdpplgpn_api') && get_option('fcmdpplgpn_topic')) {
            //new post/page
            if (isset($post->post_status)) {

                if ($update && ($post->post_status == 'publish')) {

                    $send_fcmdpplgpn_checkbox = get_post_meta($post_id, 'send-fcm-checkbox', true);

                    //$this->write_log('send_fcmdpplgpn_checkbox: ' . $send_fcmdpplgpn_checkbox);

                    if ($send_fcmdpplgpn_checkbox) {

                        //update_post_meta( $post_id, 'send-fcm-checkbox', '0' );

                        if ($this->get_options_posttype($post->post_type)) {
                            //($post, $sendOnlyData, $showLocalNotification, $command)
                            $result = $this->fcmdpplgpn_notification($post, false, false, '');
                            //$this->write_log('post updated: ' . $post_title);   
                        } elseif ($this->get_options_posttype($post->post_type)) {
                            $result = $this->fcmdpplgpn_notification($post, false, false, '');
                            //$this->write_log('page updated: ' . $post_title);   
                        }
                    }
                }
            }
        }
    }


    public function write_log($log)
    {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

    public function get_options_posttype($post_type)
    {
        return get_option('fcmdpplgpn_posttype_' . $post_type) == 1;
    }

    public function fcmdpplgpn_setup_admin_menu()
    {
        add_submenu_page('options-general.php', __('Firebase Push Notification', FCMDPPLGPN_TRANSLATION), FCMDPPLGPN_PLUGIN_NM, 'manage_options', 'fcmdpplgpn_push_notification', array($this, 'fcmdpplgpn_admin_page'));

        add_submenu_page(
            null,
            __('Test Push Notification', FCMDPPLGPN_TRANSLATION),
            'Test Notification',
            'administrator',
            'test_push_notification',
            array($this, 'fcmdpplgpn_send_test_notification')
        );
    }

    public function fcmdpplgpn_admin_page()
    {
        include(plugin_dir_path(__FILE__) . 'fcm-dp-admin-panel.php');
    }

    public function fcmdpplgpn_activate()
    {
    }

    public function fcmdpplgpn_deactivate()
    {
    }


    function fcmdpplgpn_settings()
    {
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_api');
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_topic');
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_disable');
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_page_disable');
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_channel');
        register_setting('fcmdpplgpn_group', 'fcmdpplgpn_default_image');

        /* set checkboxs post types */
        $args  = array(
            'public' => true,
        );

        $post_types = get_post_types($args, 'objects');

        if ($post_types) { // If there are any custom public post types.

            foreach ($post_types  as $post_type) {
                $this->write_log('add action 4: ' . $post_type->name);
                if ($post_type->name != 'attachment') {
                    register_setting('fcmdpplgpn_group', 'fcmdpplgpn_posttype_' . $post_type->name);
                }
            }
        }
    }

    function fcmdpplgpn_send_test_notification()
    {

        $test = new FCMDPPLGPNTestSendPushNotification;

        $test->post_type = "test";
        $test->ID = 0;
        $test->post_title = "Test Push Notification";
        $test->post_content = "Test from Firebase Push Notification Plugin";
        $test->post_excerpt = "Test from Firebase Push Notification Plugin";
        $test->post_url = "https://webcoastagency.com";


        $result = $this->fcmdpplgpn_notification($test, false, false, '');

        echo '<div class="row">';
        echo '<div><h2>API Return</h2>';

        echo '<pre>';
        printf($result);
        echo '</pre>';

        echo '<p><a href="' . admin_url('admin.php') . '?page=test_push_notification">Send again</a></p>';
        echo '<p><a href="' . admin_url('admin.php') . '?page=fcmdpplgpn_push_notification">FCM Options</a></p>';

        echo '</div>';
    }

    //function fcmdpplgpn_notification($title, $content, $resume, $post_id, $image){
    function fcmdpplgpn_notification($post, $sendOnlyData, $showLocalNotification, $command)
    {

        $from = get_bloginfo('name');
        //$content = 'There are new post notification from '.$from;

        /*
        $post_type = esc_attr($post->post_type);
        $post_id = esc_attr($post->ID);
        $post_title = esc_attr($post->post_title);
        $content = esc_html($post->post_content);
        $resume = esc_attr($post->post_excerpt);
        $post_url = esc_url($post->post_url);
        */

        // v1.1
        $post_type = esc_attr($post->post_type);
        $post_id = esc_attr($post->ID);
        $post_title = $post->post_title;
        $content = esc_html(wp_strip_all_tags(preg_replace("/\r|\n/", " ", $post->post_content)));
        $resume = esc_attr($post->post_excerpt);
        $post_url = esc_url($post->post_url);

        // http://codex.wordpress.org/Function_Reference/get_post_thumbnail_id
        // $thumb_id = get_post_thumbnail_id( $post_id );

        // http://codex.wordpress.org/Function_Reference/wp_get_attachment_image_src
        // https://stackoverflow.com/a/56340953 -> set true to get default image
        // $thumb_url = wp_get_attachment_image_src( $thumb_id, 'full' );
        $thumb_url = get_the_post_thumbnail_url($post_id);

        $image = $thumb_url ? esc_url($thumb_url) : '';

        if (mb_strlen($image) == 0) {
            $image = get_option('fcmdpplgpn_default_image');
        }

        /*
        $this->write_log('post Id: ' . $post_id);
        $this->write_log('post Title: ' . $post_title);
        $this->write_log('post Content: ' . $content);
        $this->write_log('resume: ' . $resume);
        $this->write_log('image: ' . $image);
        */

        $topic =  esc_attr(get_option('fcmdpplgpn_topic'));
        $apiKey = esc_attr(get_option('fcmdpplgpn_api'));
        $url = 'https://fcm.googleapis.com/fcm/send';

        /*
        $headers = array(
            'Authorization: key=' . $apiKey,
            'Content-Type: application/json'
        );
        */

        $message = mb_strlen($resume) == 0 ? mb_substr(wp_strip_all_tags(html_entity_decode($content)), 0, 100) . '...' : html_entity_decode($resume);

        $notification_data = array(
            'click_action'          => 'FCM_NOTIFICATION_CLICK',
            'message'               => $message,
            'post_type'             => $post_type,
            'post_id'               => $post_id,
            'title'                 => $post_title,
            'image'                 => $image,
            'url'                   => $post_url,
            'show_in_notification'  => $showLocalNotification,
            'command'               => $command,
            'dialog_title'          => $post_title,
            'dialog_text'           => $message,
            'dialog_image'          => $image,
        );

        $notification_ios = array(
            'badge'                 => '1'
        );

        $notification_android_data = array(
            'body'                  => $message,
            'title'                 => $post_title,
            'image'                 => $image
        );
        $notification_android = array(
            'notification'           => $notification_android_data
        );

        $apns_image = array(
            'image'                 => $image
        );
        $apns_payload_aps = array(
            'mutable-content'       => 1
        );
        $apns_payload = array(
            'aps'                 => $apns_payload_aps
        );
        $apns = array(
            'fcm_options'           => $apns_image,
            'payload'               => $apns_payload
        );

        $notification = array(
            'title'                 => $post_title,
            'body'                  => mb_strlen($resume) == 0 ? mb_substr(wp_strip_all_tags(html_entity_decode($content)), 0, 100) . '...' : html_entity_decode($resume),
            'content_available'     => true,
            'android_channel_id'    => get_option('fcmdpplgpn_channel'),
            'click_action'          => 'FCM_NOTIFICATION_CLICK',
            'sound'                 => 'default',
            'image'                 => $image,
            'ios'                   => $notification_ios,
            'android'               => $notification_android
        );

        $post = array(
            'to'                    => '/topics/' . $topic,
            'collapse_key'          => 'type_a',
            'notification'          => $notification,
            'priority'              => 'high',
            'data'                  => $notification_data,
            'timeToLive'            => 10,
            'apns'                  => $apns
        );

        // @xxx To debug
        // $to      = 'anthony@webcoastagency.com';
        // $subject = '[FCM] Push Notification';
        // $message = 'A new notification. Check out this body:\n\n' . print_r($post, 1);
        // $headers = 'From: no-reply@webcoastagency.com' . "\r\n" .
        // 'Reply-To: no-reply@webcoastagency.com' . "\r\n" .
        // 'X-Mailer: PHP/' . phpversion();

        // mail($to, $subject, $message, $headers);

        $payload = json_encode($post);

        $args = array(
            'timeout'           => 45,
            'redirection'       => 5,
            'httpversion'       => '1.1',
            'method'            => 'POST',
            'body'              => $payload,
            'sslverify'         => false,
            'headers'           => array(
                'Content-Type'      => 'application/json',
                'Authorization'     => 'key=' . $apiKey,
            ),
            'cookies'           => array()
        );

        $response = wp_remote_post($url, $args);

        return json_encode($response);
    }


    function fcmdpplgpn_settings_link($links)
    {
        // Build and escape the URL.
        $url = esc_url(add_query_arg(
            'page',
            'fcmdpplgpn_push_notification',
            get_admin_url() . 'admin.php'
        ));
        // Create the link.
        $settings_link = "<a href='$url'>" . __('Settings') . '</a>';
        // Adds the link to the end of the array.
        array_push(
            $links,
            $settings_link
        );
        return $links;
    } //end nc_settings_link()


}

/* to test a send notification */
class FCMDPPLGPNTestSendPushNotification
{
    public  $ID;
    public  $post_type;
    public  $post_content;
    public  $post_excerpt;
    public  $post_url;
}

$FCMDPPLGPN_Push_Notification_OBJ = new FCMDPPLGPN_Push_Notification();
