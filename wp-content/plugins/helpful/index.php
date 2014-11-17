<?php
/**
 * Plugin Name: Helpful
 * Plugin URI: http://gielberkers.com
 * Description: Adds a simple 'was this post helpful?'-question to each post
 * Version: 1.0
 * Author: Giel Berkers
 * Author URI: http://gielberkers.com
 * License: GPL2
 */

// Block direct access:
defined('ABSPATH') or die("No script kiddies please!");

/**
 * Class Helpful - Wrapper class for the Helpful plugin 
 */
class Helpful
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter( 'the_content', array($this, 'filterTheContent'));
        add_action( 'get_template_part_helpful/form', array($this, 'getTemplatePart'));
        add_action( 'get_template_part_helpful/results', array($this, 'getTemplatePart'));
        register_activation_hook(__FILE__, array($this, 'install'));
        // Register AJAX-action:
        add_action( 'wp_ajax_helpful_post', array($this, 'savePostRequest'));
        add_action( 'wp_ajax_nopriv_helpful_post', array($this, 'savePostRequest'));
        // Add action to add metaboxes:
        add_action( 'add_meta_boxes', array($this, 'addMetaBoxes'));
        // Add a custom column to our overview page:
        add_action( 'manage_posts_columns', array($this, 'addCustomColumn'));
        add_action( 'manage_posts_custom_column', array($this, 'populateCustomColumn'), 10, 2);
        // Make column sortable:
        add_filter( 'manage_edit-post_sortable_columns', array($this, 'addSortableColumn'));
        add_filter('posts_clauses', array($this, 'postsClauses'));
    }

    /**
     * Add some HTML after the content
     * 
     * @param $content
     * @return mixed
     */
    public function filterTheContent($content)
    {
        global $page, $numpages, $multipage;
        // Check if we are on a single page:
        if(is_single())
        {
            // Check if this is the last page of a multi-page post:
            if($multipage == 1 && $page != $numpages) {
                return $content;
            }
            // Add some HTML after the content, just to prove a point:
            ob_start();
            get_template_part( 'helpful/results' );
            get_template_part( 'helpful/form' );
            $html = ob_get_clean();
            $content .= $html;
        }
        return $content;
    }

    /**
     * Register template part
     * 
     * @param $slug
     * @param null $name
     */
    public function getTemplatePart($slug, $name = null)
    {
        $templateDirectory = get_template_directory();
        // Check if there is a customized template in our template directory:
        if(!file_exists( $templateDirectory . '/' . $slug . '.php' ))
        {
            // Nope, just load the regular template then:
            load_template(dirname(__FILE__) . '/' . 
                    str_replace('helpful/', 'templates/', $slug) . '.php', false);
        }        
    }

    /**
     * Installation function
     */
    public function install()
    {
        global $wpdb;
        // Setup query:
        $sql = sprintf('
            CREATE TABLE IF NOT EXISTS `%1$shelpful` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `id_post` bigint(20) unsigned NOT NULL,
                `ip` varchar(255) NOT NULL DEFAULT \'\',
                `rating` int(11) NOT NULL,
                `feedback` text,
                PRIMARY KEY (`id`),
                KEY `id_post` (`id_post`),
                CONSTRAINT `%1$shelpful_ibfk_1` FOREIGN KEY (`id_post`) REFERENCES `%1$sposts` (`ID`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;        
        ', $wpdb->prefix);
        // Execute query:
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }

    /**
     * Get the URL where the form should POST to
     * 
     * @return string|void
     */
    public static function getFormUrl()
    {
        return admin_url('admin-ajax.php?action=helpful_post');
    }
    
    /**
     * This function is fired when we call /wp-admin/admin-ajax.php?action=helpful_post
     */
    public function savePostRequest()
    {
        global $wpdb;
        
        if(isset($_POST['rating']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'helpful' ))
        {
            // Get the post ID:
            $postId = intval($_POST['post_id']);
            
            // Get the visitors IP Address:
            $ip = self::getIp();
            
            // Get the provided rating:
            $rating = intval($_POST['rating']);
            
            // Get the feedback:
            $feedback = (isset($_POST['why']) && !empty($_POST['why'])) ? 
                    addslashes(strip_tags($_POST['why'])) : null;
            
            // Set the data:
            $data = array(
                'id_post' => $postId,
                'ip' => $ip,
                'rating' => $rating,
                'feedback' => $feedback
            );
            
            if(!$this->hasVisitorRated($postId))
            {
                // Insert the data into the database:
                $wpdb->insert(
                    $wpdb->prefix . 'helpful',
                    $data
                );
            }
        }
        
        // Redirect to referer, to prevent double-submitting by refreshing the page:
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }

    /**
     * Check to see if the current visitor has already rated this article.
     * 
     * @param $postId
     * @return bool
     */
    public static function hasVisitorRated($postId)
    {
        global $wpdb;
        
        // Get the visitors IP Address:
        $ip = self::getIp();
        
        // Count the number of ratings this visitor has made for this article:
        $results = $wpdb->get_results(
            sprintf(
                'SELECT COUNT(*) AS `count` FROM `%1$shelpful` WHERE `id_post` = %2$d AND `ip` = \'%3$s\';', 
                $wpdb->prefix, 
                $postId, 
                $ip
            ), OBJECT
        );
        
        return $results[0]->count > 0;
    }

    /**
     * Get the rating of a specific post
     * 
     * @param $postId
     * @return bool
     */
    public static function hasRating($postId)
    {
        global $wpdb;
        
        // Count the number of ratings this visitor has made for this article:
        $results = $wpdb->get_results(
            sprintf(
                'SELECT COUNT(*) AS `count` FROM `%1$shelpful` WHERE `id_post` = %2$d;', 
                $wpdb->prefix, 
                $postId 
            ), OBJECT
        );
        
        return $results[0]->count > 0;
    }

    /**
     * Get the rating of a specific post
     * 
     * @param $postId
     * @return float
     */
    public static function getRating($postId)
    {
        global $wpdb;
        
        // Count the number of ratings this visitor has made for this article:
        $results = $wpdb->get_results(
            sprintf(
                'SELECT AVG(`rating`) AS `rating` FROM `%1$shelpful` WHERE `id_post` = %2$d;', 
                $wpdb->prefix, 
                $postId 
            ), OBJECT
        );
        
        return $results[0]->rating;
    }
    
    /**
     * Get the visitors' IP-address
     * 
     * @return string
     */
    public static function getIp()
    {
        // Get the visitors IP Address:
        $ip = $_SERVER['REMOTE_ADDR'] ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER['HTTP_CLIENT_IP']);
        return $ip;
    }

    /**
     * Add meta boxes
     */
    public function addMetaBoxes()
    {
        add_meta_box(
            'helpful',      // The ID of the metabox
            'Post Rating',  // The title
            array($this, 'renderMetaBox'),  // The callback
            'post',         // The post-type where this metabox applies to
            'side',         // The location of the metabox,
            'low'           // The priority
        );
    }

    /**
     * Render the metabox
     * 
     * @param WP_Post $post
     */
    public function renderMetaBox($post)
    {
        if(!$this->hasRating($post->ID))
        {
            echo '<em>This post has no ratings yet</em>';
        } else {
            echo '<strong>Rating: ' . 
                    number_format($this->getRating($post->ID), 1) . ' out of 5</strong>';
            echo '<ul>';
            $ratings = $this->getAllRatings($post->ID);
            foreach($ratings as $rating)
            {
                printf('
                    <li style="border-top: 1px solid #eee;">
                        <p class="header">%1$s<span style="float: right;">%2$s</span></p>
                        <p class="feedback"><em>%3$s</em></p>
                    </li>
                ',
                $rating->ip,
                $rating->rating,
                $rating->feedback);
            }
            echo '</ul>';
        }
    }

    /**
     * Get all the ratings according to the post ID
     * 
     * @param int $postId
     * @return array
     */
    public function getAllRatings($postId)
    {
        global $wpdb;
        
        // Count the number of ratings this visitor has made for this article:
        $results = $wpdb->get_results(
            sprintf(
                'SELECT * FROM `%1$shelpful` WHERE `id_post` = %2$d;', 
                $wpdb->prefix, 
                $postId 
            ), OBJECT
        );
        
        return $results;        
    }
    
    /**
     * Add a custom column
     * 
     * @param $defaults
     * @return mixed
     */
    public function addCustomColumn($defaults)
    {
        $defaults['rating'] = 'Rating';
        return $defaults;
    }
    
    /**
     * Populate the custom column
     * 
     * @param $columnName
     * @param $postId
     */
    public function populateCustomColumn($columnName, $postId)
    {
        if($columnName == 'rating')
        {
            if($this->hasRating($postId))
            {
                echo number_format($this->getRating($postId), 1);
            } else {
                echo '<em style="opacity: 0.25">N/A</em>';                
            }
        }
    }

    /**
     * Make custom column sortable
     * 
     * @param $columns
     * @return mixed
     */
    public function addSortableColumn($columns)
    {
        $columns['rating'] = 'rating';
        return $columns;
    }

    /**
     * Sort by joining a table
     * 
     * @param array $pieces
     * @return array
     */
    public function postsClauses($pieces)
    {
        global $wpdb;
        if(is_admin() && isset($_GET['orderby']) && $_GET['orderby'] == 'rating')
        {
            // Join table:
            $pieces['join'] = sprintf('LEFT JOIN `%1$shelpful` hf ON hf.id_post = wp_posts.ID', $wpdb->prefix);
            // Group:
            $pieces['groupby'] = 'id_post';            
            // Order by:
            $direction = (isset($_GET['order']) && $_GET['order'] == 'asc') ? 'ASC' : 'DESC';
            $pieces['orderby'] = sprintf('`rating` %1$s', $direction);
            // Where:
            $pieces['where'] .= ' AND `rating` IS NOT NULL';
            // Fields:
            $pieces['fields'] = sprintf('%1$sposts.*, AVG(rating) as `rating`', $wpdb->prefix);
        }
        return $pieces;
    }
}

// Instantiate on load:
new Helpful();
