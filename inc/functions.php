<?php
define('DTC_VERSION', 1);
define('DTC_SLUG', 'debelop-translation-check');
define('DTC_API_URL', 'http://bld.debelop.com/detect');
define('DTC_SHOP_URL', 'http://debelop.com/wpml-translation-check');
define('DTC_TEST_LIMIT', 50);
define('DTC_MIN_PROB', 0.95); //minimum probability to show detection as "reliable"

function dtc_add_admin_menu()
{
    add_menu_page(
        'Translation check',
        'Translation check',
        'manage_options',
        DTC_SLUG,
        'dtc_display_settings',
        plugins_url('img/plugin-icon.png', dirname(__FILE__))
    );
    add_submenu_page(
        DTC_SLUG, //'options-general.php',
        'Translation check - Settings',
        'Settings',
        'manage_options',
        DTC_SLUG,
        'dtc_display_settings'
    );
    add_submenu_page(
        DTC_SLUG,
        'Translation check',
        'Check translations',
        'manage_options',
        DTC_SLUG.'-content',
        'dtc_display_main'
    );

}

function dtc_display_main()
{
    include('content_tabs.php');
}


function dtc_display_settings()
{
   ?>
   <div class="wrap">
      <?php screen_icon(); ?>
      <h2>Translation check: Settings</h2>
      <form method="post" action="options.php">

         <?php settings_fields('dtc_settings_fields_id'); ?>
         <?php do_settings_sections(__FILE__); ?>
         <?php submit_button();?>

      </form>
   </div>
   <?php
}

function dtc_init_register_settings()
{
    register_setting('dtc_settings_fields_id', 'dtc_options');

    add_settings_section(
        'dtc_options_section', // Unique ID
        '', // Name for this section
        '', // Function to call
        __FILE__ // Page
    );

    add_settings_field('dtc_api_key_field', // Unique ID
        'Api Key', // Name for this field
        'dtc_api_key_field', //Function to call
        __FILE__, // Page
        'dtc_options_section' // Section to belong to
    );


    add_settings_field('dtc_post_types_field', // Unique ID
        'Post types', // Name for this field
        'dtc_post_types_field', //Function to call
        __FILE__, // Page
        'dtc_options_section' // Section to belong to
    );

    add_settings_field('dtc_detect_default_lang_field', // Unique ID
        'Detect default language', // Name for this field
        'dtc_detect_default_lang_field', //Function to call
        __FILE__, // Page
        'dtc_options_section' // Section to belong to
    );
}


function dtc_enqueue($hook)
{
    if (false !== strpos($hook, DTC_SLUG)) {
        wp_register_style('dtc_admin_styles', plugins_url('css/admin.css', dirname(__FILE__)));
        wp_enqueue_style('dtc_admin_styles');
    }
}




function dtc_api_key_field()
{
   $options = get_option('dtc_options');
   echo '<input id="dtc_api_key_input" name="dtc_options[api_key]" type="text" value="'.$options['api_key'].'" />';
   echo '<p class="description">Place your Api Key here. If you don\'t have one, leave it as <b>TEST</b>.</p>';
}

function dtc_detect_default_lang_field()
{
   $options = get_option('dtc_options');
   echo '<label><input id="dtc_detect_default_lang_0" name="dtc_options[detect_default_lang]" type="radio" value="0" '.(!$options['detect_default_lang']?'checked="checked"':'').'" /> No</label><br/>';
   echo '<label><input id="dtc_detect_default_lang_1" name="dtc_options[detect_default_lang]" type="radio" value="1" '.($options['detect_default_lang']?'checked="checked"':'').'" /> Yes</label>';
   echo '<p class="description">Whether to apply language detection to the entries written in the default language. Usually not necessary.</p>';
}

function dtc_post_types_field()
{
    $options = get_option('dtc_options');
    $selTypes = $options['types'];

    $allTypes = get_post_types('', 'objects');

    foreach ($allTypes as $type) {
        $checked = in_array($type->name, $selTypes) ? ' checked="checked"' : '';
        echo '<label><input id="dtc_post_types_'.$type->name.'" name="dtc_options[types][]" type="checkbox" value="'.$type->name.'"'.$checked.' /> '.$type->label.'</label><br/>'."\n";
    }
    echo '<p class="description">Select the post types to be checked. You should only select the post types for which you have multi-language content.</p>';
}


function dtc_send_texts()
{
    global $wpdb; // this is how you get access to the database

    $response = array('success' => false);
    $options = get_option('dtc_options');
    do {
        if (empty($_POST['texts'])) {
            $response['msg'] = 'No texts received';
            break;
        }
        $texts = $_POST['texts'];
        include('DebLanguageDetector.php');
        $detector = new DebLanguageDetector($options['api_key']);
        foreach ($texts as $k => $v) {
            $detector->addText($v, $k);
        }
        $result = $detector->detect();
        if (false === $result) {
            $response['msg'] = $detector->getError();
            break;
        }
        $response['success'] = true;
        $response['data'] = $result;
    } while (0);


    echo json_encode($response);

    die(); // this is required to return a proper result
}

function dtc_admin_notice()
{
    global $current_screen;

    $options = get_option('dtc_options');

    if ($options['api_key'] == 'TEST' && $current_screen->parent_base == DTC_SLUG) {
        echo '<div class="update-nag"><p>'
        . sprintf('You are using this plugin in TEST mode. Under this mode, only the first %d entries are language-detected.
    The remaining ones will be skipped. You can purchase an <a href="%s">API KEY</a> in order to remove this limitation.', DTC_TEST_LIMIT, DTC_SHOP_URL)
        . '</p></div>';
    }
}

class DTCTexts {

    private $texts;
    private $limit;

    function __construct($options)
    {
        $this->texts = array();
        $this->limit = $options['api_key'] == 'TEST' ? DTC_TEST_LIMIT : null;
    }

    public function addContent($id, $title, $text) {
        if ('' == $text) return;
        if ($this->limit && count($this->texts) >= $this->limit) return;
        if (strlen($text) < 180) $text = trim($title, '.') . '. ' . $text;
        $this->texts[(string)$id] = $text;
    }

    public function getTexts() {
        return $this->texts;
    }
}