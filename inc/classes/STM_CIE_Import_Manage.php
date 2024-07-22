<?php

class STM_CIE_Import_Manage
{
    CONST PAGE_SLUG = 'stm_cie_import';

    private array $materials;
    private $import_process;

    private array $detected;
    private array $types;
    private array $statuses_default;

    public function __construct(){
        add_action('admin_menu', array($this, 'add_menu_page'), 10000);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts_styles'));
        add_action('wp_ajax_stm_cie_import', array($this, 'import'));
        add_action('wp_ajax_stm_cie_check_import', array($this, 'check_import'));

        $this->types = array(
            'bundles' => 0, 'courses' => 0, 'quizzes' => 0, 'lessons' => 0, 'questions' => 0
        );
        $this->statuses_default = array(
            'skipped' => $this->types,
            'created' => $this->types
        );
        $this->detected = $this->types;

        $this->import_process = new STM_CIE_Background_Import();
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
            $response['message'] = __('Please choose .json file', 'stmcie');
            $response['message_status'] = 'danger';
            wp_send_json($response);
        }

        $import_file = $_FILES['import_file'];

        $skip_for_names = (isset($_REQUEST['skip_for_names'])) ? sanitize_text_field($_REQUEST['skip_for_names']) : 'false';
        $skip_for_names = ($skip_for_names == 'true');

        if ( ! file_exists( $import_file['tmp_name'] ) ) {
            wp_send_json($response);
        }

        $decoded_file = wp_json_file_decode($import_file['tmp_name'], ['associative' => true]);

        $this->import_handler($decoded_file, ['skip_for_names' => $skip_for_names]);

        $response['success'] = true;

        wp_send_json($response);
    }

    public function check_import()
    {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $response = array();

        $response['show_info'] = true;

        $import_process_option = get_option('stm_cie_import_process_option', []);
        $import_types_detected = get_option('stm_cie_import_types_detected', []);
        $import_status = get_option('stm_cie_import_status', '');

        foreach ($import_types_detected as $type => $count) {
            $response['skipped'][$type] = $import_process_option['skipped'][$type].'/'.$count;
            $response['created'][$type] = $import_process_option['created'][$type].'/'.$count;
        }

        $response['detected'] = $import_types_detected;

        if($import_status == 'completed') {
            $response['ended'] = true;
            $response['message'] = __('Successfully completed', 'stmcie');
            $response['success'] = true;
            wp_send_json($response);
        }

        wp_send_json($response);
    }

	public function item_handler( $item = [] ) {


		if ( ! isset( $item['type'] ) || empty( $item['type'] ) ) {
			return;
		}
		global $wpdb;
		$import_process_option = get_option( 'stm_cie_import_process_option', $this->statuses_default );
		$import_materials      = get_option( 'stm_cie_import_materials', [] );
		$type = $item['type'];
		$is_skipped = false;
		$is_created = false;
		if ( $item['skip_for_names'] && ! empty( $item['post_title'] ) ) {
			$exist_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item['post_title'] . "'" );
			if ( $exist_id ) {
				$is_skipped = true;
			}
		}
		$inserted_post_id = $this->import_post( $item );
		if ( ! is_wp_error( $inserted_post_id ) ) {
			$old_id                      = $item['ID'];
			$this->materials[ $old_id ]  = $inserted_post_id;
			$import_materials[ $old_id ] = $inserted_post_id;
			if ( isset( $item['meta'] ) && ! empty( $item['meta'] ) ) {
				$this->import_postmeta( $inserted_post_id, $item['meta'] );
			}
			$is_created = true;
			if ( $type == 'courses' ) {
				if ( isset( $item['terms'] ) && ! empty( $item['terms'] ) ) {
					$this->import_terms( $inserted_post_id, $item['terms'] );
				}
				$this->import_attachment( $inserted_post_id, $item['image_src'] );
				$this->import_sections( $inserted_post_id, $item['sections'] );
			} elseif ( $type == 'bundles' ) {
				$stm_lms_bundle_ids = [];
				$new_ids            = [];
				if ( isset( $item['meta']['stm_lms_bundle_ids'] ) && ! empty( $item['meta']['stm_lms_bundle_ids'] ) ) {
					$stm_lms_bundle_ids = $item['meta']['stm_lms_bundle_ids'];
				}
				$this->import_postmeta( $inserted_post_id, $item['meta'] );
				$this->import_attachment( $inserted_post_id, $item['image_src'] );
				if ( count( $stm_lms_bundle_ids ) ) {
					foreach ( $stm_lms_bundle_ids as $id ) {
						$new_ids[] = $this->materials[ $id ];
					}
				}
				update_post_meta( $inserted_post_id, 'stm_lms_bundle_ids', $new_ids );
			}
		} else {
			$is_skipped = true;
		}
		if ( $is_skipped ) {
			$import_process_option['skipped'][ $type ] ++;
		}
		if ( $is_created ) {
			$import_process_option['created'][ $type ] ++;
		}
		custom_log( $import_process_option );
		update_option( 'stm_cie_import_process_option', $import_process_option );
		update_option( 'stm_cie_import_materials', $import_materials );
	}

    public function import_handler($data = [], $args = [])
    {
        delete_option('stm_cie_import_process_option');
        delete_option('stm_cie_import_types_detected');
        delete_option('stm_cie_import_status');
        delete_option('stm_cie_import_materials');

        $this->materials = [];

        $this->detected = $this->types;

        if(count($data)) {
            $sorted_data = array(
                'questions' => (!empty($data['questions'])) ? $data['questions'] : [],
                'quizzes' => (!empty($data['quizzes'])) ? $data['quizzes'] : [],
                'lessons' => (!empty($data['lessons'])) ? $data['lessons'] : [],
                'courses' => (!empty($data['courses'])) ? $data['courses'] : [],
                'bundles' => (!empty($data['bundles'])) ? $data['bundles'] : [],
            );

            foreach ($sorted_data as $key => $list) {
                $this->detected[$key] = (is_array($list)) ? count($list) : 0;
            }

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

        update_option( 'stm_cie_import_types_detected', $this->detected );

    }

	public function import_post( $post = [] ) {
		global $wpdb;
		// Query the database to find if there's any course with the same title and post type
		$course_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s AND post_status != 'trash'",
			$post['post_title'], $post['post_type']
		) );
		if ( $course_id ) {
			if ( ! $post['skip_for_names'] ) {
				$update_course = array(
					'ID'           => $course_id,
					'post_excerpt' => $post['post_excerpt'],
					'post_content' => $post['post_content'],
				);
				// Update the course
				wp_update_post( $update_course );

				return array( 'status' => 'updated', 'post_id' => $course_id );
			}

			return array( 'status' => 'skipped', 'post_id' => $course_id );
		}
		$insert = array(
			'ID'           => '',
			'post_title'   => $post['post_title'],
			'post_type'    => $post['post_type'],
			'post_status'  => $post['post_status'],
			'post_excerpt' => $post['post_excerpt'],
			'post_content' => $post['post_content'],
		);
		// Create a new course
		$inserted_post_id = wp_insert_post( $insert );
		if ( ! is_wp_error( $inserted_post_id ) ) {
			return array( 'status' => 'created', 'post_id' => $inserted_post_id );
		}

		return array( 'status' => 'error', 'post_id' => 0 );
	}


    public function is_serial($string) {
        return (@unserialize($string) !== false);
    }

    public function import_postmeta($inserted_post_id, $data = [])
    {
        $import_materials = get_option('stm_cie_import_materials', []);
        if(get_post_type($inserted_post_id) == 'stm-quizzes') {
            $new_questions_ids = [];
            $questions = (isset($data['questions']) && !empty($data['questions']) && isset($data['questions'][0])) ? $data['questions'][0] : '';
            if(!empty($questions) && !is_array($questions)) {
                $questions = explode(',', $questions);
                foreach ($questions as $question_id) {
                    if(isset($import_materials[$question_id])) {
                        $new_questions_ids[] = $import_materials[$question_id];
                    }
                }
                $data['questions'][0] = implode(',', $new_questions_ids);
            }
        }

        foreach ( $data as $key => $values) {
            foreach( $values as $value ) {
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
        if( empty( $sections ) ) return;
        global $wpdb;
        $table_sections = $wpdb->prefix . 'stm_lms_curriculum_sections';
        $table_materials = $wpdb->prefix . 'stm_lms_curriculum_materials';
        $import_materials = get_option('stm_cie_import_materials', []);

        foreach ($sections as $section){
            $new_section_id = 0;
            if ( ! empty( $section['title'] ) && ! empty( $section['order'] ) ) {
                $wpdb->insert($table_sections, array(
                    'title' => $section['title'],
                    'course_id' => $inserted_post_id,
                    'order' => $section['order'],
                ));
                $new_section_id = $wpdb->insert_id;
            }
            
            if( $new_section_id ) {
                if( count( $section['materials'] ) ) {
                    foreach ( $section['materials'] as $materials_item ) {
                        $old_post_id = $materials_item['post_id'];
                        $r = $wpdb->insert($table_materials, array(
                            'post_id' => $import_materials[$old_post_id],
                            'post_type' => $materials_item['post_type'],
                            'section_id' => $new_section_id,
                            'order' => $materials_item['order'],
                        ));
                    }
                }
            }
        }
    }

    public function import_completed()
    {
        update_option('stm_cie_import_status', 'completed');
        delete_option('stm_cie_import_materials');
    }

}

new STM_CIE_Import_Manage();