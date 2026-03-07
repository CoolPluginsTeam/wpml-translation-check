<?php

if(!defined('ABSPATH')){
    exit;
}

/**
 * Dashboard
 * 
 * example:
 * 
 * Dashbord initialize
 * if(!class_exists('AUTOML_Ai_Cpt_Dashboard')){
 * $dashboard=AUTOML_Ai_Cpt_Dashboard::instance();
 * }
 * 
 * Store options
 * if(class_exists('AUTOML_Ai_Cpt_Dashboard')){
 *  AUTOML_Ai_Cpt_Dashboard::store_options(
 *      'prefix', // Required plugin prefix
 *      'unique_key',// Optional unique key is used to update the data based on post/page id or plugin/themes name
 *      'update', // Optional preview string count or character count update or replace
 *      array(
 *           'post/page or theme/plugin name' => 'name or id',
 *          'post_title (optional)' => 'Post Title',
 *          'service_provider' => 'google', // don't change this key
 *          'source_language' => 'en', // don't change this key
 *          'target_language' => 'fr', // don't change this key
 *          'time_taken' => '10', // don't change this key
 *          'string_count'=>10, 
 *          'character_count'=>100, 
 *          'date_time' => date('Y-m-d H:i:s'),
 *      ) // Required data array
 *  );
 * }
 * 
 * Add Tabs
 * add_filter('AUTOML_Ai_Cpt_Dashboard_tabs', function($tabs){
 *  $tabs[]=array(
 *      'prefix'=>'tab_name', // Required
 *      'tab_name'=>'Tab Name', // Required
 *      'columns'=>array(
 *          'post_id or plugin_name'=>'Post Id or Plugin Name',
 *          'post_title (optional)'=>'Post Title',
 *          'string_count'=>'String Count',
 *           'character_count'=>'Character Count',
 *           'service_provider'=>'Service Provider',
 *           'time_taken'=>'Time Taken',
 *           'date_time'=>'Date Time',
 *      ) // columns Required
 *  );
 *  return $tabs;
 * });
 * 
 * Display review notice
 * if(class_exists('AUTOML_Ai_Cpt_Dashboard')){
 *  AUTOML_Ai_Cpt_Dashboard::review_notice(
 *      'prefix', // Required
 *      'plugin_name', // Required
 *      'url', // Required
 *  );
 * }
 * 
 * Get translation data
 * if(class_exists('AUTOML_Ai_Cpt_Dashboard')){
 *  AUTOML_Ai_Cpt_Dashboard::get_translation_data(
 *      'prefix', // Required
 *      array(
 *          'editor_type' => 'gutenberg', // optional return data based on editor type
 *          'post_id' => '123', // optional return data based on post id
 *      ) // Optional
 *  );
 * }
 */

 if(!class_exists('AUTOML_Ai_Cpt_Dashboard'))
    {
        class AUTOML_Ai_Cpt_Dashboard{

        /**
         * Init
         * @var object
         */
        private static $init;

        /**
         * Tabs data
         * @var array
         */
        private $tabs_data=array();

        /**
         * Instance
         * @return object
         */
        public static function instance(){
            if(!isset(self::$init)){
                self::$init = new self();
            }
            return self::$init;
        }

        public function __construct(){
            add_action('wp_ajax_automl_ai_hide_review_notice', array($this, 'automl_ai_hide_review_notice'));
        }

        /**
         * Sort column data
         * @param array $columns
         * @param array $value
         * @return array
         */
        public function sort_column_data($columns, $value){
            $result = array();
            foreach($columns as $key => $label) {
                $result[$key] = isset($value[$key]) ? sanitize_text_field($value[$key]) : '';
            }
            return $result;
        }

        /**
         * Store options
         * @param string $plugin_name
         * @param string $prefix
         * @param array $data
         * @return void
         */
        public static function store_options($prefix='', $unique_key='', $old_data='update', array $data = array()){
            if(!empty($prefix) && isset($data['string_count']) && isset($data['character_count'])){
                $prefix = sanitize_key($prefix);
                $all_data = get_option('automl_ai_dashboard_data', array());
                
                if(isset($all_data[$prefix])){
                    $data_update = false;
                    $cache_key  = sanitize_text_field($prefix) .'_translation_data_post_ids';
                    $cache_group = sanitize_text_field($prefix) .'_translation_info';
            
                    foreach($all_data[$prefix] as $key => $translate_data){
                        if(!empty($unique_key) && isset($translate_data[$unique_key]) && 
                        sanitize_text_field($translate_data[$unique_key]) === sanitize_text_field($data[$unique_key]) && 
                        sanitize_text_field($translate_data['service_provider']) === sanitize_text_field($data['service_provider']) &&
                        sanitize_text_field($translate_data['target_language']) === sanitize_text_field($data['target_language']) &&
                        sanitize_text_field($translate_data['source_language']) === sanitize_text_field($data['source_language'])
                        ){
                            
                            if($old_data=='update'){
                                $data['string_count'] = absint($data['string_count']) + absint($translate_data['string_count']);
                                $data['character_count'] = absint($data['character_count']) + absint($translate_data['character_count']);
                                $data['time_taken'] = absint($data['time_taken']) + absint($translate_data['time_taken']);
                            }
                            
                            foreach($data as $id => $value){
                                $all_data[$prefix][$key][sanitize_key($id)] = sanitize_text_field($value);
                            }
                            $data_update = true;
                        }
                    }

                    if(!$data_update){

                        wp_cache_delete($cache_key, $cache_group);
                        delete_transient($cache_key);
                        
                        $all_data[$prefix][] = array_map('sanitize_text_field', $data);
                        
                        $post_ids = array_column( $all_data[$prefix], 'post_id' );

			            $unique_post_ids = array_keys( array_flip( $post_ids ) );

                        wp_cache_set( $cache_key, array_values($unique_post_ids), $cache_group, DAY_IN_SECONDS );
                        set_transient($cache_key, array_values($unique_post_ids), DAY_IN_SECONDS);
                    }
                }else{
                    $all_data[$prefix][] = array_map('sanitize_text_field', $data);
                }

                update_option('automl_ai_dashboard_data', $all_data);
            }
        }

        /**
         * Get translation data
         * @param string $prefix
         * @return array
         */
        public static function get_translation_data($prefix, $key_exists=array()){
            $prefix = sanitize_key($prefix);
            $all_data = get_option('automl_ai_dashboard_data', array());
            $data = array();

            if(isset($all_data[$prefix])){
                $total_string_count = 0;
                $total_character_count = 0;

                foreach($all_data[$prefix] as $key => $value){

                    $continue=false;
                    foreach($key_exists as $key_exists_key => $key_exists_value){
                        if(!isset($value[$key_exists_key]) || (isset($value[$key_exists_key]) && $value[$key_exists_key] !== $key_exists_value)){
                            $continue=true;
                            break;
                        }
                    }

                    if($continue){
                        continue;
                    }

                    $total_string_count += isset($value['string_count']) ? absint($value['string_count']) : 0;
                    $total_character_count += isset($value['character_count']) ? absint($value['character_count']) : 0;
                }

                $data = array(
                    'prefix' => $prefix,
                    'data' => array_map(function($item) {
                        return array_map('sanitize_text_field', $item);
                    }, $all_data[$prefix]),
                    'total_string_count' => $total_string_count,
                    'total_character_count' => $total_character_count,
                );
            }else{
                $data = array(
                    'prefix' => $prefix,
                    'total_string_count' => 0,
                    'total_character_count' => 0,
                );
            }

            return $data;
        }

        public static function ctp_enqueue_assets(){
            if(function_exists('wp_style_is') && !wp_style_is('automl_ai_review-style', 'enqueued')){
                $plugin_url = plugin_dir_url(__FILE__);
                wp_enqueue_style('automl_ai_review-style', esc_url($plugin_url.'assets/css/cpt-dashboard.css'), array(), '1.0.0', 'all');
                wp_enqueue_script('automl_ai_review-script', esc_url($plugin_url.'assets/js/cpt-dashboard.js'), array('jquery'), '1.0.0', true);
            }
        }

        public static function format_number_count($number){
            if ($number >= 1000000) {
                return round($number / 1000000, 1) . 'M';
            } elseif ($number >= 1000) {
                return round($number / 1000, 1) . 'K';
            }
            return $number;
        }

        public static function review_notice($prefix, $plugin_name, $url){
            if(self::automl_ai_hide_review_notice_status($prefix)){
                return;
            }
            
            $translation_data = self::get_translation_data($prefix);
            
            $total_character_count = is_array($translation_data) && isset($translation_data['total_character_count']) ? $translation_data['total_character_count'] : 0;
            
            if($total_character_count < 50000){ 
                return;
            }

            $total_character_count = self::format_number_count($total_character_count);

            add_action('admin_enqueue_scripts', array(self::class, 'ctp_enqueue_assets'));

            

            $message = sprintf(
                // translators: %1$s: plugin name, %2$s: total character count, %3$s: Cool Plugins URL
                __( 'Thanks for using <b>%1$s</b>! You have translated <b>%2$s</b> characters so far using our plugin!<br>Please give us a quick rating, it works as a boost for us to keep working on more <a style="text-decoration: none;" href="%3$s" target="_blank" rel="noopener noreferrer"><b>Cool Plugins</b></a>!', 'automl-ai-translation-for-wpml' ),
                esc_html( $plugin_name ),
                esc_html( $total_character_count ),
                esc_url( 'https://coolplugins.net/' )
            );     
            

            $prefix = sanitize_key($prefix);
            $url = esc_url($url);
            $plugin_name = sanitize_text_field($plugin_name);

            $allowed = [
                'div' => [ 'class' => true, 'data-prefix' => true, 'data-nonce' => true ],
                'p' => [],
                'a' => [ 'href' => true, 'target' => true, 'class' => true, 'style' => true, 'rel' => true ],
                'button' => [ 'class' => true ],
                'b' => [],
                'br' => [],
                'strong' => [],
            ];

            $message = wp_kses($message, $allowed);

            add_action('admin_notices', function() use ($message, $prefix, $url, $allowed){
                $html= '<div class="notice notice-info is-dismissible cpt-review-notice">';
                
                $html .= '<div class="cpt-review-notice-content"><p>'.$message.'</p><div class="automl-wpml-review-notice-dismiss" data-prefix="'.esc_attr($prefix).'" data-nonce="'.esc_attr(wp_create_nonce('automl_wpml_hide_review_notice')).'"><a href="'.esc_url($url).'" target="_blank" class="button button-primary">Rate Now! ★★★★★</a><button class="button cpt-already-reviewed">'.esc_html__('Already Reviewed', 'automl-ai-translation-for-wpml').'</button><button class="button cpt-not-interested">'.esc_html__('Not Interested', 'automl-ai-translation-for-wpml').'</button></div></div></div>';
                
                echo wp_kses($html, $allowed);
            });

            add_action('automl-wpml_display_admin_notices', function() use ($message, $prefix, $url, $allowed){
                $html= '<div class="notice notice-info is-dismissible cpt-review-notice">';
                $html .= '<div class="cpt-review-notice-content"><p>'.$message.'</p><div class="automl-wpml-review-notice-dismiss" data-prefix="'.$prefix.'" data-nonce="'.wp_create_nonce('automl_wpml_hide_review_notice').'"><a href="'. $url .'" target="_blank" class="button button-primary">Rate Now! ★★★★★</a><button class="button cpt-not-interested">'.__('Not Interested', 'automl-ai-translation-for-wpml').'</button><button class="button cpt-already-reviewed">'.__('Already Reviewed', 'automl-ai-translation-for-wpml').'</button></div></div></div>';
                
                echo wp_kses($html, $allowed);
            });
        }

        public static function automl_ai_hide_review_notice_status($prefix){
            $review_notice_dismissed = get_option('cpt_review_notice_dismissed', array());
            return isset($review_notice_dismissed[$prefix]) ? $review_notice_dismissed[$prefix] : false;
        }

        public function automl_ai_hide_review_notice(){
            if(!current_user_can('manage_options')){
                wp_send_json_error( __( 'Unauthorized', 'automl-ai-translation-for-wpml' ), 403 );
                wp_die( '0', 403 );
            }

            if(isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'automl_wpml_hide_review_notice')){
                $prefix = isset($_POST['prefix']) ? sanitize_key(wp_unslash($_POST['prefix'])) : '';
                $review_notice_dismissed = get_option('cpt_review_notice_dismissed', array());
                $review_notice_dismissed[$prefix] = true;
                update_option('cpt_review_notice_dismissed', $review_notice_dismissed);
                wp_send_json_success();
                }else{
                    wp_send_json_error( __( 'Invalid nonce', 'automl-ai-translation-for-wpml' ), 400 );
                wp_die( '0', 400 );
            }
        }
    }
}