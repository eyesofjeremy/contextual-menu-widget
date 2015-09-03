<?php
/*
Plugin Name: Contextual Menu Widget
Plugin URI: https://github.com/eyesofjeremy/Contextual-Menu-Widget
Version: 0.1
Description: Add a location-based subnav menu to your sidebar. Looks for WP Custom Menus with a name based on the page's top-level parent's slug.
Author: Jeremy Carlson
Author URI: http://jeremycarlson.com/
*/



class Contextual_Menu_Widget extends WP_Widget
{
  function Contextual_Menu_Widget()
  {
    $widget_ops = array('classname' => 'Contextual_Menu_Widget', 'description' => 'Add context-aware menu based on top level page. ');
    $this->WP_Widget('Contextual_Menu_Widget', 'Contextual Menu', $widget_ops);
  }
 
  function form($instance)
  {
    $instance = wp_parse_args((array) $instance, array( 'title' => '' ));
    $title = $instance['title'];
?>
  <p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
<?php
  }
 
  function update($new_instance, $old_instance)
  {
    $instance = $old_instance;
    $instance['title'] = $new_instance['title'];
    return $instance;
  }
 
  function widget($args, $instance)
  {
    extract($args, EXTR_SKIP);
 
    $title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
 
    if (!empty($title))
      echo $before_title . $title . $after_title;;
 
    // Do Your Widgety Stuff Here...
    global $post;

    /*  - - - - - - - - - -
        special case: resources
        
        Ideally, we should be able to check if we are in a particular menu,
        but that seems like a lot of work.
        - - - - - - - - - - */

    if( is_in_nav_menu('resources') ) {

        $menu_slug = 'resources';
        
    } else {

        // this would be standard behavior
        $menu_slug = get_greatancestor_name($post);
    }

    // Get a menu if we have one setup
    if( wp_get_nav_menu_object( $menu_slug) !== FALSE ) {

        echo $before_widget;

        wp_nav_menu( array( 'menu' => $menu_slug ) );

        if(current_user_can('edit_theme_options')) {
            echo '<a class="post-edit-link" href="' . admin_url('nav-menus.php') . '?action=edit&menu=' . wp_get_nav_menu_object($menu_slug)->term_id . '">' . __('edit menu') .'</a>';
        }

        echo $after_widget;
    }
 
  }
    
}
add_action( 'widgets_init', create_function('', 'return register_widget("Contextual_Menu_Widget");') );

function is_in_nav_menu( $menu_slug ) {

    global $post;

    // If we are in an archive, need to handle ID differently
    if(is_archive()) {
        if(is_category()) {
            $archive = get_the_category();
        } elseif(is_tag()) {
            $archive = get_the_tags();
        }
        $id = $archive[0]->term_id;
    } else {
        $id = $post->ID;
        echo $id;
    }

    $items = wp_get_nav_menu_items($menu_slug);
    foreach($items as $menu_item) {
    echo ' ' . $menu_item->object_id;
        if($menu_item->object_id == $id) {
            return TRUE; // found!
        }
    }
    return false;
}

function get_greatancestor_name( $post ) {      // $post = The current post
    global $post;               // load details about this page

    if ( is_page($post) ) {

        // HT: http://cssglobe.com/post/5812/wordpress-find-pages-top-level-parent-id
        if ($post->post_parent)	{
            $ancestors = get_post_ancestors( $post->ID );
            $root = count($ancestors)-1;
            $greatancestor = $ancestors[$root];
            $post_data = get_post($greatancestor);
            return $post_data->post_name;
        } else { // if page has no parent
            return $post->post_name;
        }
    }
    return false;  // we aren't at the page, and the page is not an ancestor
}

?>