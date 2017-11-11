<?php
/*
Plugin Name: WP Post Export
Plugin URI: techesthete.net
Description: Export the post instantly to your clone website
Version: 1.3
Author: TechEsthete
Author URI: techesthte.net
*/


if (!class_exists('wp_post_export')) {
    class wp_post_export
    {

        public $plugin_dir;
        public $plugin_url;
        public $option_key;
        private $extender = array();
        public $api_settings = array();

        function __construct()
        {
            global $wpdb;


            register_activation_hook(__FILE__, array($this, '__activate'));
            $this->plugin_dir = dirname(__FILE__);
            $this->plugin_url = plugins_url('', __FILE__);

            //include required database files
            require_once($this->plugin_dir . '/database/TE_database.php');

            add_action('wp_enqueue_scripts', array($this, '__enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, '__admin_scripts'));
            // define( 'WPSEO_FILE', __FILE__ ); for seo yoast check
            add_action('save_post', array($this, '__save_postdata'), 999999);
            //add_action('admin_footer', array($this,'__save_instant_post'));
			//jschen remark, it will cause post save twice
//            add_filter('redirect_post_location', array($this, '__save_instant_post'), 10, 2);
            add_action('admin_menu', array($this, '__admin_menu'));

            add_action('delete_post', array($this, '__deleted_post'));


            //include the metabox
            require_once('inc/metabox.php');

            //include the Taxonomy
            require_once('inc/taxonomysync.php');

            //add_action ('wp_update_nav_menu', array($this,'__creat_update_menu'), 10, 2);
            //add_action ('wp_create_nav_menu', array($this,'__creat_update_menu'), 10, 2);
        }

        function  __deleted_post($pid)
        {
            global $wpdb;

            $post_id = @$_REQUEST['post'];
            if ($post_id) {
                $remote_post_id = get_post_meta($post_id, 'remote_post_id', true);
                if ($remote_post_id) {
                    $post = get_post($post_id);
                    $db_websites = new db_websites;
                    if ($db_websites->count_websites() > 0) {
                        $websites = $db_websites->get_websites();
                        foreach ($websites as $key => $website) {
                            $url = $website->url;
                            if (!empty($website->post_types)) {
                                $post_types = explode(',', $website->post_types);
                                if (in_array($post->post_type, $post_types)) {
                                    $pass = $website->passkey;
                                    if (!empty($url) && !empty($pass)) {


                                        $remote_post_ids = get_post_meta($post_id, 'remote_post_id', true);
                                        if ($remote_post_ids) {
                                            if (!empty($remote_post_ids[$this->__get_key($url)])) {
                                                $body['remote_post_id'] = $remote_post_ids[$this->__get_key($url)];
                                                $body['cutomaction'] = 'delete';
                                                $body['passkey'] = $pass;
                                                $response = $this->sent_request($url, $body);

                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

        }

        function __save_instant_post($location, $post_id)
        {
            global $post;
            $this->__save_postdata($post_id);
            return $location;
        }

        function __get_post_types($builtin = true, $format = true)
        {

            $args = array(
                '_builtin' => false
            );
            $output = 'objects';
            $operator = 'or';
            $post_types = get_post_types($args, $output, $operator);

            $posts_arr = array();
            if ($builtin) {
                if ($format) {
                    $posts_arr['post'] = 'Posts (post)';
                    $posts_arr['page'] = 'Pages (page)';
                } else {
                    $posts_arr['post'] = 'post';
                    $posts_arr['page'] = 'page';
                }

            }

            foreach ($post_types as $slug => $obj) {
                if ($format) {
                    $posts_arr[$slug] = $obj->label . ' (' . $slug . ')';
                } else {
                    $posts_arr[$slug] = $obj->slug;
                }
            }

            return $posts_arr;
        }


        function __creat_update_menu($menu_id, $menu_data = NULL)
        {


            //only sync selected post
            $urls = array();
            foreach ($urls as $key => $url) {
                if (in_array('nav_menu_item', $settings['website']['customposts'][$key])) {
                    if (!empty($url)) {
                        $this->sync_menu_with_site($url, $menu_id, $menu_data);
                    }
                }
            }
            exit;
        }

        function sync_menu_with_site($url, $menu_id, $menu_data)
        {

            $body['menu_id'] = $menu_id;
            $body['menu_data'] = $menu_data;
            $body['post_type'] = 'nav_menu_item';
            $response = $this->sent_request($url, $body);
            //p_rr( $body );
            //p_rr( $response['body'] );
            //exit;

        }

        function __enqueue_scripts()
        {

            //wp_enqueue_script( 'share-post', $this->plugin_url.'/assets/js/share-post.js',array( 'jquery' ) );
        }

        function __admin_menu()
        {
            $page_hook_suffix = add_menu_page('WP Post Export', 'WP Post Export', 'manage_options', '__export_settings', array(&$this, '__export_settings'), $this->plugin_url . '/assets/images/menu-icon.png');

            /* load css js only specific page of wordpress*/
            //add_action('admin_print_scripts-' . $page_hook_suffix, array($this, '__admin_scripts') );
        }


        function is_empty($var)
        {

            return !empty($var);
        }

        function __get_unique_custom_posts()
        {
            $db_websites = new db_websites;
            $customposts = array();
            if ($db_websites->count_websites() > 0) {
                $websites = $db_websites->get_websites();
                foreach ($websites as $key => $website) {
                    $post_types = $website->post_types;
                    if (!empty($post_types)) {
                        $exploded = explode(",", $post_types);
                        foreach ($exploded as $key => $value) {
                            $customposts[] = $value;
                        }
                    }
                }
                $customposts = array_unique($customposts);
            }
            return $customposts;
        }

        function __refreshproducts()
        {
            set_time_limit(0);
            ?>
            <div id="message" class="updated te-message">
                <?php
                if (ob_get_level() == 0) {
                    ob_start();
                }
                //get all the post type we want to sync
                $db_websites = new db_websites;
                if ($db_websites->count_websites() > 0) {
                    //let unique them
                    $customposts = $this->__get_unique_custom_posts();
                    if ($customposts) {
                        foreach ($customposts as $key => $value) {

                            $args = array(
                                'posts_per_page' => -1,
                                'post_type' => $value,
                                'post_status' => 'publish',
                                'suppress_filters' => true
                            );
                            $posts_array = get_posts($args);

                            foreach ($posts_array as $post) {
                                echo $this->__save_postdata($post->ID);
                                ob_flush();
                                flush();
                            }
                        }
                    }
                } else {
                    ?>
                    <p>Please add a website to sync.</p>
                <?php
                }
                ?>
            </div>
            <?php
            ob_end_flush();

        }

        function __export_settings()
        {
            global $wpdb;
            $notification = null;
            $db_websites = new db_websites;
            if ($_POST && isset($_POST['save_settings'])) {
                $websiteurl = @$_POST['websiteurl'];
                $passkey = @$_POST['websitepass'];
                $post_types = @$_POST['websitecustomposts'];
                if (!empty($post_types)) {
                    $post_types = implode(',', $post_types);
                }
                $db_websites->save($websiteurl, $passkey, $post_types);
                $notification = '<div id="message" class="te-message updated"><p>Website Added Successfully</p></div>';
            }
            if (isset($_REQUEST['remove'])) {
                $websiteid = @$_REQUEST['remove'];
                if ($websiteid != '') {
                    $db_websites->delete_website($websiteid);
                    $notification = '<div id="message" class="te-message updated"><p>Website removed Successfully</p></div>';
                }


            }

            if (isset($_REQUEST['editwebsite'])) {

                $websiteid = @$_REQUEST['editwebsite'];
                if ($websiteid != '') {
                    $websiteurl = @$_POST['websiteurl'];
                    $passkey = @$_POST['websitepass'];
                    $post_type = @$_POST['websitecustomposts'];
                    $post_types = @$_POST['websitecustomposts'];
                    if (!empty($post_types)) {
                        $post_types = implode(',', $post_types);
                    }

                    $db_websites->update($websiteid, $websiteurl, $passkey, $post_types);
                    $notification = '<div id="message" class="te-message updated"><p>Website Updated Successfully</p></div>';
                }
            }
            if (isset($_REQUEST['refresh_posts'])) {
                $this->__refreshproducts();
            }


            $website = '';
            $index = '';
            if (isset($_REQUEST['edit'])) {
                $index = @$_REQUEST['edit'];
                $website = $db_websites->get_website($index);
                $post_types = array();
                if (!empty($website->post_types)) {
                    $post_types = explode(',', $website->post_types);
                }
            }
            ?>
            <?php echo $notification; ?>
            <div class="te-websites-wrap">
                <h4 class="te-title-bar">WP Post Export</h4>
                <?php screen_icon('options-general'); ?>


                <div class="te-panel-box">

                    <form id="addwebsiteform" action="" method="post">

                        <div class="top-btn-div" <?php echo $index !== '' ? 'style="display:none;"' : '';?>>
                            <a href="" class="te-large-btn add-new btn-primary add-new-web-btn">Add New Website</a>
                        </div>
                        <div class="add-new-website" <?php echo $index !== '' ? '' : 'style="display:none;"';?>>
                            <h4 class="te-title-bar"><?php echo $index !== '' ? 'Update Website' : 'Add New Website';?>
                                <a href="<?php echo admin_url("admin.php?page=__export_settings");?>"
                                   id="cancel-new-website-btn"></a></h4>

                            <div class="te-panel-box">
                                <?php
                                if ($index != '') {
                                    ?>
                                    <input type="hidden" name="editwebsite" value="<?php echo $index; ?>">
                                <?php
                                }
                                ?>
                                <div class="te-form-control">
                                    <label class="te-label">Website URL:</label>
                                    <input placeholder="Enter Website URL" id="websiteurl" name="websiteurl" type="text"
                                           value="<?php echo $index != '' ? @$website->url : '';?>">
                                    <label class="te-label">Pass Key:</label>
                                    <input placeholder="Enter Pass Key" id="websitepass" name="websitepass" type="text"
                                           value="<?php echo $index != '' ? @$website->passkey : '';?>">
                                </div>
                                <div>
                                    <label class="te-label">Select Post Types:</label>
                                    <select name="websitecustomposts[]" id='websitecustomposts' multiple='multiple'>
                                        <optgroup label='Default Post Type'>
                                            <option
                                                value='page' <?php echo $index != '' && in_array($post_type = 'page', $post_types) ? 'selected' : '';?>>
                                                Pages (page)
                                            </option>
                                            <option
                                                value='post' <?php echo $index != '' && in_array($post_type = 'post', $post_types) ? 'selected' : '';?>>
                                                Posts (post)
                                            </option>
                                            <?php /* ?><option value='nav_menu_item' <?php echo in_array( $post_type='nav_menu_item' , $api_settings['customposts'] ) ? 'selected' : '';?>>Menu</option><?php */ ?>
                                        </optgroup>
                                        <optgroup label='Custom Post Type'>
                                            <?php
                                            $websitepost_types = $this->__get_post_types(false);
                                            foreach ($websitepost_types as $key => $post_type) {
                                                $selected = '';
                                                if ($index != '' && in_array($key, $post_types)) {
                                                    $selected = 'selected';
                                                }
                                                ?>

                                                <option
                                                    value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo ucfirst($post_type); ?></option>
                                            <?php
                                            }
                                            ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <input type="submit"
                                       name="<?php echo $index !== '' ? 'update_settings' : 'save_settings';?>"
                                       class="save-website-btn"
                                       value="<?php echo $index !== '' ? 'Update Website' : 'Save Website';?>"/>
                            </div>
                        </div>
                    </form>
                    <div style="clear:both; height:10px;"></div>
                    <?php echo $this->__websites_table_view();?>
                    <hr/>
                    <form id="syncallposts" action="" method="post" style="margin-top:15px;">
                        <div class="te-form-control">
                            <input type="submit" name="refresh_posts" class="add-new btn-primary te-large-btn"
                                   value="Sync All Websites"
                                   style="width:auto; cursor:pointer; margin:0px 0px 5px 0px; padding:10px 30px;"/>
                            <br/>
                            <label class="te-label" style="font-size:12px; font-style:italic; color:#999;">Clicking the
                                <strong>"Sync All Website"</strong> button will start syncing the selected posts types
                                for all above websites.</label>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        }

        function __websites_table_view()
        {

            ob_start();
            $db_websites = new db_websites;
            if ($db_websites->count_websites() > 0) {
                $websites = $db_websites->get_websites();
                ?>
                <div class="ed_loading" id="contactlist_table_loading" style="display:none"></div>
                <table cellspacing="0" class="table table-striped table-bordered dataTable" id="website_table">

                    <thead>
                    <tr>
                        <th scope="col" class="manage-column"><span>URL</span></th>
                        <th scope="col" class="manage-column"><span>Pass Key</span></th>
                        <th scope="col" class="manage-column"><span>Post Types</span></th>
                        <th scope="col" class="manage-column" width="150">Action</th>

                    </tr>
                    </thead>

                    <tbody role="alert" aria-live="polite" aria-relevant="all">

                    <?php foreach ($websites as $key => $website) {
                        ?>
                        <tr>
                            <td><?php echo $website->url; ?></td>
                            <td><?php echo $website->passkey; ?></td>
                            <td><?php echo $website->post_types; ?></td>
                            <td>
                                <div class="te-btn-groups">
                                    <form method="post">
                                        <input type="hidden" name="edit" value="<?php echo $website->id; ?>">
                                        <a href="javascript:void(0)" onclick="jQuery(this).closest('form').submit()"
                                           class="add-new btn-warning">Edit</a>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="remove" value="<?php echo $website->id; ?>">
                                        <a style="color:red" href="javascript:void(0)"
                                           onclick="jQuery(this).closest('form').submit()" class="add-new btn-danger">Remove</a>
                                    </form>
                                </div>


                            </td>
                        </tr>
                    <?php } ?>

                    </tbody>
                </table>

                <div style="clear:both; height:7px;"></div>
            <?php } else {
                ?>
                <div class="error below-h2">
                    <p>No Website Found.</p>
                </div>
            <?php
            } ?>
            <?php
            return ob_get_clean();
        }


        function __admin_scripts()
        {
            wp_enqueue_script('multi-select', $this->plugin_url . '/assets/js/jquery.multi-select.js', array('jquery'));
            wp_enqueue_script('export-admin-script', $this->plugin_url . '/assets/js/admin-script.js', array('multi-select'));

            wp_enqueue_style('multi-select', $this->plugin_url . '/assets/css/multi-select.css');
        }

        function __scripts()
        {
        }

        function __styles()
        {
        }

        function __dashboard()
        {
        }

        function __activate()
        {

            global $wpdb;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $table_name = $wpdb->prefix . 'sync_websites';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
                // Create table if not exists
                $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `url` text NOT NULL,
						  `passkey` text NOT NULL,
						  `post_types` text NOT NULL,
						  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
						  PRIMARY KEY (`id`)
						) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
                $wpdb->query($sql);


            }
        }

        function _init()
        {
        }

        function __save_postdata($post_id)
        {
            // If this is just a revision, don't sync it yet
            $output = '';
            if (wp_is_post_revision($post_id)) {
                return;
            }
            $post = get_post($post_id);
            $shoudbesync = get_post_meta($post->ID, 'sync_post', true);
            if ($shoudbesync == '-1') {
                return;
            }
            //only sync selected post

            $db_websites = new db_websites;
            if ($db_websites->count_websites() > 0) {
                $websites = $db_websites->get_websites();
                foreach ($websites as $key => $website) {
                    $url = $website->url;
                    if (!empty($website->post_types)) {
                        $post_types = explode(',', $website->post_types);
                        if (in_array($post->post_type, $post_types)) {
                            $pass = $website->passkey;
                            if (!empty($url) && !empty($pass)) {
                                $ret = $this->sync_post_with_site($url, $pass, $post_id);
                                $spliturl = explode("/wp-admin", $url);
                                $mainsiteurl = $spliturl[0];
                                if ($ret === true) {
                                    //$output .= '<p><strong>"'.$post->post_title.'"</strong> is replicated on '.strstr($url, 'wp-admin', true).".</p>";
                                    $output .= '<p><strong>"' . $post->post_title . '"</strong> is replicated on ' . $mainsiteurl . ".</p>";
                                } else if ($ret === -1) {
                                    //not syn bcoz we found term in it which we don't want to sync
                                } else {
                                    //$output .= '<p><strong>"'.$post->post_title.'"</strong> cannot be replicated on '.strstr($url, 'wp-admin', true).' due to <span class="te-error-span">'.$ret."</span></p>";
                                    $output .= '<p><strong>"' . $post->post_title . '"</strong> cannot be replicated on ' . $mainsiteurl . ' due to <span class="te-error-span">' . $ret . "</span></p>";
                                }
                            }
                        }
                    }
                }
            }
            return $output;
        }


        function sync_post_with_site($url, $pass, $post_id)
        {
			$attachments = get_posts( array(
	            'post_type' => 'attachment',
	            'posts_per_page' => -1,
	            'post_parent' => $post_id
	        ) );
			$val = $this->sync_one_post($url, $pass, $post_id);
			if ($val != true) {
				return $val;
			}
			if ( $attachments ) {
	            foreach ( $attachments as $attachment )
				{
					$val = $this->sync_one_post($url, $pass, $attachment->ID);
					if ($val != true) {
						return $val;
					}
				}
			}
		}


		function sync_one_post($url, $pass, $post_id)
		{
            //get post object
            $post = get_post($post_id, ARRAY_A);
            $post_type = $post['post_type'];

						//get post meta
			$post_meta = get_post_meta($post_id);
			unset($post_meta['remote_post_id']);

			$remote_post_ids = get_post_meta($post_id, 'remote_post_id', true);
			if ($remote_post_ids) {
				if (!empty($remote_post_ids[$this->__get_key($url)])) {
					$post_meta['remote_post_id'][0] = $remote_post_ids[$this->__get_key($url)];
				}
			}
	
			if ($post_type == 'attachment') {
				$remote_post_ids = get_post_meta($post['post_parent'], 'remote_post_id', true);
				$post['remote_post_ids'] = $remote_post_ids;	
				if ($remote_post_ids) {
					if (!empty($remote_post_ids[$this->__get_key($url)])) {
						$post['post_parent'] = $remote_post_ids[$this->__get_key($url)];
					}
				}
				
			} else {
				//get custom taxonmies registered against this post type
				$taxonomies = get_object_taxonomies($post_type, 'objects');
				$post_taxonomies_cats = array();
				$post_taxonomies_tags = array();
				foreach ($taxonomies as $taxonomy_slug => $taxonomy) {
					//save post terms in the respective taxonomy index
					$terms = wp_get_post_terms($post_id, $taxonomy_slug);
					if (!is_wp_error($terms)) {
						$termstosync = array();
						foreach ($terms as $key => $term) {
							$t_id = $term->term_id;
							$option_name = $taxonomy_slug . "_" . $t_id;
							if (!empty($t_id)) {
								if (get_option($option_name) != '-1') {
									$termstosync[] = $term;
								} else {
									return -1;
								}
							}
						}
						if ($taxonomy->hierarchical == 1) {
							$post_taxonomies_cats[$taxonomy_slug] = $termstosync;
						} else {
							$post_taxonomies_tags[$taxonomy_slug] = $termstosync;
						}
					}

				}
				$body['post_taxonomies_cats'] = $post_taxonomies_cats;
	            $body['post_taxonomies_tags'] = $post_taxonomies_tags;
			}
			//remove the things we don't need
			unset($post['guid']);
			unset($post_meta['_edit_lock']);
			unset($post_meta['_edit_last']);
			unset($post_meta['_pingme']);
			unset($post_meta['_encloseme']);
			//jschen
			//	unset($post_meta['_thumbnail_id']);
			$body['post'] = $post;
			$body['post_type'] = $post_type;
			$body['passkey'] = $pass;
			$body['post_meta'] = $post_meta;
						
            //p_rr( $body );
            $response = $this->sent_request($url, $body);
            //p_rr( $response['body'] );
            //exit;
            if ($this->isJSON($response['body'])) {
                $output = json_decode($response['body'], true);
                if (empty($output['error'])) {
                    $remote_post_id = $output['remote_post_id'];
                    if (!is_array($remote_post_ids)) {
                        $remote_post_ids = array();
                    }
                    $remote_post_ids[$this->__get_key($url)] = $remote_post_id;
                    update_post_meta($post_id, 'remote_post_id', $remote_post_ids);
                    return true;
                } else {
                    return $output['error'];
                }
            } else {
                return ' inlvaid <strong>URL</strong> or <strong>Pass Key</strong>.';
            }
        }

        function sent_request($url, $body)
        {
            return wp_remote_post($url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => $body,
                'cookies' => array()
            ));
        }

        function isJSON($string)
        {
            return is_string($string) && is_object(json_decode($string)) ? true : false;
        }

        function __get_key($url)
        {
            return md5(rtrim($url, '/'));
        }

    }
}
/* --------------------- */
if (!function_exists('p_rr')) {
    function p_rr($arr)
    {
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
}


global $__wp_post_export;
if (class_exists('wp_post_export')) {
    $__wp_post_export = new wp_post_export();
}
?>
