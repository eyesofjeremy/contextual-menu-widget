<?php
/*
Plugin Name: Contextual Menu Widget
Plugin URI: https://github.com/eyesofjeremy/Contextual-Menu-Widget
Version: 0.1
Description: Add a location-based subnav menu to your sidebar. Looks for WP Custom Menus with a name based on the page's top-level parent's slug.
Author: Jeremy Carlson
Author URI: http://jeremycarlson.com/
*/



class Contextual_Menu_Widget extends WP_Widget {

  function Contextual_Menu_Widget() {
  
    $widget_ops = array('classname' => 'Contextual_Menu_Widget', 'description' => 'Add context-aware menu based on top level page. ');
    $this->WP_Widget('Contextual_Menu_Widget', 'Contextual Menu', $widget_ops);
    
  }
 
  function form($instance) {
  
    $instance = wp_parse_args((array) $instance, array( 'title' => '', 'menu' => '', 'include_parent' => 'off' ));
    $title          = $instance['title'];
    $menu           = $instance['menu'];
    $include_parent = $instance['include_parent'] ? 'on' : 'off';
?>
  <p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
  <p>If you have <a href="<?php echo admin_url(); ?>nav-menus.php">custom menus</a> built, you can use one as the basis for your contextual menu. The items listed will be the part of the menu starting with the top-level ancestor of the current page the visitor is on.</p>

<?php
    // List custom menus if they have been set up by user
    $custom_menus = get_terms( 'nav_menu', array( 'hide_empty' => true ) );
    if( ! empty( $custom_menus ) ) {
?>
  <p><label for="<?php echo $this->get_field_id('menu'); ?>">Menu Name: 
  <select  id="<?php echo $this->get_field_id('menu'); ?>" name="<?php echo $this->get_field_name('menu'); ?>">
    <option value="">Choose a menu...</option>
<?php
      foreach( $custom_menus as $menu_item ) {
        
        // check if this option has been selected   
        $selected = ( $menu_item->slug == $menu ) ? ' selected' : '';
        echo "<option value='{$menu_item->slug}'$selected>{$menu_item->name}</option>
";
      }
?>
  </select>
  </p>

  <p>
    <input class="checkbox" type="checkbox" <?php checked($instance['include_parent'], 'on'); ?> id="<?php echo $this->get_field_id('include_parent'); ?>" name="<?php echo $this->get_field_name('include_parent'); ?>" /> 
    <label for="<?php echo $this->get_field_id('include_parent'); ?>">Show Top-Level Page</label>
  </p>

<?php
    } else { // clear out menu setting if no menu exists
?>
  <p><input id="<?php echo $this->get_field_id('menu'); ?>" name="<?php echo $this->get_field_name('menu'); ?>" type="hidden" value="" />
<?php
    }
  }
 
  function update($new_instance, $old_instance) {

    $instance = $old_instance;
    foreach( $new_instance as $key => $value ) {
      $instance[$key] = $value;
    }
    $instance['include_parent'] = $new_instance['include_parent'];

    return $instance;
  }
 
  function widget($args, $instance) {
  
    extract($args, EXTR_SKIP);
 
    // make title like other widget titles.
    $title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
    
    if (!empty($title))
      echo $before_title . $title . $after_title;;
 
    // Do Your Widgety Stuff Here...
    global $post;

    // Pay respect to your great ancestors!
    // Get top-level page information
    $venerable_one = get_greatancestor( $post );
    $menu_slug = $venerable_one->post_name;

    // Get a menu if we have one setup
    if( wp_get_nav_menu_object( $menu_slug) !== FALSE ) {

        echo $before_widget;

        wp_nav_menu( array( 'menu' => $menu_slug ) );

        if(current_user_can('edit_theme_options')) {
            echo '<a class="post-edit-link" href="' . admin_url('nav-menus.php') . '?action=edit&menu=' . wp_get_nav_menu_object($menu_slug)->term_id . '">' . __('edit menu') .'</a>';
        }

        echo $after_widget;

    } else { 
      // If we don't have a specified menu, then build one from
      // part of an existing menu, if we have it.
      // TODO: make this a setting. Because we might not want a menu at all!

      // check for a backup menu to use
      $menu_backup = ! empty( $instance['menu'] ) ? $instance['menu'] : FALSE;
      
      if( $menu_backup ) {
        
        // Get a submenu
        $args = array(
          'menu'    => $menu_backup,
          'submenu' => $menu_slug,
        );

        // Include parent item if set
        $include_parent = $instance['include_parent'];
        
        if( $include_parent ) {
          $args['submenu_parent'] = $menu_slug;
        }
        
        wp_nav_menu( $args );
      }
 
    }
 
  }
    
} // end class

// This filter and below two functions come from
// http://wordpress.stackexchange.com/a/2809/21375
// modified to check for $post->post_name instead of $post->title

add_filter( 'wp_nav_menu_objects', 'submenu_limit', 10, 2 );

/*
 * submenu_limit
 * Unset items from a menu that aren't part the children of a particular page.
 */

function submenu_limit( $items, $args ) {

    if ( empty( $args->submenu ) ) {
        return $items;
    }

    $ids       = wp_filter_object_list( $items, array( 'post_name' => $args->submenu ), 'and', 'ID' );
    $parent_id = array_pop( $ids );
    $children  = submenu_get_children_ids( $parent_id, $items );

    // Add parent ID if set.
    if ( ! empty( $args->submenu_parent ) ) {
      $children[] = $parent_id;
    }

    foreach ( $items as $key => $item ) {

        if ( ! in_array( $item->ID, $children ) ) {
            unset( $items[$key] );
        }
    }

    return $items;
}

function submenu_get_children_ids( $id, $items ) {

    $ids = wp_filter_object_list( $items, array( 'menu_item_parent' => $id ), 'and', 'ID' );

    foreach ( $ids as $id ) {

        $ids = array_merge( $ids, submenu_get_children_ids( $id, $items ) );
    }

    return $ids;
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
    
    if( is_array( $items ) && ! empty( $items ) ) {
      foreach($items as $menu_item) {
        echo ' ' . $menu_item->object_id;
        if($menu_item->object_id == $id) {
          return TRUE; // found!
        }
      }
    }
    return false;
}

function get_greatancestor( $post ) {      // $post = The current post
    global $post;               // load details about this page

    if ( is_page($post) ) {

        // HT: http://cssglobe.com/post/5812/wordpress-find-pages-top-level-parent-id
        if ($post->post_parent) {
            $ancestors = get_post_ancestors( $post->ID );
            $root = count($ancestors)-1;
            $greatancestor = $ancestors[$root];
            $post_data = get_post($greatancestor);
            return $post_data;
        } else { // if page has no parent
            return $post;
        }
    }
    return false;  // we aren't at the page, and the page is not an ancestor
}

?>