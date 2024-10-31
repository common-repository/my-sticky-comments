<?php
/*
Plugin Name: My Sticky Comments
Plugin URI: http://www.wordpress.org/plugins/my-sticky-comments/
Version: 0.4
Description: A sticky comments feature with settings.

Author: <a href="http://profiles.wordpress.org/kidsguide">Websiteguy</a>
Author URL: http://profiles.wordpress.org/kidsguide
Compatible with WordPress 2.4+.
*/
/*
Copyright 2013 Websiteguy (email : mpsparrow@cogeco.ca)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
 
 define ('SC_PATH', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)));
 
// This is the default wordpress comments_template() function, with a few added features to the query's.
function sc_comments_template($file = '/comments.php', $separate_comments = false ) {
	global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_ID, $user_identity, $overridden_cpage;

	$sc = get_option('sc_options');
	
	$sort_order = $sc['sc_sort_order'];
	
	if ( ! in_array( strtolower( $sort_order ), array( 'asc', 'desc' ) ) ) $sort_order = 'asc';

	if ( !(is_single() || is_page() || $withcomments) || empty($post) )
		return;

	if ( empty($file) )
		$file = '/comments.php';

	$req = get_option('require_name_email');

	$commenter = wp_get_current_commenter();

	$comment_author = $commenter['comment_author']; // Escaped by sanitize_comment_cookies()


	$comment_author_email = $commenter['comment_author_email'];  // Escaped by sanitize_comment_cookies()

	$comment_author_url = esc_url($commenter['comment_author_url']);

	if ( $user_ID) {
	// Modified query's so the sticky posts are shown first
		$query = $wpdb->prepare("SELECT * FROM $wpdb->comments c LEFT JOIN $wpdb->commentmeta cm ON ( c.comment_ID = cm.comment_id AND cm.meta_key = 'sticky' ) WHERE c.comment_post_ID = %d AND (c.comment_approved = '1' OR ( c.user_id = %d AND c.comment_approved = '0' ) )  ORDER BY cm.meta_value DESC, c.comment_date_gmt ".$sort_order, $post->ID, $user_ID);		
	} else if ( empty($comment_author) ) {
		$query = $wpdb->prepare("SELECT * FROM $wpdb->comments c LEFT JOIN $wpdb->commentmeta cm ON ( c.comment_ID = cm.comment_id AND cm.meta_key = 'sticky' ) WHERE c.comment_post_ID = %d AND c.comment_approved = '1' ORDER BY cm.meta_value DESC, c.comment_date_gmt ".$sort_order, $post->ID, $user_ID);
	} else {
		$query = $wpdb->prepare("SELECT * FROM $wpdb->comments c LEFT JOIN $wpdb->commentmeta cm ON ( c.comment_ID = cm.comment_id AND cm.meta_key = 'sticky' ) WHERE c.comment_post_ID = %d AND ( c.comment_approved = '1' OR ( c.comment_author = %s AND c.comment_author_email = %s AND c.comment_approved = '0' ) ) ORDER BY ORDER BY cm.meta_value DESC, c.comment_date_gmt ".$sort_order, $post->ID, wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email);
	}

	$comments = $wpdb->get_results($query);

	$wp_query->comments = apply_filters( 'comments_array', $comments, $post->ID );
	$comments = &$wp_query->comments;
	$wp_query->comment_count = count($wp_query->comments);
	update_comment_cache($wp_query->comments);

	if ( $separate_comments ) {
		$wp_query->comments_by_type = &separate_comments($comments);
		$comments_by_type = &$wp_query->comments_by_type;
	}

	$overridden_cpage = FALSE;
	if ( '' == get_query_var('cpage') && get_option('page_comments') ) {
		set_query_var( 'cpage', 'newest' == get_option('default_comments_page') ? get_comment_pages_count() : 1 );
		$overridden_cpage = TRUE;
	}

	if ( !defined('COMMENTS_TEMPLATE') || !COMMENTS_TEMPLATE)
		define('COMMENTS_TEMPLATE', true);

	$include = apply_filters('comments_template', STYLESHEETPATH . $file );
	if ( file_exists( $include ) )
		require( $include );
	elseif ( file_exists( TEMPLATEPATH . $file ) )
		require( TEMPLATEPATH .  $file );
	else 
		require( ABSPATH . WPINC . '/theme-compat/comments.php');
}


/* Hook to save if the comment is sticky or not */
function sc_admin_comment_save( $comment_ID ) {
	
	if ( $_POST['sticky'] ) $sticky = 1;
	
	update_comment_meta( $comment_ID, 'sticky', $sticky );
	
}

/* Function to return the Sticky text before or after the comment author's name */
function sc_comment_author_link( $author ) {
	
	$sc = get_option('sc_options');
	
	$comment_ID = get_comment_ID();
	
	$sticky = get_comment_meta( $comment_ID, "sticky", true );
	
	if ( ! $sticky ) {
		return $author;
	} else {
		
		if ( $sc['sc_notify_type'] == 'image' ) {
			$sticky_text = '<img class="sc_sticky_image" src="'. SC_PATH . 'images/' . $sc['sc_notify_image'] . '" alt="Sticky" title="Sticky" />';
		} else {
			$sticky_text = '<span class="sc_sticky_text">' . $sc['sc_notify_text'] . '</span>';
		}		
		
		if ( $sc['sc_notify_position'] ==  'left' ) { 
			return $sticky_text . ' ' . $author;
		} else {
			return $author . ' ' . $sticky_text;
		}
	}

}


/* Hook for the admin that is editing comments */
function sc_admin_comment_edit( $post ){
	if ( ! is_admin() ) return;
	 
	 $sticky = get_comment_meta($post->comment_ID,"sticky",true);
	 
	?> 
    <div class="sc_container" id="sc_container">
	    <label class="sc_sticky_label">
        	<input type="checkbox" name="sticky" <?php echo $sticky ? 'checked="checked"' : ''; ?> id="sticky" />Sticky
        </label>
    </div>
	<?php
}

function sc_enqueue_script() {
	
	$sc_js_path = SC_PATH . 'js/scjs.js';
	
	wp_register_script('sc_js', $sc_js_path, array('jquery') );

	wp_enqueue_script( 'sc_js' );
	
}

function sc_enqueue_style() {

	$sc_css_path = SC_PATH . 'css/sccss.css';
	
	wp_register_style( 'sc_css', $sc_css_path );

	wp_enqueue_style( 'sc_css' );
	
}

function sc_get_directory_images( $dir ) {

	$images = array();

	if ( is_dir($dir) ) {
		
		if ( $dh = opendir($dir) ) {
			
			while ( ($file = readdir($dh)) !== false ) {
				
				if ( filetype($dir . $file) == 'file'  ) {
					
					$images[] = $file;
					
				}
				
			}
			
			closedir( $dh );
			
		}

	}
	
	return $images;
	
}

/* Options page */
function sc_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have permissions to access this page.') );
	}	
	
	if ($_REQUEST['action'] == 'update' ):

			if ( !empty($_POST) ):

				foreach($_POST as $key => $value):

					if ( strpos($key, 'sc_') !== false ):
					
						$sc[$key] = esc_attr($value);
						
					endif;
					
				endforeach;
				
				update_option("sc_options", $sc);
				
			endif;
			
	endif;	
	
	$sc = get_option( 'sc_options' );
	
	$dir = realpath(dirname(__FILE__)) . '/images/';
	
	$images = sc_get_directory_images($dir);	
	
	?>

<div class="wrap">
<div class="icon32" id="icon-options-general"><br></div>
<h2>My Sticky Comments Settings</h2>
<tr>

<span style="text-decoration: underline;"><h3>Fill Out the Settings Form Below</h3></span>
    
    <form action="" method="post">

		<input type="hidden" name="action" value="update" />
        
		<table cellpadding="0" class="options_table" cellspacing="20">   
    
    	<tr>
        	<td>
            	<h3>Stick Comment Order:</h3>
            </td>
        	<td>
                <input type="radio" name="sc_sort_order" value="desc" <?php if ( $sc['sc_sort_order'] == 'desc' ) echo 'checked="checked"'; ?> /> <strong>Wordpress Default</strong> <small>This will show use the WordPress settings default.</small><br />
                <input type="radio" name="sc_sort_order" value="misc" <?php if ( $sc['sc_sort_order'] == 'misc' ) echo 'checked="checked"'; ?> /> <strong>Newest First</strong> <small>This will show the newest sticky comment first.</small><br />
                <input type="radio" name="sc_sort_order" value="asc" <?php if ( $sc['sc_sort_order'] == 'asc' ) echo 'checked="checked"'; ?> /> <strong>Oldest First</strong> <small>This will show the oldest sticky comment first.</small>
            </td>            
        </tr>  
     
    	<tr>
        	<td>
            	<br /><h3>Stick Comment Picture or Text:&nbsp;</h3> 
            </td>
        	<td>
          <br />
          <input type="radio" name="sc_notify_type" id="sc_notify_type" value="display none" <?php if ( $sc['sc_notify_type'] == 'display none' ) echo 'checked="checked"'; ?> /> <strong>None</strong> <small>This will show no image or text.</small> <br />
         	<input type="radio" name="sc_notify_type" id="sc_notify_type" value="text" <?php if ( $sc['sc_notify_type'] == 'text' ) echo 'checked="checked"'; ?> /> <strong>Text</strong> <small>This will show the text that you put in the text box below</small> <br />
          <input type="radio" name="sc_notify_type" id="sc_notify_type" value="image" <?php if ( $sc['sc_notify_type'] == 'image' ) echo 'checked="checked"'; ?> /> <strong>Image</strong> <small>This will show the image that you select.</small>
            </td>            
        </tr>         
<tr <?php if ( $sc['sc_notify_type'] == 'display none' ) echo 'style="display:none;"'; ?>>
        	<td>
               </td>
     	
<tr <?php if ( $sc['sc_notify_type'] == 'text' ) echo 'style="display:none;"'; ?>>
        	<td>
           <h3>Sticky Comment Image(s):</h3><br />
           <h3>Image Information:</h3>
            </td>
<td> 
            	<?php 
					if ( empty($images) ):
					
						echo 'There is a problem. There are no images in the ' . $dir . ' folder'; 
						
					else:
					
						foreach ( $images as $image ):
							?>
								<input type="checkbox" name="sc_notify_image" id="sc_notify_image" value="<?php echo $image ?>" <?php if ( $sc['sc_notify_image'] == $image ) echo 'checked="checked"'; ?>  /> <img src="<?php echo SC_PATH . 'images/' . $image; ?>" alt="Sticky" title="Sticky" /><br />
							<?php
						endforeach;
						
					endif;
				?>
                <br /><small><strong>Image(s) location:</strong> <?php echo SC_PATH; ?>images/ <br /> <strong>You can upload more images in the image file and they will show here.</strong></small>
                
            </td>            
        </tr>      
        
		<?php 

	

		?>        
        
    	<tr <?php if ( $sc['sc_notify_type'] == 'image' ) echo 'style="display:none;"'; ?>>
        	<td>
            	<h3>Sticky Comment Text:</h3> 
            </td>
        	<td>
				<input type="text" name="sc_notify_text" value="<?php echo $sc['sc_notify_text']; ?>" />
            </td>            
        </tr>          
        
    	<tr>
        	<td>

	<h3>Alignment:<h3> 


            </td>
        	<td>
            	<br /><input type="radio" name="sc_notify_position" value="left" <?php if ( $sc['sc_notify_position'] == 'left' ) echo 'checked="checked"'; ?> /> <strong>Left</strong> <small>This will align the image or text above to the left.</small><br />
                <input type="radio" name="sc_notify_position" value="right" <?php if ( $sc['sc_notify_position'] == 'right' ) echo 'checked="checked"'; ?> /> <strong>Right</strong> <small>This will align the image or text above to the right.</small><br /> <br />
               
            </td>            
        </tr>     
 
<tr>
<td colspan="2"><input type="submit" class="button-primary" value="Save Changes" />
</tr>
<tr>
</tr>        
     </table>
    </form>          
<tr>
<td>
<span style="text-decoration: underline;"><h3>Information For this Plugin</h3></span>
</td>
</tr>
<tr>
<td>
<h4>Thank You for Downloading This Plugin!</h4>
</td>
</tr>
<tr>
<td>
<h4>This Plugin Is Made By <a href="http://profiles.wordpress.org/kidsguide/">Websiteguy</a></h4>
</td>
</tr>
<tr>
<td>
<h4>Please Rate My Plugin <a href="http://wordpress.org/plugins/my-sticky-comments/">My Sticky Comments</a>!</h4>
</td>
</tr>
<tr>           
    
    <?php
	
}

function sc_register_settings() {
	register_setting( 'sc-settings-group', 'sc_options' );
}

function sc_options_submenu(){
	add_comments_page('Sticky Comments options', 'Sticky Comment Settings', 'manage_options', 'sc_options', 'sc_options_page');
}


add_action('admin_init',              'sc_enqueue_style');
add_action('init',                    'sc_enqueue_style');
add_action('admin_init',              'sc_register_settings' );
add_action('admin_enqueue_scripts',   'sc_enqueue_script');

add_action('add_meta_boxes_comment',  'sc_admin_comment_edit');
add_action('edit_comment',            'sc_admin_comment_save');
add_action('admin_menu',              'sc_options_submenu');

add_filter('get_comment_author_link', 'sc_comment_author_link');

/* 
 * Uninstall the plugin 
 * Cleaning up the sc_options variable and the commentsmeta information.
 */
if ( function_exists('register_uninstall_hook') )
    register_uninstall_hook(__FILE__, 'sc_uninstall');
 
function sc_uninstall(){
	
	delete_option ( 'sc_options' );
	
	global $wpdb;
	
	$query = 'DELETE FROM $wpdb->commentmeta WHERE meta_key = "sticky"';
	
	$wpdb->query($query);
	
}

/*  Activate the plug-in hook
 * Registers the default values for the settings
 */
 
if ( function_exists('register_activation_hook') ) 
	register_activation_hook( __FILE__, 'sc_activate' );


function sc_activate() {
	
	$sc = array(
	  'sc_sort_order'      => 'desc',
	  'sc_notify_type'     => 'text',
  	  'sc_notify_text'     => '',
  	  'sc_notify_position' => 'left'
	);
	
	sc_register_settings();
	
	update_option( 'sc_options', $sc );
	
}