<?php
/*
Plugin Name: Gwebpro Store Locator
Plugin URI: http://www.gwebpro.com/Services/wordpress-plugin.html
Description: Find nearest store from your current location.  Also change your current location from the map or search by entering your current address or city name or store name. You get the complete flexibility in searching the store.
Author: G web pro
Version: 1.0
Author URI: http://www.gwebpro.com
License: GPL2

Copyright 2013  G web pro (email : support@gwebpro.com)

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
class gwebproStoreLocator {
  var $meta_fields = array("list-address1","list-city","list-postcode","addi-website","addi-mobile","addi-phone","addi-fax","addi-email","list-lat","list-long");

  function gwebproStoreLocator() {
    // Register custom post types
    register_post_type('store', array(
      'labels' => array(
        'name' => __('Store Locator'), 'singular_name' => __( 'Store Locator' ),
        'add_new' => __( 'Add Store' ),
        'add_new_item' => __( 'Add New Store' ),
        'edit' => __( 'Edit' ),
        'edit_item' => __( 'Edit Store' ),
        'new_item' => __( 'New Store' ),
        'view' => __( 'View Store' ),
        'view_item' => __( 'View Store' ),
        'search_items' => __( 'Search Stores' ),
        'not_found' => __( 'No Stores found' ),
        'not_found_in_trash' => __( 'No Stores found in Trash' ),
        'parent' => __( 'Parent Store' ),
      ),
      'singular_label' => __('Store'),
      'public' => true,
      'show_ui' => true, // UI in admin panel
      '_builtin' => false, // It's a custom post type, not built in
      '_edit_link' => 'post.php?post=%d',
      'capability_type' => 'post',
      'hierarchical' => false,
	  'menu_icon' => plugins_url( 'images/logo_1.png' , __FILE__ ),
      'rewrite' => array("slug" => "store_locator"), // Permalinks
      'query_var' => "stores", // This goes to the WP_Query schema
      'supports' => array('title','editor','thumbnail')
    ));
	
    add_filter("manage_edit-store_columns", array(&$this, "edit_columns"));
    add_action("manage_posts_custom_column", array(&$this, "custom_columns"));
	add_filter('query_vars', array(&$this, 'parameter_queryvars') );

    // Register custom taxonomy

    #Businesses
    register_taxonomy("store_category", array("store"), array(
      "hierarchical" => true, 
      "label" => "Store Category", 
      "singular_label" => "Store Category", 
      "rewrite" => true,
    ));
	add_action('admin_menu', array(&$this, 'store_settings'));
    // Admin interface init
    add_action("admin_init", array(&$this, "admin_init"));

    // Insert post hook
    add_action("wp_insert_post", array(&$this, "wp_insert_post"), 10, 2);
	add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_frontend_scripts'));
  }

  function edit_columns($columns) {
    $columns = array(
      "cb" => "<input type=\"checkbox\" />",
      "title" => "Store Name",
      "city" => "City",
      "store_categories" => "Categories",
      "date" => "Date",
    );

    return $columns;
  }

  function custom_columns($column) {
    global $post;
    switch ($column) {
      case "city":
        $custom = get_post_custom();
        if(isset($custom["list-city"][0])) echo $custom["list-city"][0];
        break;
      case "store_categories":
        $speakers = get_the_terms(0, "store_category");
        $speakers_html = array();
        if(is_array($speakers)) {
          foreach ($speakers as $speaker)
          array_push($speakers_html, '<a href="' . get_term_link($speaker->slug, 'store_category') . '">' . $speaker->name . '</a>');
          echo implode($speakers_html, ", ");
        }
        break;
    }
  }

  
  // When a post is inserted or updated
  function wp_insert_post($post_id, $post = null) {
    if ($post->post_type == "store") {
      // Loop through the POST data
      foreach ($this->meta_fields as $key) {
        $value = @$_POST[$key];
        if (empty($value)) {
          delete_post_meta($post_id, $key);
          continue;
        }

        // If value is a string it should be unique
        if (!is_array($value)) {
          // Update meta
          if (!update_post_meta($post_id, $key, $value)) {
            // Or add the meta data
            add_post_meta($post_id, $key, $value);
          }
        }
        else
        {
          // If passed along is an array, we should remove all previous data
          delete_post_meta($post_id, $key);

          // Loop through the array adding new values to the post meta as different entries with the same name
          foreach ($value as $entry)
            add_post_meta($post_id, $key, $entry);
        }
      }
    }
  }

  function admin_init() {
	add_action('admin_enqueue_scripts', array(&$this, 'myposttype_admin_css'));
	add_action( 'admin_enqueue_scripts', array(&$this, 'add_admin_scripts'), 10, 1 );
	add_action('admin_head', array(&$this, 'plugin_header'));
    add_meta_box("address-meta", "Address", array(&$this, "meta_address"), "store", "normal", "low");
    add_meta_box("additional-info-meta", "Additional Information", array(&$this, "meta_additional"), "store", "normal", "low");
  }
  
  function store_settings() {
		add_menu_page('Store Settings', 'Store Settings', 'administrator', 'store_settings', array(&$this, 'store_display_settings'));
	}
	
	function store_display_settings()
	{
		$api_key = (get_option('api_key') != '') ? get_option('api_key') : '';
		$map_height = (get_option('map_height') != '') ? get_option('map_height') : '';
		$map_width = (get_option('map_width') != '') ? get_option('map_width') : '';
		$store_per_page = (get_option('store_per_page') != '') ? get_option('store_per_page') : '';
		$radius = (get_option('radius') != '') ? get_option('radius') : '';
		$html = '
		<div class="wrap">
           <form method="post" name="options" action="options.php">

            <h2>Store Settings</h2>' . wp_nonce_field('update-options') . '
            <table width="100%" cellpadding="10" class="form-table">
                <tr>
                    <td align="left" scope="row" style="width:110px">
                    	<label>Google Map API Key</label>
                    </td> 
					<td align="left"><input type="text" class="regular-text" value="' . $api_key . '" name="api_key" /></td>
                </tr>
				<tr>
                    <td align="left" scope="row" style="width:110px">
                    	<label>Map Height</label>
                    </td> 
					<td align="left"><input type="text" class="regular-text" value="' . $map_height . '" name="map_height" /></td>
                </tr>
				<tr>
                    <td align="left" scope="row" style="width:110px">
                    	<label>Map Width</label>
                    </td> 
					<td align="left"><input type="text" class="regular-text" value="' . $map_width . '" name="map_width" /></td>
                </tr>
				<tr>
                    <td align="left" scope="row" style="width:110px">
                    	<label>Store Per Page</label>
                    </td> 
					<td align="left"><input type="text" class="regular-text" value="' . $store_per_page . '" name="store_per_page" /></td>
                </tr>
				<tr>
                    <td align="left" scope="row" style="width:120px">
                    	<label>Display Radius (Km)</label>
                    </td> 
					<td align="left"><input type="text" class="regular-text" value="' . $radius . '" name="radius" /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="hidden" name="action" value="update" />  
                <input type="hidden" name="page_options" value="api_key,map_width,map_height,store_per_page,radius" /> 
                <input type="submit" name="Submit" value="Update" class="button button-primary" />
            </p>
            </form>

        </div>';
		echo $html;
	}
	
   function plugin_header() {
		global $post_type;
		?>
		<style>
		<?php if (($_GET['post_type'] == 'store') || ($post_type == 'store')) : ?>
		#icon-edit { background:transparent url('<?php echo plugins_url( 'images/logo.png' , __FILE__ );?>') no-repeat; }    
		<?php endif; ?>
			</style>
		<?php
  }
  
  function myposttype_admin_css($hook_suffix) {
		global $typenow; 
		if ($typenow=="store") {
			echo  "<link type='text/css' rel='stylesheet' href='" .plugins_url( 'css/storeAdmin.css' , __FILE__ )."' />";
		}
	}
  
  function add_admin_scripts( $hook ) {
		global $post;
		if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
			if ( 'store' === $post->post_type ) {     
				wp_enqueue_script('myscript',plugins_url( 'js/storeAdmin.js' , __FILE__ ));
			}
		}
	}

  // Admin post meta contents
  function meta_address() {
    global $post;
    $custom = get_post_custom($post->ID);
    if(isset($custom["list-address1"])) $address1 = $custom["list-address1"][0];else $address1 = '';
    if(isset($custom["list-city"])) $city = $custom["list-city"][0];else $city = '';
    if(isset($custom["list-postcode"])) $post_code = $custom["list-postcode"][0];else $post_code = '';
	if(isset($custom["list-lat"])) $lat = $custom["list-lat"][0];else $lat = '';
	if(isset($custom["list-long"])) $long = $custom["list-long"][0];else $long = '';
  ?>
    <div class="location">
        <table border="0" id="location">
            <tr><td class="location_field"><label>Address:</label></td><td class="location_input"><input class="required" name="list-address1" value="<?php echo $address1; ?>" /></td></tr>
            <tr><td class="location_field"><label>City, State, Country:</label></td><td class="location_input"><input name="list-city" class="required" value="<?php echo $city; ?>" /></td></tr>
            <tr><td class="location_field"><label>Postal Code:</label></td><td class="location_input"><input name="list-postcode" class="required" value="<?php echo $post_code; ?>" /></td></tr>
            <tr><td></td><td><input type="button" class="loading_lat_button" value="Get Co-ordinates" onclick="getLatLong();" /><span class="loading_lat"></span></td></tr>
            <tr><td></td><td><div id="map_canvas" style="width:100%;height:156px; display:none"></div></td></tr>
            <tr><td class="location_field"><label>Latituide:</label></td><td class="location_input"><input name="list-lat" class="required" value="<?php echo $lat; ?>" /></td></tr>
            <tr><td class="location_field"><label>longituide:</label></td><td class="location_input"><input name="list-long" class="required" value="<?php echo $long; ?>" /></td></tr>
        </table>
        <?php if($lat!="" && $long!=""){ ?>
        	<script type="text/javascript">placePin('<?php echo $lat; ?>','<?php echo $long; ?>');</script>
        <?php }?>
    </div>
   <?php
  }
  function meta_additional() {
    global $post;
    $custom = get_post_custom($post->ID);
    if(isset($custom["addi-website"][0])) $website = $custom["addi-website"][0];else $website = '';
    if(isset($custom["addi-phone"][0])) $phone = $custom["addi-phone"][0];else $phone = '';
    if(isset($custom["addi-mobile"][0])) $mobile = $custom["addi-mobile"][0];else $mobile = '';
    if(isset($custom["addi-fax"][0])) $fax = $custom["addi-fax"][0];else $fax = '';
    if(isset($custom["addi-email"][0])) $email = $custom["addi-email"][0];else $email = '';
	?>
	<div class="additional">
        <table border="0" id="additional">
            <tr><td class="additional_field"><label>Email:</label></td><td class="additional_input"><input name="addi-email" value="<?php echo $email; ?>" size="40"/></td></tr>
            <tr><td class="additional_field"><label>Website:</label></td><td class="additional_input"><input name="addi-website" value="<?php echo $website; ?>" size="40"/></td></tr>
            <tr><td class="additional_field"><label>Phone:</label></td><td class="additional_input"><input name="addi-phone" value="<?php echo $phone; ?>" /></td></tr>
            <tr><td class="additional_field"><label>Mobile:</label></td><td class="additional_input"><input name="addi-mobile" value="<?php echo $mobile; ?>" /></td></tr>
            <tr><td class="additional_field"><label>Fax:</label></td><td class="additional_input"><input name="addi-fax" value="<?php echo $fax; ?>" /></td></tr>
        </table>
	</div>
     <?php
  }
  
   function store_frontend() {
	  	global $store;
		include('gwebpro-store-locator-frontend.php');
   }
   
    function wp_enqueue_frontend_scripts()
    {
		if (!is_admin()) {
			wp_enqueue_style('storelocator_css_front', plugins_url( 'css/store_style.css' , __FILE__ ));
        	wp_enqueue_script('jquery');
			if(get_option('api_key')!=""){
				wp_enqueue_script('storelocator_map_js_front', 'http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false&key='.get_option('api_key'));
			}else {
				wp_enqueue_script('storelocator_map_js_front', 'http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false');
			}
			wp_enqueue_script('storelocator_js_front', plugins_url( 'js/infobox.js' , __FILE__ ));
		}
    }

  
    function parameter_queryvars( $qvars )
    {
		$qvars[] = 'lat';
		$qvars[] = 'long';
		$qvars[] = 'dest';
		$qvars[] = 'q';
		return $qvars;
    }

    function get_queryvar($varname)
    {
		global $wp_query;
		if (isset($wp_query->query_vars[$varname]))
		{
			return $wp_query->query_vars[$varname];
		}
		return NULL;
    }
	
	function location_join( $clause='' ) {
		global $wpdb;
		$clause .= " LEFT JOIN $wpdb->postmeta AS lat ON ($wpdb->posts.ID = lat.post_id) 
					 LEFT JOIN $wpdb->postmeta AS lng ON ($wpdb->posts.ID = lng.post_id)";
		
		return $clause;
	}
	
	function location_where( $where, &$wp_query )
	{
		global $wpdb;
		$where .= " AND (lng.meta_value!='' AND lng.meta_key = 'list-long')
					AND (lat.meta_value!='' AND lat.meta_key = 'list-lat')";
		return $where;
	}
	
	function title_filter( $where, &$wp_query )
	{
		global $wpdb;
		if ( $search_term = $wp_query->get( 'search_q' ) ) {
			$where .= ' AND (' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( like_escape( $search_term ) ) . '%\' OR ' . $wpdb->posts . '.post_content LIKE \'%' . esc_sql( like_escape( $search_term ) ) . '%\')';
		}
		return $where;
	}
	
	function getCurrentLatLng()
	{
		$ip_addr = $_SERVER['REMOTE_ADDR'];
		$geoplugin = unserialize( file_get_contents('http://www.geoplugin.net/php.gp?ip='.$ip_addr) );
		if ( is_numeric($geoplugin['geoplugin_latitude']) && is_numeric($geoplugin['geoplugin_longitude']) ) {
			$lat = $geoplugin['geoplugin_latitude'];
			$long = $geoplugin['geoplugin_longitude'];
		}
		return array($lat,$long);
	}
	
	function distance($lat1, $lon1, $lat2, $lon2, $unit) {

	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);
	
	  if ($unit == "K") {
		return ($miles * 1.609344);
	  } else if ($unit == "N") {
		  return ($miles * 0.8684);
		} else {
			return $miles;
		  }
	}
	
	function storeLists()
	{
		global $wpdb;
		$paged = ($this->get_queryvar('paged')) ? $this->get_queryvar('paged') : 1;
		$lat = $this->get_queryvar('lat'); 
		$long = $this->get_queryvar('long');
		$dest = $this->get_queryvar('dest');
		$q = $this->get_queryvar('q');
		if(get_option('radius'))
			$rad=floatval(get_option('radius'))*0.621371;
		else
			$rad=6.21371;
		if($lat=="" && $long=="")
		{
			$latlong=$this->getCurrentLatLng();
			$lat=$latlong[0];
			$long=$latlong[1];
		}
		
		$args = array('post_type' => 'store','posts_per_page' => -1, 'search_q' => $q);
		
		add_filter( 'posts_where', array(&$this, 'title_filter'), 10, 2 );
		add_filter( 'posts_join', array(&$this, 'location_join') );
		add_filter('posts_where', array(&$this, 'location_where'), 10, 2 );
		$loop = new WP_Query( $args );
		$sql='SELECT SQL_CALC_FOUND_ROWS 
		3956 * 2 * ASIN(SQRT( POWER(SIN(('.$lat.' -abs(lat.meta_value)) * pi()/180 / 2),2) + COS('.$lat.' * pi()/180 ) * COS( abs(lat.meta_value) *  pi()/180) * POWER(SIN(('.$long.' - lng.meta_value) *  pi()/180 / 2), 2) )) AS distance,';
		$request = str_replace("SQL_CALC_FOUND_ROWS","", $loop->request);
		$request = str_replace("SELECT", $sql, $request);
		$request = str_replace("ORDER BY", "having distance < ".$rad." ORDER BY", $request);
		//echo $request;
		$result1=$wpdb->get_results($request);
		$ids=array();
		foreach($result1 as $res)
			$ids[]=$res->ID;
		remove_filter( 'posts_where', array(&$this,'title_filter'), 10, 2 );
		remove_filter( 'posts_join', array(&$this, 'location_join') );
		remove_filter('posts_where', array(&$this, 'location_where'), 10, 2 );
		$args['post__in'] = $ids;
		if(get_option('store_per_page')=="")
			$args['posts_per_page']=5;
		else
			$args['posts_per_page']=get_option('store_per_page');
		$args['paged']= $paged;
		?>
		<ul class="content">
		<?php
			if(sizeof($result1)>0)
			{
				$loop_main = new WP_Query( $args );
				$i=0;
				while ( $loop_main->have_posts() ) : $loop_main->the_post(); 
					 $custom = get_post_custom(get_the_ID());
					if(isset($custom["addi-website"][0]) && $custom["addi-website"][0]!="http://") $website = $custom["addi-website"][0];else $website = '#';
					if(isset($custom["addi-phone"][0])) $phone = $custom["addi-phone"][0];else $phone = '';
					if(isset($custom["addi-mobile"][0])) $mobile = $custom["addi-mobile"][0];else $mobile = '';
					if(isset($custom["addi-fax"][0])) $fax = $custom["addi-fax"][0];else $fax = '';
					if(isset($custom["addi-email"][0])) $email = $custom["addi-email"][0];else $email = '';
					if(isset($custom["list-address1"])) $address1 = $custom["list-address1"][0];else $address1 = '';
					if(isset($custom["list-city"])) $city = $custom["list-city"][0];else $city = '';
					if(isset($custom["list-postcode"])) $post_code = $custom["list-postcode"][0];else $post_code = '';
					if(isset($custom["list-lat"])) $lat_p = $custom["list-lat"][0];else $lat = '';
					if(isset($custom["list-long"])) $long_p = $custom["list-long"][0];else $long = '';
				?>
				<li>
					<div class="list_box general" onclick="launchInfoWindow(<?php echo $i?>);">
                    	
						<?php 
						$url = get_the_post_thumbnail(get_the_ID(), array(150,150)); 
						$url_small = get_the_post_thumbnail(get_the_ID(), array(80,80));
						?>
                        <?php if($url!=""){?><div class="store_img"><?php echo $url;?></div><?php }?>
                        <div class="left">
                            <h3><?php echo get_the_title()?></h3>
                            <p><strong>Distance:</strong> <?php echo round($this->distance($lat, $long, $lat_p, $long_p, "K"),2) . " Kilometers";?> (Approx.)</p>
                            <div class="store_content"><?php echo get_the_content(); ?></div>
                            <?php if($email!=""){?><p><a href="mailto:<?php echo $email?>"><?php echo $email?></a></p><?php }?>
                            <p><a href="<?php echo $website?>" <?php if($website!="#"){?>target="_blank"<?php }?>><?php echo $website?></a></p>
                            <?php
                            $terms = get_the_terms( get_the_ID() , 'store_category' );
							if(is_array($terms)){
								?>
								<p><strong>Category:</strong>
								<?php
								$j=1;
								foreach ( $terms as $term ) {
									echo $term->name;
									if($j++<sizeof($terms)) echo ",";
								}
							}
                            ?>
                            </p>
                        </div>
                        <div class="right">
                            <p><strong>Phone:</strong> <?php echo $phone?></p>
                            <p><strong>Mobile:</strong> <?php echo $mobile?></p>
                            <p><strong>Fax:</strong> <?php echo $fax?></p>
                        </div>
                        <div class="clear"></div>
                    </div>
			   </li>
               <script type="text/javascript">
			   latlong.push(new Array('<?php echo $lat_p?>','<?php echo $long_p?>','<?php echo get_the_title()?>','<?php if($url_small!=""){?><div class="store_thumb"><?php echo $url_small?></div><?php }?><div class="store_wrap" <?php if($url_small==""){?>style="width:auto;"<?php }?>><h3><?php echo get_the_title()?></h3><p><?php echo $address1?>,<?php echo $city?><br/>Pin: <?php echo $post_code?></p><p><a href="javascript:;" onclick="direction(\'<?php echo $address1?>,<?php echo $city?>\');">Direction</a></p></div><div class="clear"></div>'));
			   </script>
			<?php $i++; 
			
			endwhile;
		 }?>
		</ul>
        <div class="page_navigation" id="nav_main"><?php $pages = $loop_main->max_num_pages;$this->kriesi_pagination($pages);?></div>
        <script type="text/javascript">$j('#nav_clone').html($j('#nav_main').html());</script>
        <div class="clear"></div>
        <?php if($loop_main->max_num_pages<=1){?>
            <script>$j('.page_navigation').hide();</script>
        <?php }?>
        <?php
		 if($i==0){?>
            <div class="no-result">No stores found near your current location.</div>
        <?php }
	}
  
  function kriesi_pagination($pages = '', $range = 2)
  {  
		 $showitems = ($range * 2)+1;  
	
		 global $paged;
		 if(empty($paged)) $paged = 1;
	
		 if($pages == '')
		 {
			 global $wp_query;
			 $pages = $wp_query->max_num_pages;
			 if(!$pages)
			 {
				 $pages = 1;
			 }
		 }   
	
		 if(1 != $pages)
		 {
			 echo "<div class='pagination'>";
			 if($paged > 2 && $paged > $range+1 && $showitems < $pages) echo "<a class='previous_link' href='".get_pagenum_link(1)."'>&laquo;</a>";
			 if($paged > 1 && $showitems < $pages) echo "<a class='next_link' href='".get_pagenum_link($paged - 1)."'>&lsaquo;</a>";
	
			 for ($i=1; $i <= $pages; $i++)
			 {
				 if (1 != $pages &&( !($i >= $paged+$range+1 || $i <= $paged-$range-1) || $pages <= $showitems ))
				 {
					 echo ($paged == $i)? "<a href='javascript:;' class='active_page'>".$i."</a>":"<a href='".get_pagenum_link($i)."' class='inactive' >".$i."</a>";
				 }
			 }
	
			 if ($paged < $pages && $showitems < $pages) echo "<a href='".get_pagenum_link($paged + 1)."'>&rsaquo;</a>";  
			 if ($paged < $pages-1 &&  $paged+$range-1 < $pages && $showitems < $pages) echo "<a href='".get_pagenum_link($pages)."'>&raquo;</a>";
			 echo "</div>\n";
		 }
   }
   
}

// Initiate the plugin
add_action("init", "gwebproStoreLocatorInit");
add_shortcode( 'GwebproStoreLocator', array( 'gwebproStoreLocator', 'store_frontend' ) );
function gwebproStoreLocatorInit() { 
  global $store;
  $store = new gwebproStoreLocator();
}
?>