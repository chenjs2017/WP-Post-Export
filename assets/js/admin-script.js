function is_valid_url(url) {
	 return url.match(/^(ht|f)tps?:\/\/[a-z0-9-\.]+\.[a-z]{2,4}\/?([^\s<>\#%"\,\{\}\\|\\\^\[\]`]+)?$/);
}

jQuery(document).ready(function(){
  	jQuery('#websitecustomposts').multiSelect({});
    
    jQuery('.add-new-web-btn').click(function(e) {
		e.preventDefault();
        jQuery(this).hide(200,function(){
			jQuery('.add-new-website').fadeIn(250);
		});
    });
	
	jQuery('#cancel-new-website-btn').click(function(e) {
		jQuery('.add-new-website').fadeOut(150);
    });
	
	jQuery('#addwebsiteform').submit(function(){
		if ( jQuery('#websiteurl').val() == '' ) {
			alert("Please enter url");
			jQuery('#websiteurl').focus();
			return false;
		}
		if ( !is_valid_url( jQuery('#websiteurl').val() ) ) {
			alert("Wrong url");
			jQuery('#websiteurl').focus();
			return false;
		}
		if ( jQuery('#websitepass').val() == '' ) {
			alert("Please enter pass key");
			jQuery('#websitepass').focus();
			return false;
		}
		if ( jQuery('#websitecustomposts').val() == null ) {
			alert("Please select post type");
			return false;
		}
		return true;
	});

	jQuery('#syncallposts').submit(function(){
		if ( confirm('Are you sure you want to continue?')) {
			return true;
		}
		return false;
	});

});