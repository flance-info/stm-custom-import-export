<?php

if(!class_exists('WP_Async_Request')) {
    require_once STMCIE_PATH . '/inc/lib/wp-background-processing/wp-async-request.php';
}
if(!class_exists('WP_Background_Process')) {
    require_once STMCIE_PATH . '/inc/lib/wp-background-processing/wp-background-process.php';
}

require_once STMCIE_PATH . '/inc/classes/STM_CIE_Background_Import.php';

require_once STMCIE_PATH . '/inc/classes/STM_CIE_Import_Manage.php';
require_once STMCIE_PATH . '/inc/classes/STM_CIE_Export_Manage.php';

add_action('admin_enqueue_scripts', function (){
    wp_enqueue_style( 'stmcie', STMCIE_URL. 'assets/css/style.css', [], STMCIE_VERSION, 'all' );
    wp_enqueue_script( 'stmcie', STMCIE_URL . 'assets/js/scripts.js', [], STMCIE_VERSION, true );
});


add_action( 'registered_post_type', function ($post_type, $post_type_object){
    if($post_type == 'stm-course-bundles') {
        $post_type_object->show_in_menu = true;
    }
}, 100005, 2 );


//add_action('init', function (){
//    $course_id = 49995;
//    $marks = get_post_meta( $course_id, 'course_marks', true );
//    update_post_meta( $course_id, 'course_marks', unserialize($marks) );
//    pre_die(unserialize($marks));
//});

