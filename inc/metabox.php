<?php
class wp_post_sync_metabox {
  
  public $post_types;
  public $default_options;
  
  function __construct() {
    add_action( 'add_meta_boxes', array($this,'add_metaboxes') );
    add_action( 'save_post', array($this,'save_metaboxes'), 1, 2 );
    
  }
  
  function add_metaboxes() {
  	global $__wp_post_export;
  	$customposts = $customposts = $__wp_post_export->__get_unique_custom_posts();;
	foreach ($customposts as $post_type) {
  		add_meta_box( 'sync-post-meta-box-id', 'Sync Post Using WP Export', array($this, 'display_meta_box'), $post_type, 'side', 'high' );
	}
  }
  
  function display_meta_box() {
  		global $post;
  		wp_nonce_field( 'wp_post_sync_metabox', 'wp_post_sync_metabox' );
  		$checked = $this->get_data();
		?>
		<style>
		
		</style>
		<div class="sync_detail_metabox">
			
		    <input type="checkbox" name="sync_post" id="sync_post" value="1" <?php echo $checked!='-1' ? 'checked=checked' : ''; ?>  /> <label for="sync_post"><strong>Sync this Post</strong></label> </br>
            
            <span style="font-style:italic; color:#999; display:inline-block; margin-top:5px;"><em>uncheck this chechbox if you don't want to sync this post</em></span>
		    
		</div>
		<?php
  }
  
  // Saving Meta Box of Sliders
  	function save_metaboxes( $post_id ) {
	    global $__wp_post_export;
	    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		$pst = get_post($post_id);
		
  		$this->post_types = $__wp_post_export->__get_post_types();
		if($pst) {
			if( !array_key_exists( $pst->post_type , $this->post_types ) ) {
				return;
			}
		} else {
			return;
		}

		if ( $_POST ) {
			if( !isset( $_POST['wp_post_sync_metabox'] ) || !wp_verify_nonce( $_POST['wp_post_sync_metabox'], 'wp_post_sync_metabox' ) ) return;
			if( isset( $_POST['sync_post'] ) ) {
				update_post_meta( $post_id, 'sync_post', '1' );
			} else {
				update_post_meta( $post_id, 'sync_post', '-1' );
			}
		}
  	}

	function get_data(){
	    global $post;
	    return get_post_meta($post->ID, 'sync_post', true);
	}
}
$wp_post_sync_metabox = new wp_post_sync_metabox;