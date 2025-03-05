<?php
/**
 * Plugin Name: Search Sitewide Plugin
 * Description: A plugin to search for specific text across all WordPress pages and replace it.
 * Version: 1.0
 * Author: Leaders Marketing Tech Dept
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function simple_site_search_menu()
{
    add_management_page(
        'Search Sitewide Plugin',
        'Search Sitewide',
        'manage_options',
        'simple-site-search',
        'simple_site_search_page'
    );
}
add_action('admin_menu', 'simple_site_search_menu');

// Add CSS for the admin page
function simple_site_search_admin_styles()
{
    echo '<style>
        .search-highlight { background-color: #ffff00; }
        .preview-content { max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
    </style>';
}
add_action('admin_head-tools_page_simple-site-search', 'simple_site_search_admin_styles');

// Create the search page
function simple_site_search_page()
{
    ?>
    <div class="wrap">
        <h1>Search Sitewide Plugin</h1>
        <p>Search for words or phrases across all pages and posts on your site, with the option to replace them.</p>

        <form method="post" action="">
            <?php wp_nonce_field('simple_site_search_action', 'simple_site_search_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="search_term">Search Term</label></th>
                    <td>
                        <input type="text" id="search_term" name="search_term" class="regular-text"
                            value="<?php echo isset($_POST['search_term']) ? esc_attr($_POST['search_term']) : ''; ?>"
                            required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="replace_term">Replace With</label></th>
                    <td>
                        <input type="text" id="replace_term" name="replace_term" class="regular-text"
                            value="<?php echo isset($_POST['replace_term']) ? esc_attr($_POST['replace_term']) : ''; ?>">
                        <p class="description">Leave empty for search only.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Content Types</th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_pages" value="1" <?php checked(isset($_POST['search_pages'])); ?>>
                            Pages
                        </label><br>
                        <label>
                            <input type="checkbox" name="search_posts" value="1" <?php checked(isset($_POST['search_posts'])); ?>>
                            Posts
                        </label><br>
                        <label>
                            <input type="checkbox" name="search_custom" value="1" <?php checked(isset($_POST['search_custom'])); ?>>
                            Custom Post Types
                        </label>
                        <p class="description">Searches include both regular content and ACF fields.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Search Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="case_sensitive" value="1" <?php checked(isset($_POST['case_sensitive'])); ?>>
                            Case Sensitive
                        </label><br>
                        <label>
                            <input type="checkbox" name="whole_word" value="1" <?php checked(isset($_POST['whole_word'])); ?>>
                            Whole Word Only
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="search_submit" class="button-primary" value="Search">
            </p>
        </form>

        <?php
        // Process the search when form is submitted
        if (isset($_POST['search_submit']) && check_admin_referer('simple_site_search_action', 'simple_site_search_nonce')) {
            $search_term = sanitize_text_field($_POST['search_term']);
            $replace_term = isset($_POST['replace_term']) ? sanitize_text_field($_POST['replace_term']) : '';
            $search_pages = isset($_POST['search_pages']);
            $search_posts = isset($_POST['search_posts']);
            $search_custom = isset($_POST['search_custom']);
            $case_sensitive = isset($_POST['case_sensitive']);
            $whole_word = isset($_POST['whole_word']);

            if (empty($search_term)) {
                echo '<div class="error"><p>Please enter a search term.</p></div>';
                return;
            }

            if (!$search_pages && !$search_posts && !$search_custom) {
                echo '<div class="error"><p>Please select at least one content type to search.</p></div>';
                return;
            }

            // Perform the search
            $results = perform_site_search($search_term, $search_pages, $search_posts, $search_custom, $case_sensitive, $whole_word);

            // Display results
            if (empty($results)) {
                echo '<div class="notice notice-info"><p>No results found for: <strong>' . esc_html($search_term) . '</strong></p></div>';
            } else {
                echo '<h2>Search Results for: "' . esc_html($search_term) . '"</h2>';

                // Show replace form if results found
                if (!empty($replace_term)) {
                    ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('simple_site_replace_action', 'simple_site_replace_nonce'); ?>
                        <input type="hidden" name="search_term" value="<?php echo esc_attr($search_term); ?>">
                        <input type="hidden" name="replace_term" value="<?php echo esc_attr($replace_term); ?>">
                        <input type="hidden" name="case_sensitive" value="<?php echo $case_sensitive ? '1' : ''; ?>">
                        <input type="hidden" name="whole_word" value="<?php echo $whole_word ? '1' : ''; ?>">

                        <div class="notice notice-warning">
                            <p><strong>Warning:</strong> You are about to replace all occurrences of
                                "<?php echo esc_html($search_term); ?>" with "<?php echo esc_html($replace_term); ?>". This cannot be
                                undone. Make sure you have a backup of your database before proceeding.</p>
                            <p>
                                <input type="submit" name="replace_submit" class="button-primary" value="Replace All Occurrences"
                                    onclick="return confirm('Are you sure you want to replace all occurrences? This cannot be undone.');">
                            </p>
                        </div>
                    </form>
                    <?php
                }

                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>Title</th>';
                echo '<th>Type</th>';
                echo '<th>Preview</th>';
                echo '<th>Actions</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ($results as $result) {
                    // Get the context of the search term
                    $content = $result->post_content;
                    $pos = stripos($content, $search_term);
                    $start = max(0, $pos - 50);
                    $length = strlen($search_term) + 100;
                    $context = substr($content, $start, $length);

                    // Highlight the search term
                    if (!$case_sensitive) {
                        $context = preg_replace('/(' . preg_quote($search_term, '/') . ')/i', '<span class="search-highlight">$1</span>', $context);
                    } else {
                        $context = str_replace($search_term, '<span class="search-highlight">' . $search_term . '</span>', $context);
                    }

                    echo '<tr>';
                    echo '<td>' . esc_html($result->post_title) . '</td>';
                    echo '<td>' . esc_html(ucfirst($result->post_type)) . '</td>';
                    echo '<td><div class="preview-content">' . wp_kses_post($context) . '</div></td>';
                    echo '<td>';
                    echo '<a href="' . esc_url(get_permalink($result->ID)) . '" target="_blank">View</a> | ';
                    echo '<a href="' . esc_url(get_edit_post_link($result->ID)) . '">Edit</a>';

                    // Add individual replace option
                    if (!empty($replace_term)) {
                        echo ' | <a href="#" class="replace-single" data-post-id="' . esc_attr($result->ID) . '">Replace</a>';
                    }

                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
        }

        // Process the replacement
        if (isset($_POST['replace_submit']) && check_admin_referer('simple_site_replace_action', 'simple_site_replace_nonce')) {
            $search_term = sanitize_text_field($_POST['search_term']);
            $replace_term = sanitize_text_field($_POST['replace_term']);
            $case_sensitive = isset($_POST['case_sensitive']);
            $whole_word = isset($_POST['whole_word']);

            // Get all posts with the search term
            $results = perform_site_search($search_term, true, true, true, $case_sensitive, $whole_word);

            $count = 0;
            foreach ($results as $post) {
                $new_content = perform_replacement($post->post_content, $search_term, $replace_term, $case_sensitive, $whole_word);
                $new_title = perform_replacement($post->post_title, $search_term, $replace_term, $case_sensitive, $whole_word);

                // Update the post if content changed
                if ($new_content !== $post->post_content || $new_title !== $post->post_title) {
                    wp_update_post(array(
                        'ID' => $post->ID,
                        'post_content' => $new_content,
                        'post_title' => $new_title
                    ));
                    $count++;
                }

                // Also update ACF fields
                $acf_updated = perform_acf_replacement($post->ID, $search_term, $replace_term, $case_sensitive, $whole_word);
                if ($acf_updated > 0) {
                    $count += $acf_updated;
                }
            }

            echo '<div class="notice notice-success"><p>' . sprintf(_n('%s item updated.', '%s items updated.', $count), $count) . '</p></div>';
        }
        ?>
    </div>
    <?php
}

// Function to perform the search
function perform_site_search($search_term, $search_pages, $search_posts, $search_custom, $case_sensitive, $whole_word = false)
{
    global $wpdb;

    $post_types = array();
    if ($search_pages) {
        $post_types[] = 'page';
    }
    if ($search_posts) {
        $post_types[] = 'post';
    }
    if ($search_custom) {
        // Get all registered custom post types
        $custom_types = get_post_types(array('_builtin' => false), 'names');
        $post_types = array_merge($post_types, $custom_types);
    }

    if (empty($post_types)) {
        return array();
    }

    $post_types_str = "'" . implode("','", $post_types) . "'";

    // Build the search query
    $like_operator = $case_sensitive ? 'LIKE BINARY' : 'LIKE';

    // Prepare search term
    $search_like = '%' . $wpdb->esc_like($search_term) . '%';

    // Query for post content and title
    if ($whole_word) {
        // For whole word search, we use REGEXP
        $main_query = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} AS p
            WHERE (p.post_content REGEXP %s OR p.post_title REGEXP %s)
            AND p.post_type IN ({$post_types_str})
            AND p.post_status = 'publish'",
            '[[:<:]]' . $search_term . '[[:>:]]',
            '[[:<:]]' . $search_term . '[[:>:]]'
        );
    } else {
        $main_query = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} AS p
            WHERE (p.post_content {$like_operator} %s OR p.post_title {$like_operator} %s)
            AND p.post_type IN ({$post_types_str})
            AND p.post_status = 'publish'",
            $search_like,
            $search_like
        );
    }

    // Query for ACF and other meta fields
    if ($whole_word) {
        // Whole word in meta is more complex, simplified here
        $meta_query = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} AS p
            JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE pm.meta_value REGEXP %s
            AND p.post_type IN ({$post_types_str})
            AND p.post_status = 'publish'",
            '[[:<:]]' . $search_term . '[[:>:]]'
        );
    } else {
        $meta_query = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} AS p
            JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE pm.meta_value {$like_operator} %s
            AND p.post_type IN ({$post_types_str})
            AND p.post_status = 'publish'",
            $search_like
        );
    }

    // Combine the queries with UNION
    $combined_query = "({$main_query}) UNION ({$meta_query}) ORDER BY post_title";

    return $wpdb->get_results($combined_query);
}

// Function to perform text replacement
function perform_replacement($content, $search, $replace, $case_sensitive, $whole_word)
{
    if ($whole_word) {
        if ($case_sensitive) {
            return preg_replace('/\b' . preg_quote($search, '/') . '\b/', $replace, $content);
        } else {
            return preg_replace('/\b' . preg_quote($search, '/') . '\b/i', $replace, $content);
        }
    } else {
        if ($case_sensitive) {
            return str_replace($search, $replace, $content);
        } else {
            return str_ireplace($search, $replace, $content);
        }
    }
}

// Function to replace content in ACF fields for a post
function perform_acf_replacement($post_id, $search, $replace, $case_sensitive, $whole_word)
{
    global $wpdb;

    // Get all meta for this post
    $meta_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = %d AND meta_value LIKE %s",
            $post_id,
            '%' . $wpdb->esc_like($search) . '%'
        )
    );

    $updated = 0;

    foreach ($meta_rows as $meta) {
        // Skip internal ACF fields
        if (strpos($meta->meta_key, '_') === 0) {
            continue;
        }

        $new_value = perform_replacement($meta->meta_value, $search, $replace, $case_sensitive, $whole_word);

        // Only update if something changed
        if ($new_value !== $meta->meta_value) {
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new_value),
                array('meta_id' => $meta->meta_id)
            );
            $updated++;
        }
    }

    return $updated;
}

// AJAX handler for single post replacement (to be implemented)
function single_post_replace_callback()
{
    // To be implemented for individual post replacements
    // This would require adding JavaScript to the admin page
}
//add_action('wp_ajax_single_post_replace', 'single_post_replace_callback');