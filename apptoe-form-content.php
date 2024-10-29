<?php
	if( ! defined( 'ABSPATH' ) )
		exit;
	if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') )
		return;
	
	if( $_SERVER['REQUEST_METHOD']=="POST" && isset($_POST['apptoe_submit_btn'] ) )
	{
		if( ! empty( $_POST ) && check_admin_referer( basename(__FILE__), 'apptoe_form_nonce' ) )
		{
			$hasError=false;
			if( isset ( $_POST['secretkey']))
			{
				$secret_key=sanitize_text_field($_POST['secretkey']);
			}
			if( isset ( $_POST['pagetitle']))
			{
				$page_title=sanitize_text_field($_POST['pagetitle']);
			}
			if( isset ( $_POST['width']))
			{
				$iframe_width=sanitize_text_field($_POST['width']);
			}
			if( isset ( $_POST['height'])) 
			{
				$iframe_height=sanitize_text_field($_POST['height']);
			}
			
			if(empty($secret_key))
			{
				$secterKeyError='<span class="colorRed">Please Enter Secret Key</span>';
				$hasError=true;
				return;
			}
			if(empty($page_title))
			{
				$pageTitleError='<span class="colorRed">Please Enter Page Title</span>';
				$hasError=true;
				return;
			}
			if(empty($iframe_width))
			{
				$widthError='<span class="colorRed">Please Enter Iframe Width</span>';
				$hasError=true;
				return;
			}
			if(empty($iframe_height))
			{
				$heightError='<span class="colorRed">Please Enter Iframe Height</span>';
				$hasError=true;
				return;
			}
			if($hasError==false)
			{
				global $wpdb;
				/*CHECKING IN WP_OPTIONS TABLE FOR AppToE CONFIGURATION FOR IFRAME IS UPDATING OR NOT*/
				$prefix=$wpdb->prefix;
				$page= $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name='apptoe_page_id'" );
				$page_id= $page->option_value;
				$iframeCode='<html><iframe src="https://goapptoe.com/MakeAppointment?owner=';
				$iframeCode .=$secret_key .'" width=';
				$iframeCode .=$iframe_width .' height=';
				$iframeCode .=$iframe_height .' scrolling="no"></iframe></html>';
					
				if($page_id==null)/* FIRST TIME USER SUBMITTING CONFIGURATION DATA*/
				{
					$page=array(
							'post_author' => 1,
							'post_content' => $iframeCode,
							'post_title' => $page_title,
							'post_status' => 'publish',
							'post_type' => 'page',
					);
					$result=wp_insert_post( $page ); /*RETURN POST ID*/
				
					if($result!=0)
					{
						/*ADD ENTRY IN WP_OPTIONS TABLE TO STORE */
						$isOptionAdded=add_option('apptoe_page_id',$result,'','yes'); /* False if option was not added and true if option was added. Returing 1 on success*/
						if($isOptionAdded==true)
						{
							add_option('apptoe_secret_key',$secret_key,'','yes');
							add_option('apptoe_page_title',$page_title,'','yes');
							add_option('apptoe_width',$iframe_width,'','yes');
							add_option('apptoe_height',$iframe_height,'','yes');
							
							/*Getting current Theme Name, And primary_menu from wp_option table*/
							$theme=wp_get_theme(); 
							$theme_name=$theme->get( 'TextDomain' );
							$theme_option_name='theme_mods_'.$theme_name;
							$theme_option_obj= $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name='$theme_option_name'" );
							$theme_option_value=$theme_option_obj->option_value;
							$array_option_value=unserialize($theme_option_value);
							
							$firstMenuName=array_keys($array_option_value["nav_menu_locations"])[0];
							$primary_menu_id=$array_option_value["nav_menu_locations"][$firstMenuName];
							if($primary_menu_id != null)
							{
								/* Getting total page in primary_menu from wp_term_taxonomy table*/
								$get_primary_menu_details=$wpdb->get_row("SELECT * FROM $wpdb->term_taxonomy WHERE term_id=$primary_menu_id" );
								$total_page_in_primary_menu=$get_primary_menu_details->count;
								$updated_total_page=(int)$total_page_in_primary_menu + 1;
								
								$menu=array(
									'post_author'=>1,
									'post_status'=>'publish',
									'menu_order' =>$updated_total_page ,
									'post_type'=>'nav_menu_item',
								);
								$result_menu=wp_insert_post( $menu ); /* Return Post ID*/
								
								add_option('apptoe_menu_page_id',$result_menu,'','yes'); /* False if option was not added and true if option was added. Returing 1 on success*/
								
								
								$tableTermRelation=$prefix.'term_relationships';
								$add_term_relation=$wpdb->insert( $tableTermRelation ,array( 'object_id'=> $result_menu,'term_taxonomy_id'=> $primary_menu_id,'term_order'=>0) );
								
								$tableTermTaxonomy=$prefix.'term_taxonomy';
								$update_term_relation= $wpdb->update($tableTermTaxonomy,array ( 'count'=> $updated_total_page),array('term_id'=>$primary_menu_id) ); /*returns the number of rows updated, or false if there is an error*/
								
								 /* Start Adding data into wp_postmeta table for adding page into main menu*/
								add_post_meta( $result_menu, '_menu_item_type', 'post_type' );  /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_menu_item_parent', 0 ); /* Return Meta ID*/ 
								add_post_meta( $result_menu, '_menu_item_object_id', $result ); /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_object', 'page' ); /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_target', '' ); /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_classes', 'a:1:{i:0;s:0:"";}' ); /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_xfn', '' ); /* Return Meta ID*/
								add_post_meta( $result_menu, '_menu_item_url', '' ); /* Return Meta ID*/
							}
							$response="<div class='Success'>Configuration Successfully Saved.</div>";
						}
						else //Error While Adding to option table
						{
							$tablePosts=$prefix.'posts';
							$deleteCreatedPage= $wpdp->delete($tablePosts, array('ID' => $result), array( '%d' ) );
							$response="<div class='Error'>Error Occured, Try Again!!!.</div>";
						}
						
					}
					else
					{
						$response="<div class='Error'>Error!! Try Again!!!.</div>";
					}
				}
				else  /* USER IS UPDATING THE CONGFIGURATION */
				{
					$page=array(
							'ID' => $page_id,
							'post_author' => 1,
							'post_content' => $iframeCode,
							'post_title' => $page_title,
							'post_status' => 'publish',
							'post_type' => 'page',
					);
					$result=wp_update_post( $page ); /*RETURN POST ID*/
					
					if($result!=0 || $result != false)
					{
						update_option('apptoe_secret_key',$secret_key,'','yes');
						update_option('apptoe_page_title',$page_title,'','yes');
						update_option('apptoe_width',$iframe_width,'','yes');
						update_option('apptoe_height',$iframe_height,'','yes');
						$response="<div class='Success'>Configuration Updated Successfully.</div>";
					}
					else
					{
						$response="<div class='Error'>Error While Updating!! Try Again!!!</div>";
					}
				}
			}
		}
		else
		{
			$response="<div class='Error'>Error While Verifying!! Try Again!!!</div>";
		}
	}
	
?>
<?php echo $response; ?>
<?php
	$ApptoeSecretKey=get_option('apptoe_secret_key');
	$ApptoePageTitle=get_option('apptoe_page_title');
	$ApptoeWidth=get_option('apptoe_width');
	$ApptoeHeight=get_option('apptoe_height');
?>
<div class="apptoeBackground">
	<h1 class="colorBlue">AppToE Configuration</h1>
	<div>
		<form action="<?php the_permalink(); ?>" method="post">
		
			<table class="form-table">
				<tr>
					<th><label for="secretkey">Secret Key:</label></th>
					<td><input type="text" name="secretkey" value="<?php if( $ApptoeSecretKey ) echo esc_attr( $ApptoeSecretKey ); ?>" class="fieldWidth"/><br/>
						<?php echo $secterKeyError; ?>
					</td>
					<td>
					    <p>(This Key You will get from <a href="https://goapptoe.com/">www.goapptoe.com.</a> Login to your Apptoe Account -> Dashboard -> Settings -> Scheduling Link -> Copy Secret Key.)</p>
					</td>
				</tr>
				<tr>
					<th><label for="pagetitle">Your Page Title:</label></th>
					<td><input type="text" name="pagetitle" value="<?php if( $ApptoePageTitle ) echo esc_attr( $ApptoePageTitle ); ?>" class="fieldWidth"/><br/>
					<?php echo $pageTitleError; ?>
					</td>
					<td>
						<p>(For e.g.- Make An Appointment.)</p>
					</td>
				</tr>
				<tr>
					<th><label for="width">Width:</label></th>
					<td><input type="text" name="width" value="<?php if( $ApptoeWidth ) echo esc_attr( $ApptoeWidth ); ?>" class="fieldWidth"/><br/>
					<?php echo $widthError; ?>
					</td>
					<td>
						<p>(For e.g.- 100%  or 450px.)</p>
					</td>
				</tr>
				<tr>
					<th><label for="height">Height:</label></th>
					<td><input type="text" name="height" value="<?php if( $ApptoeHeight ) echo esc_attr( $ApptoeHeight ); ?>" class="fieldWidth"/><br/>
					<?php echo $heightError; ?>
					</td>
					<td>
						<p>(For e.g.- 1200px.)</p>
					</td>
				</tr>
				
				<tr>
					<td><input type="submit" name="apptoe_submit_btn" value="Submit" class="submitBtn"/></td>
				</tr>
			</table>
			
			<?php wp_nonce_field( basename(__FILE__), 'apptoe_form_nonce' ); ?>
		</form>
	</div>
</div>
