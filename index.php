<?php
/*
Plugin Name: WP User Role Register Multisite
Plugin URI: https://github.com/mikiamomik/wp-user-role-register-multisite/
Description: Wordpress Plugin that allows you to select the user roles for every site in a multisite Wordpress project.
Author: Bernardo Picaro
Version: 1.0.1
*/

function wurrm_form_multisite($type=null) {
	if($type=="add-existing-user"){ return false; } 
	$is_network=empty($type);
	$sites=get_sites(); 
	global $wp_roles;

    $all_roles = $wp_roles->roles;
    $editable_roles = apply_filters('editable_roles', $all_roles);
    $user_roles=array();
    foreach($editable_roles as $k=>$role){ $user_roles[$k]=$role['name'];}
    ?>
	<table class="form-table">
		<tbody>
			<?php if(!$is_network) { ?>
			<tr class="form-field form-required">
				<th colspan='1000'><?= __('Other Sites') ?></th>
			</tr>
			<?php }
			foreach($sites as $site){
				if($is_network || (!$is_network && get_current_blog_id()!=$site->blog_id)) {
					?><tr class="form-field form-required"><?php
					$domain=str_replace(".robadadonne.it",null,$site->domain);
					$domain=str_replace(".rdd.it",null,$domain);
					$domain=strtoupper($domain);
					echo "<th scope=\"row\">".__("Role")." ".$domain."</th>";
					echo "<td>
						  <select name=\"wurrm_site_enabled[".$site->blog_id."]\" >";
						  	echo "<option value='0'>".__("Do not register in this site")."</option>";
						    foreach($user_roles as $k=>$role){
						    	echo "<option value=\"".$k."\" ".(($is_network && $k=='contributor') ? "selected":null).">".$role."</option>";
						    }
					echo "</select>
						  </td>";
					?></tr><?php
				}
			} ?>
		</tbody>
	</table>
	<style>
		body table.form-table td select{width:100% !important;max-width: 25em !important;}
	</style>
	<?php
}

function wurrm_registration_save( $user_id ) {
	if(is_numeric($user_id) && is_multisite() && isset($_POST['wurrm_site_enabled'])){
		foreach($_POST['wurrm_site_enabled'] as $blog_id=>$role){
			if($role){
				switch_to_blog($blog_id);
				$u = new WP_User($user_id);
				$u->add_role($role);
				restore_current_blog();
			}
		}
	}
}

function wurrm_add_user_id_column($columns) {
	if(isset($columns['blogs'])){
		unset($columns['blogs']);
	}
    $columns['sites'] = __('Roles');
    return $columns;
}
 
function wurrm_show_user_id_column_content($value, $column_name, $user_id) {
    //$user = get_userdata( $user_id );
    $sites_content=null;
	if ( 'sites' == $column_name ){
		$sites=get_sites();
		foreach($sites as $site){
			$get_users_obj = get_users(array('blog_id' => $site->blog_id,'search'  => $user_id));
			if(isset($get_users_obj[0]->roles)){
				$sites_content.="<span class=\"".$site->blog_id."\">";
				$sites_content.="<a href=\"".network_admin_url("site-info.php?id=".$site->blog_id)."\">".$site->domain."</a>";
				$sites_content.=" <small><a href=\"".network_admin_url("site-info.php?id=".$site->blog_id)."\">".strtoupper(__("Edit"))."</a></small>";
				$sites_content.="<br>";
				foreach($get_users_obj[0]->roles as $role){
					$sites_content.="<span class='wurrm_role_user'>$role</span><br>";
				}
				$sites_content.="</span><br>";
			}
			
		}
		return $sites_content;
	} 
    return $value;
}


function wurrm_add_style_user_id() {
    echo '<style>.wurrm_role_user:before{content: " - ";}</style>';
}

if ( is_multisite() ) { 
	add_action('admin_footer', 'wurrm_add_style_user_id' );
	add_filter('wpmu_users_columns', 'wurrm_add_user_id_column');
	add_action('manage_users_custom_column',  'wurrm_show_user_id_column_content', 10, 3);

	add_action('network_user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('network_user_new_created_user', 'wurrm_registration_save', 200000, 1 );

	add_action('user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('user_register', 'wurrm_registration_save', 200000, 1 );
}
