<?php

class STM_CIE_Export_Manage
{
    private array $support;
    private string $current;

    private array $courses;
    private array $quizzes;
    private array $lessons;
    private array $questions;

    public function __construct()
    {
        $this->support = array(
            'stm-courses',
            'stm-quizzes',
            'stm-lessons',
            'stm-course-bundles',
        );

        $this->courses = [];
        $this->lessons = [];
        $this->quizzes = [];
        $this->questions = [];

        $this->current = (isset($_REQUEST['post_type'])) ? sanitize_text_field($_REQUEST['post_type']) : '';

        foreach ($this->support as $post_type) {
            add_filter('bulk_actions-edit-'.$post_type, array($this, 'bulk_actions_dropdown'), 20);
            add_filter('handle_bulk_actions-edit-'.$post_type, array($this, 'bulk_actions_handle'), 20, 3);
        }

        add_action('admin_notices', array($this, 'notices'), 20, 3);
        add_action('wp_ajax_stm_cie_export_selected', array($this, 'export_selected'));

        add_filter('stmcie_response_export_data', array($this, 'export_data'), 10, 3);

//        add_action('wp_ajax_stm_cie_export_all', array($this, 'export_all'));
//        add_action('manage_posts_extra_tablenav', array($this, 'extra_tablenav'), 15);
    }

    public function bulk_actions_dropdown($bulk_actions)
    {
        $bulk_actions['export'] = __('Export to File', 'stmcie');
        return $bulk_actions;
    }

    public function bulk_actions_handle($redirect_url, $action, $post_ids)
    {
        if ($action == 'export') {
            $redirect_url = add_query_arg('export', implode(',',$post_ids), $redirect_url);
        }
        return $redirect_url;
    }

    public function notices()
    {
        if (!empty($_REQUEST['export'])) {
            echo '<div id="message" class="updated notice is-dismissable stm-cie-export-notice"><p>' . __('Export selected posts.', 'stmcie') . '</p></div>';
        }
    }

    public function export_data_bundles($object_ids = [])
    {
        $list = [];
        if(count($object_ids)) {
            foreach ( $object_ids as $post_id ) {
                $post = $this->get_modified_post_data($post_id);
                $post['meta'] = $this->get_modified_post_meta($post_id);

                $attachment_id = get_post_thumbnail_id( $post_id );
                $post['image_id'] = $attachment_id;
                $post['image_src']  = '';
                if(!empty($attachment_id)) {
                    $image = wp_get_attachment_image_src($attachment_id, 'full');
                    if(isset($image[0])) {
                        $post['image_src'] = $image[0];
                    }
                }

                $courses = get_post_meta( $post_id, 'stm_lms_bundle_ids', true );
                if(count($courses)) {
                    $this->courses = array_map('intval', $courses);
                }

                $list[] = $post;
            }
        }
        return $list;
    }

    public function export_data_courses($object_ids = [])
    {
        $list = [];
        if(count($object_ids)) {

            global $wpdb;
            $table_sections = $wpdb->prefix . 'stm_lms_curriculum_sections';
            $table_materials = $wpdb->prefix . 'stm_lms_curriculum_materials';

            $this->lessons = [];

            foreach ( $object_ids as $post_id ) {
                $post = $this->get_modified_post_data($post_id);
                $post['meta'] = $this->get_modified_post_meta($post_id);
                $post['terms']['stm_lms_course_taxonomy'] = wp_get_object_terms( $post_id, 'stm_lms_course_taxonomy', array( 'fields' => 'names' ) );
                $attachment_id = get_post_thumbnail_id( $post_id );
                $post['image_id'] = $attachment_id;
                $post['image_src']  = '';
                if(!empty($attachment_id)) {
                    $image = wp_get_attachment_image_src($attachment_id, 'full');
                    if(isset($image[0])) {
                        $post['image_src'] = $image[0];
                    }
                }

                $sections = [];

                $sections_rows = $wpdb->get_results("SELECT * FROM $table_sections WHERE course_id = $post_id", ARRAY_A );
                if(count($sections_rows)) {
                    foreach ($sections_rows as $row) {
                        $section_id = intval($row['id']);
                        $sections[$section_id] = array(
                            'title' => $row['title'],
                            'order' => $row['order'],
                        );
                        $materials_rows = $wpdb->get_results("SELECT * FROM $table_materials WHERE section_id = $section_id", ARRAY_A);
                        if(count($materials_rows)) {
                            foreach ($materials_rows as $row_child) {
                                $sections[$section_id]['materials'][] = array(
                                    'post_id' => $row_child['post_id'],
                                    'post_type' => $row_child['post_type'],
                                    'section_id' => $section_id,
                                    'order' => $row_child['order'],
                                );
                            }
                        }
                    }
                }

                $post['sections'] = $sections;

                $course_materials = ( new \MasterStudy\Lms\Repositories\CurriculumMaterialRepository() )->get_course_materials( $post_id );
                $post['materials_ids'] = $course_materials;
                if(count($course_materials)) {
                    foreach ($course_materials as $item_id) {
                        if(get_post_type($item_id) == 'stm-lessons') {
                            $this->lessons[] = $item_id;
                        } elseif (get_post_type($item_id) == 'stm-quizzes') {
                            $this->quizzes[] = $item_id;
                        }
                    }
                }

                $list[] = $post;
            }
        }
        return $list;
    }

    public function export_data_quizzes($object_ids = [])
    {
        $list = [];
        if(count($object_ids)) {
            foreach ( $object_ids as $post_id ) {
                $post = $this->get_modified_post_data($post_id);
                $post['meta'] = $this->get_modified_post_meta($post_id);
                $list[] = $post;
                $questions = (isset($post['meta']['questions']) && !empty($post['meta']['questions']) && isset($post['meta']['questions'][0])) ? $post['meta']['questions'][0] : '';
                $questions = (!empty($questions) && !is_array($questions)) ? explode(',', $questions) : $questions;
                $this->questions = ( ! empty( $questions ) && is_array( $questions ) ) ? array_merge($this->questions, $questions) : array();
            }
        }
        return $list;
    }

    public function export_data_lessons($object_ids = [])
    {
        $list = [];
        if(count($object_ids)) {
            foreach ( $object_ids as $post_id ) {
                $post = $this->get_modified_post_data($post_id);
                $post['meta'] = $this->get_modified_post_meta($post_id);
                $list[] = $post;
            }
        }
        return $list;
    }

    public function export_data_questions($object_ids = [])
    {
        $list = [];
        if(count($object_ids)) {
            foreach ( $object_ids as $post_id ) {
                $post = $this->get_modified_post_data($post_id);
                $post['meta'] = $this->get_modified_post_meta($post_id);
                $list[] = $post;
            }
        }
        return $list;
    }

    public function get_modified_post_data($post_id)
    {
        $post = get_post( $post_id, ARRAY_A );
        return array(
            'ID' => $post['ID'],
            'post_type' => $post['post_type'],
            'post_title' => $post['post_title'],
            'post_excerpt' => $post['post_excerpt'],
            'post_content' => $post['post_content'],
            'post_status' => $post['post_status'],
            'post_author' => $post['post_author'],
        );
    }

    public function get_modified_post_meta($post_id)
    {
        $meta = get_post_custom( $post_id );
        $dissalow_list = [
            'views',
            '_edit_lock',
            '_vc_post_settings',
            '_wpb_vc_js_status',
            '_wpb_shortcodes_custom_css',
            '_yoast_wpseo_content_score',
            '_yoast_wpseo_primary_stm_lms_course_taxonomy',
            '_wp_old_slug',
            '_elementor_edit_mode',
            '_elementor_data',
        ];
        foreach ($meta as $key => $value) {
            if(in_array($key, $dissalow_list)) {
                unset($meta[$key]);
            }
        }
        return $meta;
    }


    public function export_selected()
    {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $object_ids = (isset($_REQUEST['export'])) ? explode(',', $_REQUEST['export']) : [];
        $type = (isset($_REQUEST['type'])) ? sanitize_text_field($_REQUEST['type']) : '';

        $response = array();

        if(empty($object_ids) || empty($type)){
            wp_send_json($response);
        }

        $response['exportData'] = apply_filters('stmcie_response_export_data', [], $object_ids, $type);

        wp_send_json($response);
    }

    public function export_data($response, $object_ids, $type)
    {
        if($type == 'stm-course-bundles') {

            $response['bundles'] = $this->export_data_bundles($object_ids);
            $response['courses'] = $this->export_data_courses($this->courses);
            $response['lessons'] = $this->export_data_lessons($this->lessons);
            $response['quizzes'] = $this->export_data_quizzes($this->quizzes);
            $response['questions'] = $this->export_data_questions($this->questions);

        } elseif($type == 'stm-courses') {

            $response['courses'] = $this->export_data_courses($object_ids);
            $response['lessons'] = $this->export_data_lessons($this->lessons);
            $response['quizzes'] = $this->export_data_quizzes($this->quizzes);
            $response['questions'] = $this->export_data_questions($this->questions);

        } elseif($type == 'stm-quizzes') {

            $response['quizzes'] = $this->export_data_quizzes($object_ids);
            $response['questions'] = $this->export_data_questions($this->questions);

        } elseif($type == 'stm-lessons') {

            $response['lessons'] = $this->export_data_lessons($object_ids);

        } elseif($type == 'stm-questions') {

            $response['questions'] = $this->export_data_questions($this->questions);

        }
        return $response;
    }

    public function export_all()
    {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $response = array();

        $type = (isset($_REQUEST['type'])) ? sanitize_text_field($_REQUEST['type']) : '';

        wp_send_json($response);
    }

    public function extra_tablenav($which)
    {
        if($which == 'top' && in_array($this->current, $this->support)) {
            ?>
            <div class="alignleft actions stm-cie-export-action">
                <button type="button" class="button button-primary stm-cie-export-all"><i class="fa fa-upload"></i> <?php _e('Export All to File', 'stmcie'); ?></button>
            </div>
            <?php
        }
    }

}

new STM_CIE_Export_Manage();