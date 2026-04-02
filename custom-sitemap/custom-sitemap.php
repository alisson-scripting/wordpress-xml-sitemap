<?php

/**
 * Plugin Name: Combined XML Sitemap
 * Description: Dynamic XML sitemap for posts, pages, categories, and images.
 * Version: 1.0.0
 * Author: Andreas Lisson
 * Author URI: https://www.lisson-webdevelopment.de
 * License: GPLv2 or later
 * Text Domain: custom-sitemap
 * Domain Path: /languages
 *
 * @package custom-sitemap
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CUSTOM_SITEMAP_EXCLUDED_SLUGS' ) ) {
    define( 'CUSTOM_SITEMAP_EXCLUDED_SLUGS', array( 'impressum', 'datenschutz' ) );
}

if ( ! defined( 'CUSTOM_SITEMAP_EXCLUDED_IMAGE_PARTS' ) ) {
    define( 'CUSTOM_SITEMAP_EXCLUDED_IMAGE_PARTS', array( 'adobestock', 'shutterstock' ) );
}

add_filter( 'query_vars', 'custom_sitemap_register_query_vars' );
add_action( 'template_redirect', 'custom_sitemap_render_xml' );

/**
 * Registers required query variables.
 *
 * @param array<int, string> $query_vars Query variables.
 * @return array<int, string>
 */
function custom_sitemap_register_query_vars( $query_vars ) {
    $query_vars[] = 'sitemap';

    return $query_vars;
}

/**
 * Renders the XML sitemap.
 *
 * @return void
 */
function custom_sitemap_render_xml() {
    $sitemap_requested = get_query_var( 'sitemap' );

    if ( '1' !== (string) $sitemap_requested ) {
        return;
    }

    // nocache_headers();
    status_header( 200 );
    header( 'Content-Type: application/xml; charset=' . get_option( 'blog_charset' ) );
    // cache: 1 week
    header( 'Cache-Control: public, max-age=604800, s-maxage=604800' );
    header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + WEEK_IN_SECONDS ) . ' GMT' );

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    custom_sitemap_output_posts_and_pages();
    custom_sitemap_output_categories();

    echo '</urlset>';
    exit;
}


/**
 * Outputs posts and pages.
 *
 * @return void
 */
function custom_sitemap_output_posts_and_pages() {
    $page     = 1;
    $per_page = 100;

    do {
        $query = new WP_Query(
            array(
                'post_type'              => array( 'post', 'page' ),
                'post_status'            => 'publish',
                'posts_per_page'         => $per_page,
                'paged'                  => $page,
                'no_found_rows'          => false,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            )
        );

        if ( ! $query->have_posts() ) {
            break;
        }

        while ( $query->have_posts() ) {
            $query->the_post();

            $slug = trim( (string) wp_parse_url( get_permalink(), PHP_URL_PATH ), '/' );

            if ( in_array( $slug, CUSTOM_SITEMAP_EXCLUDED_SLUGS, true ) ) {
                continue;
            }

            echo '<url>' . "\n";
            echo '<loc>' . esc_xml( get_permalink() ) . '</loc>' . "\n";
            echo '<lastmod>' . esc_xml( get_post_modified_time( 'c', true ) ) . '</lastmod>' . "\n";
            echo '<changefreq>weekly</changefreq>' . "\n";
            echo '<priority>0.8</priority>' . "\n";

            $images = get_attached_media( 'image', get_the_ID() );

            $thumbnail_id = get_post_thumbnail_id( get_the_ID() );
            if ( $thumbnail_id ) {
                $thumbnail_url = wp_get_attachment_url( $thumbnail_id );
                $thumbnail_post = get_post( $thumbnail_id );

                if ( ! empty( $thumbnail_url ) && $thumbnail_post instanceof WP_Post ) {
                    $images[ $thumbnail_id ] = $thumbnail_post;
                }
            }

            if ( ! empty( $images ) ) {
                foreach ( $images as $image ) {
                    if ( ! $image instanceof WP_Post ) {
                        continue;
                    }

                    $image_url = wp_get_attachment_url( $image->ID );

                    if ( empty( $image_url ) ) {
                        continue;
                    }

                    $image_name = strtolower( (string) get_post_meta( $image->ID, '_wp_attached_file', true ) );

                    foreach ( CUSTOM_SITEMAP_EXCLUDED_IMAGE_PARTS as $excluded_part ) {
                        if ( false !== strpos( $image_name, $excluded_part ) ) {
                            continue 2;
                        }
                    }

                    echo '<image:image>' . "\n";
                    echo '<image:loc>' . esc_xml( $image_url ) . '</image:loc>' . "\n";
                    echo '</image:image>' . "\n";
                }
            }

            echo '</url>' . "\n";
        }

        wp_reset_postdata();
        $page++;
    } while ( $page <= $query->max_num_pages );
}


/**
 * Outputs categories.
 *
  * @return void
 */
function custom_sitemap_output_categories() {
    $categories = get_terms(
        array(
            'taxonomy'   => 'category',
            'hide_empty' => true,
        )
    );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return;
    }

    foreach ( $categories as $category ) {
        if ( in_array( $category->slug, CUSTOM_SITEMAP_EXCLUDED_SLUGS, true ) ) {
            continue;
        }

        $category_url = get_term_link( $category );

        if ( is_wp_error( $category_url ) ) {
            continue;
        }

        echo '<url>' . "\n";
        echo '<loc>' . esc_xml( $category_url ) . '</loc>' . "\n";

        echo '<changefreq>weekly</changefreq>' . "\n";
        echo '<priority>0.6</priority>' . "\n";
        echo '</url>' . "\n";
    }
}