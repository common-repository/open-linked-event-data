<?php
class WordPressOleExportAdmin {

    public function __construct() {
        add_action('admin_menu', array($this, 'addMenu'));

        if (WordPressOleExportUtil::getOption('post_checkbox') === 1) {
            add_action('add_meta_boxes', array($this, 'addCheckboxMetabox'));
            add_action('save_post', array($this, 'saveCheckboxMetabox'));
        }

    }

    public function addMenu() {
        add_options_page('OLE Export', 'OLE Export', 'manage_options', 'oleexport', array($this, 'pluginOptions'));
    }

    public function pluginOptions() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        if (isset($_POST['oleexport_options_edit_field']) && wp_verify_nonce($_POST['oleexport_options_edit_field'], 'oleexport_options_edit') && isset($_POST['optionsEdit'])) {
            $isActive = 0;
            if (isset($_POST['active']) && $_POST['active'] === 'on') {
                $url = home_url('feed/ole');
                wp_remote_get('https://www.hinto.ch/oleping?url='.urlencode($url));
                $isActive = 1;
            }
            WordPressOleExportUtil::updateOption('active', $isActive);

            if (isset($_POST['driver'])) {
                WordPressOleExportUtil::updateOption('driver', str_replace('\\\\','\\',sanitize_text_field($_POST['driver'])));
            }

            $isPostCheckboxEnabled = 0;
            if (isset($_POST['post_checkbox']) && $_POST['post_checkbox'] === 'on') {
                $isPostCheckboxEnabled = 1;
            }
            WordPressOleExportUtil::updateOption('post_checkbox', $isPostCheckboxEnabled);

            if(isset($_POST['source_version'])) {
                WordPressOleExportUtil::updateOption('source_version', sanitize_text_field($_POST['source_version']));
            }
        }

        require(dirname(__FILE__) . '/templates/options.php');
    }

    /**
     * @return array
     */
    public function getActiveDrivers() {
        $apl = get_option('active_plugins');
        $drivers = WordPressOleExportUtil::getOleExportDrivers();

        foreach($drivers as $driver) {
            $driver->disabled = false;

            if (!in_array($driver->activePluginName, $apl)) {
                $driver->disabled = true;
            }
        }

        return $drivers;
    }

    public function addCheckboxMetabox() {
        $postTypes = [];
        $driver = WordPressOleExportUtil::getOption('driver');
        if (!empty($driver)) {
            foreach(WordPressOleExportUtil::getOleExportDrivers() as $oleDriver) {
                if ($driver === $oleDriver->className) {
                    $postTypes = $oleDriver->eventPostTypes;
                    break;
                }
            }
        }

        foreach($postTypes as $postType) {
            add_meta_box('oleexport_post_enabled', __( 'OLE Export', 'oleexport' ), array($this, 'handleCheckboxMetabox'), $postType, 'normal', 'high');
        }
    }

    public function handleCheckboxMetabox($post) {
        wp_nonce_field('oleexport_post_checkbox', 'oleexport_post_checkbox_nonce');
        $isEnabled = get_post_meta($post->ID, '_oleexport_post_enabled', true);
        ?>
        <label><input type="checkbox" name="oleexport_post_enabled" id="oleexport_post_enabled" <?php echo $isEnabled === '1' ? 'checked="checked"': ''; ?>/><?php _e( 'Publish on OLE', 'oleexport' ); ?></label>
        <?php
    }

    public function saveCheckboxMetabox($postId) {
        if (!isset($_POST['oleexport_post_checkbox_nonce'])){
            return;
        }

        if (!wp_verify_nonce($_POST['oleexport_post_checkbox_nonce'], 'oleexport_post_checkbox' ) ) {
            return;
        }

        if (defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) {
            return;
        }

        $isEnabled = 0;
        if (isset($_POST['oleexport_post_enabled']) && $_POST['oleexport_post_enabled'] === 'on') {
            $isEnabled = 1;
        }

        update_post_meta($postId, '_oleexport_post_enabled', $isEnabled);
    }
}
