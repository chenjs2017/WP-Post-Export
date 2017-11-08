<?php
class db_websites {
	var $table;
	function __construct() {
		global $wpdb;
       	$this->table = $wpdb->prefix.'sync_websites';
   	}
	function update(  $website_id , $url , $passkey , $post_types)
	{	
		global $wpdb;
		$data['url']		=	@$url;
		$data['passkey']	=	@$passkey;
		$data['post_types']	=	@$post_types;
		$where['id'] = $website_id;
		if ( $website_id ) {
			return $wpdb->update($this->table,$data,$where,array('%s','%s','%s'),array('%d'));
		}
	}	
	
	function get_websites(  )
	{
		global $wpdb;
		return $wpdb->get_results ( "SELECT * FROM ".$this->table." ");
	}
	function get_website( $website_id )
	{
		global $wpdb;
		return $wpdb->get_row ( "SELECT * FROM ".$this->table." WHERE id='$website_id' ");
	}

	function count_websites(  )
	{
		global $wpdb;
		return $wpdb->get_var ( "SELECT count(*) total FROM ".$this->table."");
	}
	
	function delete_website( $website_id )
	{
		global $wpdb;
		if ( $website_id ) {
			$wpdb->delete( $this->table, array( 'id' => $website_id ), array( '%d' ) );
		}
	}

	function save(  $url , $passkey , $post_types)
	{
		global $wpdb;
		$data['url']		=	@$url;
		$data['passkey']	=	@$passkey;
		$data['post_types']	=	@$post_types;
			
		$wpdb->insert($this->table,$data,array('%s','%s','%s'));
		return $wpdb->insert_id ? $wpdb->insert_id:false;
		
	}
}