<?php
class wp_taxonomy_terms_sync_fields {
  
  public $post_types;
  public $default_options;
  
  	function __construct() {
  		add_action( 'init', array($this,'add_wp_taxonomy_terms_sync_fields'));
  	}
  
  	function add_wp_taxonomy_terms_sync_fields(){
  		global $__wp_post_export;
  		$customposts = $customposts = $__wp_post_export->__get_unique_custom_posts();;
		//p_rr( $customposts );
	  	foreach ($customposts as $post_type) {
	  		$taxonomies = get_object_taxonomies( $post_type , 'objects');
	  		//p_rr( $taxonomies );
	  		foreach ($taxonomies as $taxonomy_slug => $taxonomy) {
	  			add_action( $taxonomy_slug.'_add_form_fields', array($this,'extra_taxonomy_fields'), 10, 2 );
				add_action( $taxonomy_slug.'_edit_form_fields', array($this,'extra_taxonomy_fields_table'), 10, 2);
				add_action(	'created_'.$taxonomy_slug,array($this,'save_extra_taxonomy_fileds'), 10, 2);
				add_action( 'edited_'.$taxonomy_slug, array($this,'save_extra_taxonomy_fileds'), 10, 2);
	  		}
		}
  	}
  	function extra_taxonomy_fields_table( $tag ) {
		$this->extra_taxonomy_fields( $tag , $showTable = true );
	}

	function extra_taxonomy_fields( $tag , $showTable = false ) {    //check for existing featured ID
		$taxonomy = $_REQUEST['taxonomy'];
		$checked = '';
		if ( is_object( $tag )) {
			$t_id = $tag->term_id;
			$option_name = $taxonomy."_".$t_id;
		    if ( !empty($t_id) ) {
		    	$checked = get_option(  $option_name);
		    }
		}
		if ( $showTable) {
	    	?>
	    	<tr class="form-field">
				<th scope="row" valign="top"><label for="extra4"><?php _e('Sync Term Using WP Post Export'); ?></label></th>
				<td>
					<input type="checkbox" name="sync_term" id="sync_term" value="1" <?php echo $checked!='-1' ? 'checked=checked' : ''; ?>  />
           			 <p style="font-style:italic; color:#999; display:inline-block; margin-top:5px;"><em>uncheck this chechbox if you don't want to sync this post</em></p>
		        </td>
			</tr>
	    	<?php
	    } else {
		?>

			<div class="form-field form-required">
				<label for="sync_term"><?php _e('Sync Term Using WP Post Export'); ?></label>
				<input type="checkbox" name="sync_term" id="sync_term" value="1" <?php echo $checked!='-1' ? 'checked=checked' : ''; ?>  /> 
				<p style="font-style:italic; color:#999; display:inline-block; margin-top:5px;"><em>uncheck this chechbox if you don't want to sync this term</em></p>
			</div>

	    <?php }
	}
	function save_extra_taxonomy_fileds( $term_id ) {
		//$taxonomy = 'newsevents';
		//p_rr( $_REQUEST );
		//exit;

		$taxonomy = $_REQUEST['taxonomy'];
		
		$t_id = $term_id;
	    $optin_name = $taxonomy."_".$t_id;
		if( isset( $_POST['sync_term'] ) ) {
			update_option( $optin_name, '1' );
		} else {
			update_option( $optin_name, '-1' );
		}
	}

	function get_data(){
	    global $post;
	    return get_post_meta($post->ID, 'sync_post', true);
	}
}
$wp_taxonomy_terms_sync_fields = new wp_taxonomy_terms_sync_fields;