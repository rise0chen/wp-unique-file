<?php
/**
 * Plugin Name: Unique File
 * Plugin URI:  https://wordpress.org/plugins/unique-file/
 * Description: Only store one when the uploaded file is same
 * Version:     1.0.0
 * Author:      rise0chen
 * Author URI:  https://blog.crise.cn/
 * License:     GPL2
 */

define('UNIQUE_FILE_VERSION', '1.0.0');
define('UNIQUE_FILE_PAGE', plugin_basename(dirname(__FILE__)) . '/unique-file.php');

// Default plugin values
if(get_option('unique_file_version') != UNIQUE_FILE_VERSION) {
  update_option('unique_file_version', UNIQUE_FILE_VERSION, '','yes');
  add_option('unique_file_rename_md5', 'true', '', 'yes');
  add_option('unique_file_must_one', 'true', '', 'yes');
  add_option('unique_file_without_siteid', 'true', '', 'yes');
  add_option('unique_file_prevent_deletion', 'true', '', 'yes');
}

// rename file by md5
if ( ! function_exists( 'unique_file_rename' ) ) :
  function unique_file_rename( $attachment ) {
    $current_ext = pathinfo( basename( $attachment['name'] ), PATHINFO_EXTENSION );
    $processed_ext = empty( $current_ext ) ? '' : '.' . $current_ext;
    $attachment['name'] = hash_file('md5', $attachment['tmp_name']) . $processed_ext;

    return $attachment;
  }
endif;
if (get_option('unique_file_rename_md5', true) == 'true') {
    add_filter( 'wp_handle_upload_prefilter', 'unique_file_rename', 20, 2 );
}

// remove unique number
if ( ! function_exists( 'unique_file_unique' ) ) :
  function unique_file_unique( $filename, $ext, $dir, $unique_filename_callback ) {
    $pattern = '/^(.*?)(-\d+)?(\..+?)?$/';
    preg_match($pattern, $filename, $matches);
    $filename = $matches[1] . $ext;
    return $filename;
  }
endif;
if (get_option('unique_file_must_one', true) == 'true') {
    add_filter( 'wp_unique_filename', 'unique_file_unique', 20, 6 );
}

// remove siteid
if ( ! function_exists( 'unique_file_remove_siteid' ) ) :
  function unique_file_remove_siteid( $uploads ) {
    $pattern = '/^(.*?)(\/(sites\/)?\d+)?$/';
    preg_match($pattern, $uploads['basedir'], $matches);
    if ( isset($matches[2]) ) {
      $siteid = $matches[2];
      $uploads['path'] = str_replace($siteid, "", $uploads['path']);
      $uploads['url'] = str_replace($siteid, "", $uploads['url']);
      $uploads['basedir'] = str_replace($siteid, "", $uploads['basedir']);
      $uploads['baseurl'] = str_replace($siteid, "", $uploads['baseurl']);
    }
    return $uploads;
  }
endif;
if (get_option('unique_file_without_siteid', true) == 'true') {
  add_filter( 'upload_dir', 'unique_file_remove_siteid', 0, 1 );
}

// prevent deletion
if ( ! function_exists( 'unique_file_prevent_deletion' ) ) :
  function unique_file_prevent_deletion( $delete, $post, $force_delete ) {
    $post_id = $post->ID;
    $meta = wp_get_attachment_metadata($post_id);
    if (isset($meta['file'])) {
        return false;
    }
    $url = wp_get_attachment_url($post_id);
    if ($url) {
        return false;
    }
    return $delete;
  }
endif;
if (get_option('unique_file_prevent_deletion', true) == 'true') {
  add_filter( 'pre_delete_attachment', 'unique_file_prevent_deletion', 0, 3 );
}

// add setting button
function unique_file_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/unique-file.php')) {
        $links[] = '<a href="options-general.php?page=' . UNIQUE_FILE_PAGE . '">Setting</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'unique_file_plugin_action_links', 10, 2);

// add setting page
function unique_file_add_setting_page()
{
    add_options_page('Unique File', 'Unique File', 'manage_options', __FILE__, 'unique_file_setting_page');
}
add_action('admin_menu', 'unique_file_add_setting_page');

// setting page
function unique_file_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = array();
    if (!empty($_POST) and $_POST['type'] == 'unique_file_set') {
        $options['rename_md5'] = isset($_POST['rename_md5']) ? 'true' : 'false';
        $options['must_one'] = isset($_POST['must_one']) ? 'true' : 'false';
        $options['without_siteid'] = isset($_POST['without_siteid']) ? 'true' : 'false';
        $options['prevent_deletion'] = isset($_POST['prevent_deletion']) ? 'true' : 'false';
    }

    if ($options !== array()) {
        update_option('unique_file_rename_md5', $options['rename_md5']);
        update_option('unique_file_must_one', $options['must_one']);
        update_option('unique_file_without_siteid', $options['without_siteid']);
        $disable_yearmonth = isset($_POST['disable_yearmonth']) ? '0' : '1';
        update_option('uploads_use_yearmonth_folders', $disable_yearmonth);
        update_option('unique_file_prevent_deletion', $options['prevent_deletion']);

        echo '<div class="updated"><p><strong>success to save!</strong></p></div>';
    }

    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>Unique File <span style="font-size: 13px;">Version: <?php echo UNIQUE_FILE_VERSION; ?></span></h1>
        <p>Recommended to enable all options</a>
        <hr/>
        <form name="form1" method="post" action="<?php echo wp_nonce_url(
            './options-general.php?page=' . UNIQUE_FILE_PAGE
        ); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>Rename by MD5</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="rename_md5" <?php checked( 'true', get_option( 'unique_file_rename_md5' ) ); ?> />
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Store Only One</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="must_one" <?php checked( 'true', get_option( 'unique_file_must_one' ) ); ?> />
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Without siteid</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="without_siteid" <?php checked( 'true', get_option( 'unique_file_without_siteid' ) ); ?> />
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Disable yearmonth folder</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="disable_yearmonth" <?php checked( '0', get_option( 'uploads_use_yearmonth_folders' ) ); ?> />
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Prevent Deletion</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="prevent_deletion" <?php checked( 'true', get_option( 'unique_file_prevent_deletion' ) ); ?> />
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>Save/Update</legend>
                    </th>
                    <td><input type="submit" name="submit" class="button button-primary" value="Save"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="unique_file_set">
        </form>
    </div>
    <?php
}
?>
