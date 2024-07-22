<?php
/*
Plugin Name: STM Custom Import & Export
Plugin URI: https://stylemix.net/
Description: STM Custom Import & Export plugin for https://study.vyse.de/
Author: Stylemix
Author URI: https://stylemix.net/
Text Domain: stmcie
Version: 1.0.2
*/

define( 'STMCIE_VERSION', '1.0.2' );
define( 'STMCIE_PATH', dirname( __FILE__ ) );
define( 'STMCIE_URL', plugin_dir_url( __FILE__ ) );
$plugin_path = dirname( __FILE__ );

require_once STMCIE_PATH . '/inc/functions.php';

if ( ! is_textdomain_loaded( 'stmcie' ) ) {
    load_plugin_textdomain(
        'stmcie',
        false,
        'stmcie/languages'
    );
}

// if(!function_exists('pre_var')) {
//     function pre_var($var){
//         echo '<pre>';
//         var_dump($var);
//         echo '</pre>';
//     }
// }

// if(!function_exists('pre_die')) {
//     function pre_die($var){
//         pre_var($var);
//         die();
//     }
// }

// if (!function_exists('write_log')) {
//     function write_log($log) {
//         if (true === WP_DEBUG) {
//             if (is_array($log) || is_object($log)) {
//                 error_log(print_r($log, true));
//             } else {
//                 error_log($log);
//             }
//         }
//     }
// }

function custom_log( $message, $log_file = 'custom_debug.log' ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }
        $time = date( 'Y-m-d H:i:s' );
        $log_message = "[{$time}] {$message}\n";
        $log_file_path = WP_CONTENT_DIR . '/' . $log_file;
        error_log( $log_message, 3, $log_file_path );
    }
}
