<?php
/*
Plugin Name: WP User Role Register Multisite
Plugin URI: https://github.com/mikiamomik/wp-user-role-register-multisite/
Description: Wordpress Plugin that allows you to select the user roles for every site in a multisite Wordpress project.
Author: Bernardo Picaro
Version: 1.0
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
if ( is_multisite() ) { 
	add_action('network_user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('network_user_new_created_user', 'wurrm_registration_save', 200000, 1 );

	add_action('user_new_form', 'wurrm_form_multisite',10000,1);
	add_action('user_register', 'wurrm_registration_save', 200000, 1 );
}
