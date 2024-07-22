<?php

class STM_CIE_Import_Manage
{
    CONST PAGE_SLUG = 'stm_cie_import';

    private array $materials;
    private $import_process;

    private int $bundles_created;
    private int $courses_created;
    private int $quizzes_created;
    private int $lessons_created;
    private int $questions_created;

    private int $bundles_skipped;
    private int $courses_skipped;
    private int $quizzes_skipped;
    private int $lessons_skipped;
    private int $questions_skipped;

    public function __construct(){
        add_action('admin_menu', array($this, 'add_menu_page'), 10000);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts_styles'));
        add_action('wp_ajax_stm_cie_import', array($this, 'import'));
    }

    public function add_menu_page(){
        add_submenu_page(
            'stm-lms-settings',
            esc_html__( 'STM Import Courses/Bundles', 'stmcie' ),
            esc_html__( 'STM Import', 'stmcie' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'main_page' ),
            100
        );
    }

    public function admin_scripts_styles(){
        if(isset($_GET['page']) && $_GET['page'] == self::PAGE_SLUG) {
            wp_enqueue_style( 'stm-cie-bootstrap', STMCIE_URL. 'assets/css/bootstrap.min.css', [], STMCIE_VERSION, 'all' );
            wp_enqueue_style( 'stm-cie-styles', STMCIE_URL. 'assets/css/style.css', [], STMCIE_VERSION, 'all' );
            wp_enqueue_script( 'stm-cie-vue', STMCIE_URL. 'assets/js/vue.min.js', ['stm-cie-vue-resource'], STMCIE_VERSION, true);
            wp_enqueue_script( 'stm-cie-vue-resource', STMCIE_URL. 'assets/js/vue-resource.min.js', [], STMCIE_VERSION, true );
            wp_enqueue_script( 'stm-cie-import', STMCIE_URL. 'assets/js/import.js', ['jquery', 'stm-cie-vue', 'stm-cie-vue-resource'], STMCIE_VERSION, true );
        }
    }

    public function main_page()
    {
        require_once STMCIE_PATH . '/templates/admin/import.php';
    }

    public function import()
    {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $response = array();
        if ( ! isset( $_FILES['import_file'] ) ) {
            return;
        }

        $import_file = $_FILES['import_file'];

        $skip_for_names = (isset($_REQUEST['skip_for_names'])) ? sanitize_text_field($_REQUEST['skip_for_names']) : 'false';
        $skip_for_names = ($skip_for_names == 'true');

        if ( ! file_exists( $import_file['tmp_name'] ) ) {
            return;
        }

        $decoded_file = wp_json_file_decode($import_file['tmp_name'], ['associative' => true]);

        $this->import_handler($decoded_file, ['skip_for_names' => $skip_for_names]);

        $response['skipped'] = array(
            'bundles' => $this->bundles_skipped,
            'courses' => $this->courses_skipped,
            'quizzes' => $this->quizzes_skipped,
            'lessons' => $this->lessons_skipped,
            'questions' => $this->questions_skipped,
        );

        $response['created'] = array(
            'bundles' => $this->bundles_created,
            'courses' => $this->courses_created,
            'quizzes' => $this->quizzes_created,
            'lessons' => $this->lessons_created,
            'questions' => $this->questions_created,
        );

        $response['show_info'] = true;

        wp_send_json($response);
    }

    public function item_handler($item = [])
    {
        global $wpdb;

        $default = array(
            'skipped' => array( 'bundles' => 0, 'courses' => 0, 'quizzes' => 0, 'lessons' => 0, 'questions' => 0 ),
            'created' => array( 'bundles' => 0, 'courses' => 0, 'quizzes' => 0, 'lessons' => 0, 'questions' => 0 )
        );
        $import_process_option = get_option('stm_cie_import_process_option', $default);

        $type = $item['type'];

        $is_skipped = false;
        $is_created = false;

        if($item['skip_for_names'] && !empty($item['post_title'])) {
            $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
            if($exist_id) {
                $is_skipped = true;
            }
        }


        $inserted_post_id = $this->import_post($item);

        if(!is_wp_error($inserted_post_id)) {
            $old_id = $item['ID'];
            $this->materials[$old_id] = $inserted_post_id;

            $this->import_postmeta($inserted_post_id, $item['meta']);

            $is_created = true;

//            if($type == 'questions') {
//            } elseif($type == 'quizzes') {
//            } elseif($type == 'lessons') {
//            } elseif($type == 'courses') {
//            } elseif($type == 'bundles') {
//            }
            if($type == 'courses') {
                $this->import_terms($inserted_post_id, $item['terms']);

                $this->import_attachment($inserted_post_id, $item['image_src']);

                $this->import_sections($inserted_post_id, $item['sections']);
            } elseif($type == 'bundles') {
                $stm_lms_bundle_ids = [];
                $new_ids = [];
                if(isset($item['meta']['stm_lms_bundle_ids']) && !empty($item['meta']['stm_lms_bundle_ids'])) {
                    $stm_lms_bundle_ids = $item['meta']['stm_lms_bundle_ids'];
                }

                $this->import_postmeta($inserted_post_id, $item['meta']);

                $this->import_attachment($inserted_post_id, $item['image_src']);

                if(count($stm_lms_bundle_ids)) {
                    foreach ($stm_lms_bundle_ids as $id) {
                        $new_ids[] = $this->materials[$id];
                    }
                }

                update_post_meta($inserted_post_id, 'stm_lms_bundle_ids', $new_ids);
            }
        } else {
            $is_skipped = true;
        }

        if($is_skipped) {
            $import_process_option['skipped'][$type]++;
        }
        if($is_created) {
            $import_process_option['created'][$type]++;
        }

        update_option( 'stm_cie_import_process_option', $import_process_option );
    }

    public function import_handler($data = [], $args = [])
    {
        delete_option('stm_cie_import_process_option');

        $this->materials = [];
        $this->bundles_skipped = 0;
        $this->courses_skipped = 0;
        $this->quizzes_skipped = 0;
        $this->lessons_skipped = 0;
        $this->questions_skipped = 0;

        $this->bundles_created = 0;
        $this->courses_created = 0;
        $this->quizzes_created= 0;
        $this->lessons_created = 0;
        $this->questions_created = 0;

        $this->import_process = new STM_CIE_Background_Import();

        if(count($data)) {
            $sorted_data = array(
                'questions' => $data['questions'],
                'quizzes' => $data['quizzes'],
                'lessons' => $data['lessons'],
                'courses' => $data['courses'],
                'bundles' => $data['bundles']
            );

            $skip_for_names = (isset($args['skip_for_names']) && $args['skip_for_names']);

            foreach ($sorted_data as $type => $list) {
                if(is_array($list) && count($list)) {
                    foreach ($list as $item) {
                        $item['type'] = $type;
                        $item['skip_for_names'] = $skip_for_names;

                        $this->import_process->push_to_queue( $item );
                    }
                }
            }
            $this->import_process->save()->dispatch();
        }

    }

    public function _import_handler($data = [], $args = [])
    {
        global $wpdb;

        $this->materials = [];
        $this->bundles_skipped = 0;
        $this->courses_skipped = 0;
        $this->quizzes_skipped = 0;
        $this->lessons_skipped = 0;
        $this->questions_skipped = 0;

        $this->bundles_created = 0;
        $this->courses_created = 0;
        $this->quizzes_created= 0;
        $this->lessons_created = 0;
        $this->questions_created = 0;

        if(isset($data['questions']) && !empty($data['questions'])) {
            $this->questions_skipped = 0;
            $this->questions_created = 0;
            foreach ($data['questions'] as $item) {
                if(isset($args['skip_for_names']) && $args['skip_for_names']) {
                    $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
                    if($exist_id) {
                        $this->questions_skipped++;
                        continue;
                    }
                }

                $inserted_post_id = $this->import_post($item);

                if(is_wp_error($inserted_post_id)) {
                    $this->questions_skipped++;
                    continue;
                }

                $old_id = $item['ID'];
                $this->materials[$old_id] = $inserted_post_id;

                $this->questions_created++;

                $this->import_postmeta($inserted_post_id, $item['meta']);
            }
        }

        if(isset($data['quizzes']) && !empty($data['quizzes'])) {
            $this->quizzes_skipped = 0;
            $this->quizzes_created = 0;
            foreach ($data['quizzes'] as $item) {
                if(isset($args['skip_for_names']) && $args['skip_for_names']) {
                    $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
                    if($exist_id) {
                        $this->quizzes_skipped++;
                        continue;
                    }
                }

                $inserted_post_id = $this->import_post($item);

                if(is_wp_error($inserted_post_id)) {
                    $this->quizzes_skipped++;
                    continue;
                }

                $old_id = $item['ID'];
                $this->materials[$old_id] = $inserted_post_id;

                $this->quizzes_created++;

                $this->import_postmeta($inserted_post_id, $item['meta']);
            }
        }

        if(isset($data['lessons']) && !empty($data['lessons'])) {
            $this->lessons_skipped = 0;
            $this->lessons_created = 0;
            foreach ($data['lessons'] as $item) {
                if(isset($args['skip_for_names']) && $args['skip_for_names']) {
                    $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
                    if($exist_id) {
                        $this->lessons_skipped++;
                        continue;
                    }
                }

                $inserted_post_id = $this->import_post($item);

                if(is_wp_error($inserted_post_id)) {
                    $this->lessons_skipped++;
                    continue;
                }

                $old_id = $item['ID'];
                $this->materials[$old_id] = $inserted_post_id;

                $this->lessons_created++;

                $this->import_postmeta($inserted_post_id, $item['meta']);
            }
        }

        if(isset($data['courses']) && !empty($data['courses'])) {
            $this->courses_skipped = 0;
            $this->courses_created = 0;

            foreach ($data['courses'] as $item) {
                if(isset($args['skip_for_names']) && $args['skip_for_names']) {
                    $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
                    if($exist_id) {
                        $this->courses_skipped++;
                        continue;
                    }
                }

                $inserted_post_id = $this->import_post($item);

                if(is_wp_error($inserted_post_id)) {
                    $this->courses_skipped++;
                    continue;
                }

                $old_id = $item['ID'];
                $this->materials[$old_id] = $inserted_post_id;

                $this->courses_created++;

                $this->import_postmeta($inserted_post_id, $item['meta']);

                $this->import_terms($inserted_post_id, $item['terms']);

                $this->import_attachment($inserted_post_id, $item['image_src']);

                $this->import_sections($inserted_post_id, $item['sections']);
            }
        }

        if(isset($data['bundles']) && !empty($data['bundles'])) {
            $this->bundles_skipped = 0;
            $this->bundles_created = 0;

            foreach ($data['bundles'] as $item) {
                if(isset($args['skip_for_names']) && $args['skip_for_names']) {
                    $exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
                    if($exist_id) {
                        $this->bundles_skipped++;
                        continue;
                    }
                }

                $inserted_post_id = $this->import_post($item);

                if(is_wp_error($inserted_post_id)) {
                    $this->bundles_skipped++;
                    continue;
                }

                $this->bundles_created++;

                $stm_lms_bundle_ids = [];
                $new_ids = [];
                if(isset($item['meta']['stm_lms_bundle_ids']) && !empty($item['meta']['stm_lms_bundle_ids'])) {
                    $stm_lms_bundle_ids = $item['meta']['stm_lms_bundle_ids'];
                }

                $this->import_postmeta($inserted_post_id, $item['meta']);

                $this->import_attachment($inserted_post_id, $item['image_src']);

                if(count($stm_lms_bundle_ids)) {
                    foreach ($stm_lms_bundle_ids as $id) {
                        $new_ids[] = $this->materials[$id];
                    }
                }

                update_post_meta($inserted_post_id, 'stm_lms_bundle_ids', $new_ids);
            }
        }
    }

    /** 
     * Check duplicate course
     * 
     * @param string $course_title
     * @param string $post_type
     * @return bool Return true if a post exists, false otherwise
    */
    protected function check_duplicate_course( $course_title = '', $post_type = 'stm-courses' ) {
        if ( empty( $course_title ) ) {
            return false;
        }
        
        global $wpdb;
        // Query the database to find if there's any course with the same title and post type
        $course = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s AND post_status = 'publish'",
            $course_title, $post_type
        ));
    
        // Return true if a post exists, false otherwise
        return $course ? true : false;
    }
    
    public function import_post($post = [])
    {
        if ( empty( $post ) && ! is_array( $post ) ) {
            return;
        }

        //Check duplicate course
        if ( $this->check_duplicate_course( $post['post_title'], $post['post_type'] ) ) {
            return;
        }

        $insert = array(
            'ID' => '',
            'post_title' => $post['post_title'],
            'post_type' => $post['post_type'],
            'post_status' => $post['post_status'],
            'post_excerpt' => $post['post_excerpt'],
            'post_content' => $post['post_content'],
        );
        return wp_insert_post( $insert );
    }

    public function is_serial($string) {
        return (@unserialize($string) !== false);
    }

    public function import_postmeta($inserted_post_id, $data = [])
    {
        if(get_post_type($inserted_post_id) == 'stm-quizzes') {
            $new_questions_ids = [];
            $questions = (isset($data['questions']) && !empty($data['questions']) && isset($data['questions'][0])) ? $data['questions'][0] : '';
            if(!empty($questions) && !is_array($questions)) {
                $questions = explode(',', $questions);
                foreach ($questions as $question_id) {
                    if(isset($this->materials[$question_id])) {
                        $new_questions_ids[] = $this->materials[$question_id];
                    }
                }
                $data['questions'][0] = implode(',', $new_questions_ids);
            }
        }

        foreach ( $data as $key => $values) {
            foreach( $values as $value ) {
//                if($key == 'course_marks') {
//                    if($this->is_serial($value)) {
//                        add_post_meta( $inserted_post_id, $key, unserialize($value) );
//                    }
//                } else {
//                    add_post_meta( $inserted_post_id, $key, $value );
//                }
                if($this->is_serial($value)) {
                    add_post_meta( $inserted_post_id, $key, unserialize($value) );
                } else {
                    add_post_meta( $inserted_post_id, $key, $value );
                }
            }
        }
    }

    public function import_terms($inserted_post_id, $terms = [])
    {
        wp_set_object_terms( $inserted_post_id, $terms['stm_lms_course_taxonomy'], 'stm_lms_course_taxonomy', false );
    }

    public function import_attachment($inserted_post_id, $image_url = '')
    {
        if( empty($image_url) ) {
            return false;
        }

        $response = wp_remote_get($image_url);
        if (is_wp_error($response)) {
            return new WP_Error('download_error', 'Download error');
        }
        $image_data = wp_remote_retrieve_body($response);

        $file_name = basename($image_url);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $file_name;

        file_put_contents($file_path, $image_data);

        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . basename($file_name),
            'post_mime_type' => wp_check_filetype($file_name, null)['type'],
            'post_title' => sanitize_file_name($file_name),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $inserted_post_id);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        set_post_thumbnail($inserted_post_id, $attachment_id);

    }

    public function import_sections($inserted_post_id, $sections = [])
    {
        if(empty($sections)) return;

        global $wpdb;
        $table_sections = $wpdb->prefix . 'stm_lms_curriculum_sections';
        $table_materials = $wpdb->prefix . 'stm_lms_curriculum_materials';

        foreach ($sections as $section){
            $wpdb->insert($table_sections, array(
                'title' => $section['title'],
                'course_id' => $inserted_post_id,
                'order' => $section['order'],
            ));
            $new_section_id = $wpdb->insert_id;
            if($new_section_id) {
                if(count($section['materials'])) {
                    foreach ($section['materials'] as $materials_item) {
                        $old_post_id = $materials_item['post_id'];
                        $r = $wpdb->insert($table_materials, array(
                            'post_id' => $this->materials[$old_post_id],
                            'post_type' => $materials_item['post_type'],
                            'section_id' => $new_section_id,
                            'order' => $materials_item['order'],
                        ));
                    }
                }
            }
        }
    }

}

new STM_CIE_Import_Manage();