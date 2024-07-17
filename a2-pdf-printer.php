<?php
/*
Plugin Name: A2 PDF Printer
Plugin URI: http://www.a2area.it
Description: Create PDF from single post or custom post in Wordpress
Version: 1.0.0
Author: Alessandro Alessio
Author URI: http://www.a2area.it
License: GPL2
*/

require 'vendor/autoload.php';
use Dompdf\Dompdf;

/**
 * Retrieve pdf variabile from query vars
 */
function a2_pdf_printer_query_vars($vars) {
    $vars[] = 'pdf';
    return $vars;
}
add_filter('query_vars', 'a2_pdf_printer_query_vars');

/**
 * Switch between template name
 */
function a2_pdf_printer_template_path() {
    $post_type = get_post_type();
    if ( $post_type=='post' ) {
        return plugin_dir_path(__FILE__) . 'templates/single.html';
    } else {
        return plugin_dir_path(__FILE__) . 'templates/single-'.get_post_type().'.html';
    }
    return $template;
}

/**
 * Replace all tags in template
 */
function a2_pdf_printer_template($template) {
    global $wpdb;
    $post = get_post();
    $type = get_post_type();

    // General
    $template = str_replace('{{site_title}}', get_bloginfo('name'), $template);
    $template = str_replace('{{site_url}}', get_bloginfo('url'), $template);
    $template = str_replace('{{year}}', date('Y'), $template);

    // Default Content
    $template = str_replace('{{post_title}}', $post->post_title, $template);
    $template = str_replace('{{post_content}}', $post->post_content, $template);
    $template = str_replace('{{post_date}}', $post->post_date, $template);
    $template = str_replace('{{post_author}}', get_the_author_meta('display_name', $post->post_author), $template);
    $template = str_replace('{{post_thumbnail}}', get_the_post_thumbnail_url($post->ID), $template);
    $template = str_replace('{{post_permalink}}', get_permalink($post->ID), $template);

    // Replaces image with full path
    $url_wp_content = get_bloginfo('url').'/wp-content';
    $template = str_replace($url_wp_content, WP_CONTENT_DIR, $template);

    // Custom Fields
    $custom_fields = get_post_custom_values($post->ID);
    $result = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id,meta_key,meta_value FROM wp_posts,wp_postmeta WHERE post_type = %s
          AND wp_posts.ID = wp_postmeta.post_id", $type
    ), ARRAY_A);
    foreach ($result as $row) {
        $template = str_replace('{{_cf_'.$row['meta_key'].'}}', $row['meta_value'], $template);
    }

    return $template;
}

/**
 * Add pdf link to post
 */
function a2_pdf_printer_content() {
    if (is_single() && get_query_var('pdf')) {
        $template_path = a2_pdf_printer_template_path();
        if ( file_exists($template_path) ) {
            $content = file_get_contents($template_path);
            $content = a2_pdf_printer_template($content);

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $dompdf->stream("dompdf_out.pdf", array("Attachment" => false));
            exit(0);
        }
    }
    return $content;
}
add_filter('template_redirect', 'a2_pdf_printer_content');

/**
 * Add shortcode for link to pdf
 */
function a2_pdf_printer_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Download PDF',
    ), $atts, 'a2_pdf_printer');

    return '<a href="'.get_permalink().'?pdf=1" class="a2-pdf-printer-wrapper" title="'.$atts['text'].'">'.$atts['text'].'</a>';
}
add_shortcode('a2_pdf_printer', 'a2_pdf_printer_shortcode');