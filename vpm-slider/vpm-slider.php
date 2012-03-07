<?php
/*
Plugin Name: VPM Slider
Plugin URI: http://www.vanpattenmedia.com/
Description: Allows the user to create, edit and remove ‘slides’ with text and images. MAKE ME BETTER.
Version: 1.0
Author: Peter Upfold
Author URI: http://vanpattenmedia.com/
License: GPL2
/* ----------------------------------------------*/

/*  Copyright (C) 2011-2012 Peter Upfold.

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

define('VPM_SLIDER_IN_FUNCTIONS', true);
define('VPM_SLIDER_REQUIRED_CAPABILITY', 'vpm_slider_manage_slides');
define('VPM_SLIDER_MAX_SLIDE_GROUPS', 24);
require_once(dirname(__FILE__).'/slides_backend.php');

class VPMSlider { // not actually a widget -- really a plugin admin panel
							//  the widget class comes later



	/* data strucutre
	
		a serialized array stored as a wp_option
		
		
		vpm_slider_slides_[slug]
		
			[0]
				id				[string] (generated by str_replace('.', '_', uniqid('', true)); )
				title			[string]
				description		[string]
				background		[string]
				link			[string]
				title_pos_x		[int]
				title_pos_y		[int]
				
			[1]
				id				[string] (generated by str_replace('.', '_', uniqid('', true)); )
				title			[string]
				description		[string]
				background		[string]
				link			[string]
				title_pos_x		[int]
				title_pos_y		[int]	
				
			[2] ...			
	
	*/
	
	public function createSlidesOptionField() {
	/*
		Upon plugin activation, creates the vpm_homepage_slides option
		in wp_options, if it does not already exist.
	*/
	
		if (!get_option('vpm_slider_slide_groups')) {
		
			add_option('vpm_slider_slide_groups', array()); // create with a blank array
		
		}
		
		// set the capability for administrator so they can visit the options page
		$admin = get_role('administrator');
		$admin->add_cap(VPM_SLIDER_REQUIRED_CAPABILITY);
	
	}
	
	public function sanitizeSlideGroupSlug($slug)
	{
	/*
		Sanitize a slide group slug, for accessing the wp_option row with that slug name.		
	*/
		return substr(preg_replace('/[^a-zA-Z0-9]/', '', $slug), 0, 64);
	}
	
	private function getCurrentSlides($slug) {
	/*
		Returns an array of the current slides in the database, in their 
		current precedence order.
	*/
		return get_option('vpm_slider_slides_' . VPMSlider::sanitizeSlideGroupSlug($slug) );	
	}
	
	private function idFilter($idToFilter)
	{
	/*
		Filter a uniqid string for output to the admin interface HTML.
	*/
	
		return preg_replace('[^0-9a-zA-Z_]', '', $idToFilter);
	
	}
	
	public function uglyJSRedirect($location, $data = false)
	{
	/*
		Redirect, from within the admin panel for this plugin back to the plugin's main page.
	*/
	
		switch ($location) {
		
			case 'root':
				$url = 'admin.php?page=vpm-slider';
			break;
			
			case 'edit-slide-group':
				$url = 'admin.php?page=vpm-slider&group=';
				$url .= esc_attr(VPMSlider::sanitizeSlideGroupSlug($data));
			break;
			
			default:
				$url = 'admin.php?page=vpm-slider';
			break;
		
		}
	
		// erm, just a little bit of an ugly hack :(
		
		?><script type="text/javascript">window.location.replace('<?php echo $url; ?>');</script>
		<noscript><h1><a href="<?php echo esc_url($url); ?>">Please go here</a></h1></noscript><?php
		die();
	
	}
	
	public function passControlToAjaxHandler()
	{
	/*
		If the user is trying to perform an Ajax action, immediately pass
		control over to ajax_interface.php.
		
		This should hook admin_init() (therefore be as light as possible).
	*/
	
		if (array_key_exists('page', $_GET) && $_GET['page'] == 'vpm-slider' &&
			array_key_exists('vpm-slider-ajax', $_GET) && $_GET['vpm-slider-ajax'] == 'true'
		)
		{
			require_once(dirname(__FILE__).'/ajax_interface.php');
		}		
	
	}

	public function addAdminSubMenu() {
		/*
			Add the submenu to the admin sidebar for the configuration screen.
		*/	
		
		if (array_key_exists('page', $_GET) && $_GET['page'] == 'vpm-slider')
		{
		
			// get our JavaScript on	
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui');
			
			wp_enqueue_script('media-upload');
			wp_enqueue_script('thickbox');
			wp_enqueue_style('thickbox');
			
			wp_enqueue_script('jquery-ui-draggable');	
			wp_enqueue_script('jquery-ui-droppable');	
			wp_enqueue_script('jquery-ui-sortable');		

			wp_register_script('vpm-slider-interface', plugin_dir_url( __FILE__ ).'js/interface.js');
			wp_enqueue_script('vpm-slider-interface');	
			
			// load the rotator css
			wp_register_style('vpm-slider-rotator-styles', plugin_dir_url( __FILE__ ).'css/slider_edit.css');
			wp_enqueue_style('vpm-slider-rotator-styles');
			
			wp_register_style('vpm-slider-interface-styles', plugin_dir_url( __FILE__ ).'css/interface.css');
			wp_enqueue_style('vpm-slider-interface-styles');
		}
	
		/* Top-level menu page */
		add_menu_page(
			
			'Slider',										/* title of options page */
			'Slider',										/* title of options menu item */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider',									/* menu slug */
			array('VPMSlider', 'printSlideGroupsPage'),		/* callback to print the page to output */
			plugin_dir_url( __FILE__ ).'img/vpm-slider-icon-16.png',/* icon file */
			null 											/* menu position number */
		);
		
		/* First child, 'Slide Groups' */
		add_submenu_page(
		
			'vpm-slider',									/* parent slug */
			'Slide Groups',										/* title of page */
			'Slide Groups',										/* title to use in menu */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider',									/* menu slug */
			array('VPMSlider', 'printSlideGroupsPage')		/* callback to print the page to output */
		
		);
		
		/* 'Settings' */
		add_submenu_page(
		
			'vpm-slider',									/* parent slug */
			'Settings',										/* title of page */
			'Settings',										/* title to use in menu */
			VPM_SLIDER_REQUIRED_CAPABILITY,					/* permissions level */
			'vpm-slider-settings',							/* menu slug */
			array('VPMSlider', 'printSettingsPage')			/* callback to print the page to output */
		
		);		
		
		
	
	}
	
	public function printSlideGroupsPage()
	{
	/*
		Print the page for adding, deleting Slide Groups and for pushing people over
		to the 'actual' slides editing interface for that Slide Group.
	*/
		
		// permissions check
		if (!current_user_can(VPM_SLIDER_REQUIRED_CAPABILITY))
		{
			?><h1>This page is not accessible to your user.</h1><?php
			return;
		}

		// if we are to remove a slide group, do that and redirect to home
		if (array_key_exists('action', $_GET) && $_GET['action'] == 'remove' && array_key_exists('group', $_GET))
		{
			if (wp_verify_nonce($_REQUEST['_wpnonce'], 'remove-slide-group'))
			{
				// remove the slide group
				$newGroup = new VPMSlideGroup($_GET['group']);
				$newGroup->delete();
				
				// remove the option
				delete_option('vpm_slider_slides_'. VPMSlider::sanitizeSlideGroupSlug($_GET['group']));
				
				// redirect back to the admin vpm slider root page
				VPMSlider::uglyJSRedirect('root');
				die();
					
			}
		}	
		
		// if the URL otherwise has 'group' in the GET parameters, it's time to pass control
		// to printSlidesPage() for editing purposes
		if (array_key_exists('group', $_GET))
		{
			VPMSlider::printSlidesPage();
			return;
		}
		
		// if we are to create a new slide group, do that and redirect to edit
		if (array_key_exists('action', $_GET) && $_GET['action'] == 'new_slide_group')
		{
			if (wp_verify_nonce($_REQUEST['_wpnonce'], 'new-slide-group'))
			{
			
				if (!empty($_POST['group-name']))
				{				
					// add the new slide group
					$newSlug = VPMSlider::sanitizeSlideGroupSlug(sanitize_title_with_dashes($_POST['group-name']));
					
					$newGroup = new VPMSlideGroup($newSlug, $_POST['group-name']);
					$newGroup->save();	
					
					// add the new slides option for this group
					add_option('vpm_slider_slides_'.$newSlug, array(), '', 'yes');
					
					// redirect to the new edit page for this slide group
					VPMSlider::uglyJSRedirect('edit-slide-group', $newSlug);
					die();
				}
			}
		}	
		
		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready(function() {
			jQuery('#new-slide-group-button').click(function(e) {
				e.preventDefault();
				jQuery('#new-slide-group').show('slow');
			});
			jQuery('#new-slide-group-cancel').click(function(e) {
				e.preventDefault();
				jQuery('#new-slide-group').hide('slow');
			});
		});
		//]]>
		</script>
		<div class="wrap">
		
		<div id="icon-vpm-slides" class="icon32"><br /></div><h2>Slide Groups <a href="#" id="new-slide-group-button" class="add-new-h2">Add New</a></h2>

		<div id="new-slide-group">
			<form name="new-slide-group-form" id="new-slide-group-form" method="post" action="admin.php?page=vpm-slider&action=new_slide_group">
				<h3 id="new-slide-group-header">Add a Slide Group</h3>
				<?php wp_nonce_field('new-slide-group');?>
				<table class="form-table" style="max-width:690px">
				
					<tr class="form-field form-required">
						<th scope="row"><label for="group-name">Group Name</label></th>
						<td><input name="group-name" type="text" id="group-name" value="" /></td>
					</tr>
				</table>
				<p class="submit"><input type="submit" class="button-primary" value="Add Slide Group "  />
				<input type="button" id="new-slide-group-cancel" class="button-secondary" value="Cancel" /></p></form>
			</form>
		</div>


		<?php require_once( dirname( __FILE__ ) . '/slide_groups_table.php');
		$table = new SlideGroupsTable();
		$table->prepare_items();
		$table->display();
		 ?>

		</div><!--wrap-->
		<?php
	}
	
	public function printSlidesPage() {
	/*
		Print the actual slides page for adding, editing and removing the slides.
	*/
		// permissions check
		if (!current_user_can(VPM_SLIDER_REQUIRED_CAPABILITY))
		{
			?><h1>This page is not accessible to your user.</h1><?php
			return;
		}

		$theSlug = VPMSlider::sanitizeSlideGroupSlug($_GET['group']);
		if (empty($theSlug))
		{
			echo '<div class="wrap"><h1>No Slide Group selected.</h1></div>';
			return;
		}
		
		// get the name data for this slide group based on its slug
		$slideGroup = new VPMSlideGroup($_GET['group']);
		if (!$slideGroup->load())
		{
			echo '<div class="wrap"><h1>Could not load the selected Slide Group. Does it exist?</h1></div>';
			return;
		}
		
		?>
		

		<script type="text/javascript">
		//<![CDATA[
		var VPM_WP_ROOT = '<?php echo admin_url(); ?>';var VPM_HPS_PLUGIN_URL = '<?php echo admin_url();?>admin.php?page=vpm-slider&vpm-slider-ajax=true&';var VPM_HPS_GROUP = '<?php echo esc_attr($theSlug);?>';
		//]]>
		</script>
		
		<div class="wrap">
		
		<div id="icon-vpm-slides" class="icon32"><br /></div><h2>&lsquo;<?php echo esc_html($slideGroup->name);?>&rsquo; Slides <a href="#" id="new-slide-button" class="add-new-h2">Add New</a></h2>
		
		<noscript>
		<h3>Sorry, this interface requires JavaScript to function.</h3>
		<p>You will need to enable JavaScript for this page before any of the controls below will work.</p>
		</noscript>
		
		<form name="homepage-slides">
				
		<!--sortable slides-->
		<ul id="slidesort">
		<?php
		
		$currentSlides = VPMSlider::getCurrentSlides($theSlug);
		
		if (is_array($currentSlides) && count($currentSlides) > 0)
		{
		
			foreach($currentSlides as $slide) {
			
				$myId = VPMSlider::idFilter($slide['id']);
				
				?>
				
				<li id="slidesort_<?php echo $myId;?>">
								
					<span id="slidesort_<?php echo $myId;?>_text"><?php echo stripslashes(esc_html($slide['title']));?></span>
					
					<span id="slidesort_<?php echo $myId;?>_delete" class="slide-delete">
						[<a id="slidesort_<?php echo $myId;?>_delete_button" class="slide-delete-button" href="#">delete</a>]
					</span>
				
				</li>
				
				<?php
			
			}
		
		}
		
		?>
		<div class="slidesort-add-hint"<?php if (is_array($currentSlides) && count($currentSlides) > 0) echo ' style="display:none"'; ?>>Click &lsquo;Add New&rsquo; to create a slide.</div>
		</ul>
		
		<div id="message-area"></div>
		
		<div id="loading-area"><img src="<?php echo plugin_dir_url( __FILE__ ).'img/loadingAnimation.gif';?>" /></div>
		
		<hr />
		
		<div id="edit-area">
		
			<!--<div id="preview-area">
			
				<div id="slide-preview">
				<h2 id="slide-preview-title">Slide preview</h2>
				<p id="slide-preview-description">Class Aptent Taciti Sociosqu Ad Litora Torquent Per Conubia Nostra, Per Inceptos.</p>
				</div>
			
			</div>-->
		
			<ul id="homepage_slider">
			
				<li id="preview-area">
				
					<div id="slide-preview" class="desc">
						<h2 id="slide-preview-title">Slide preview</h2>
						<div class="png_fix">
							<p id="slide-preview-description">Class Aptent Taciti Sociosqu Ad Litora Torquent Per Conubia Nostra, Per Inceptos.</p>
						</div>
					</div>
				
				</li>
			
			</ul>
		
			<div id="edit-controls">
				<form id="edit-form">
				<div class="edit-controls-inputs">
					<p><label for="edit-slide-title">Title:</label> <input type="text" name="slide-title" id="edit-slide-title" value="" maxlength="64" /></p>
					<p><label for="edit-slide-description">Description:</label> <input type="text" name="slide-description" id="edit-slide-description" value="" maxlength="255" /></p>
					<p><label for="edit-slide-image-upload">Background</label>: <span id="edit-slide-image-url"></span> <input id="edit-slide-image" type="hidden" name="slide-image" /><input id="edit-slide-image-upload" type="button" value="Upload image" /></p>
					<p><label for="edit-slide-link">Slide Link:</label> <input type="text" name="slide-link" id="edit-slide-link" value="" maxlength="255" /></p>
				</div>
				<div class="edit-controls-save-input">
					<p><input type="button" id="edit-controls-save" class="button-primary" value="Save" /></p>
					<p><input type="button" id="edit-controls-cancel" class="button-secondary" value="Cancel" /></p>
				</div>
				</form>
			
			</div>
		
		</div>
		
		<div style="clear:both;"></div>
		
		
		<!--<br/>
		<input type="button" value="Serialise slide order" id="serialise-me-test" /><br />
		<input type="button" value="Show X/Y offset values" id="show-xy-test" /><br />-->
		
		
		</form>
		</div><?php
	
	}
	
	public function setCapabilityForRoles($rolesToSet)
	{
	/*
		Set the VPM_SLIDER_REQUIRED_CAPABILITY capability against this role, so this WordPress
		user role is able to manage the slides.
		
		Will clear out the capability from all roles, then add it to both administrator and the
		specified roles. (Administrator always has access).
	*/
		global $wp_roles;
		
		if (!current_user_can('manage_options'))
		{
			return false;
		}
	
		$allRoles = get_editable_roles();
		$validRoles = array_keys($allRoles);
		
		if (!is_array($allRoles) || count($allRoles) < 1)
		{
			return false;
		}
		
		// clear the capability from all roles first
		foreach ($allRoles as $rName => $r)
		{
			$wp_roles->remove_cap($rName, VPM_SLIDER_REQUIRED_CAPABILITY);
		}
		
		// add the capability to 'administrator', which can always manage slides
		$wp_roles->add_cap('administrator', VPM_SLIDER_REQUIRED_CAPABILITY);
		
		// add the capability to the specified $roleToSet
		if (is_array($rolesToSet) && count($rolesToSet) > 0)
		{
			foreach($rolesToSet as $theRole)
			{
				if (in_array($theRole, $validRoles))
				{
					$wp_roles->add_cap($theRole, VPM_SLIDER_REQUIRED_CAPABILITY);
				}
			}
		}
		
		return true;
	
	}
	
	public function printSettingsPage()
	{
	/*
		Print the settings page to output, and also handle any of the Settings forms if they
		have come back to us.
	*/
	
		if (!current_user_can(VPM_SLIDER_REQUIRED_CAPABILITY))
		{
			echo '<h1>You do not have permission to manage slider settings.</h1>';
			die();
		}
	
		$success = null;
		$message = '';
	
		if (strtolower($_SERVER['REQUEST_METHOD']) == 'post' && array_key_exists('vpm-slider-settings-submitted', $_POST))
		{
			// handle the submitted form
			
			if (current_user_can('manage_options'))
			{
			
				$rolesToAdd = array();
			
				// find any checked roles to add our capability to
				foreach($_POST as $pk => $po)
				{
					if (preg_match('/^required_capability_/', $pk))
					{
						$roleNameChopped = substr($pk, strlen('required_capability_'));
						
						// do not allow administrator to be modified
						if ($roleNameChopped != 'administrator' && $po == '1')
						{
							$rolesToAdd[] = $roleNameChopped;		
						}								
					}
				}
				
				VPMSlider::setCapabilityForRoles($rolesToAdd);
				$success = true;
				$message .= 'Required role level saved.';
			
			}
			
		
		}
	
		?><div class="wrap">
		<div id="icon-vpm-slides" class="icon32" style="background:transparent url(<?php echo plugin_dir_url( __FILE__ );?>img/vpm-slider-icon-32.png?ver=20120229) no-repeat;"><br /></div><h2>Settings</h2>
		
		
		<?php if ($success): ?>
			<div class="updated settings-error">
				<p><strong><?php echo esc_html($message); ?></strong></p>
			</div>
		<?php endif; ?>
		
		
		<form method="post" action="admin.php?page=vpm-slider-settings">
			<input type="hidden" name="vpm-slider-settings-submitted" value="true" />
		
			<!-- Only display 'Required Role Level' to manage_options capable users -->
			<?php if (current_user_can('manage_options')):?>
		
			<h3>Required Role Level</h3>
			<p>Any user with a checked role will be allowed to create, edit and delete slides. Only users that can manage
			widgets are able to activate, deactivate or move the VPM Slider widget, which makes the slides show up on your site.</p>
			
			<table class="form-table">
			<tbody>
				<tr class="form-field">
					<td>
					<!--<pre>
					<?php
					$allRoles = get_editable_roles();
					print_r($allRoles);
					?>
					</pre>-->
					
					<?php
							if (is_array($allRoles) && count($allRoles) > 0):
								foreach($allRoles as $rName => $r): ?>
					<tr>
						<td>
							<label for="required_capability_<?php echo esc_attr($rName);?>">
								<input type="checkbox" name="required_capability_<?php echo esc_attr($rName);?>"
								id="required_capability_<?php echo esc_attr($rName);?>" value="1" style="width:20px;"
									<?php 
									// if this role has the vpm_slider_manage_slides capability, mark it as selected
									
									if (array_key_exists(VPM_SLIDER_REQUIRED_CAPABILITY, $r['capabilities'])): ?>
									checked="checked"
									<?php endif;?>
									
									<?php // lock administrator checkbox on
									if ($rName == 'administrator'):
									 ?>
									disabled="disabled"
									 <?php endif; ?>

								 /><?php echo esc_html($r['name']);?><br/>
							</label>
						</td>
					</tr>
					
					<?php endforeach; endif; ?>
				
			</table>
			
			<?php endif; ?>		
			
		<p class="submit">
			<input class="button-primary" type="submit" value="Save Changes" id="submitbutton" />		
		</p>
		
		</form>
		</div><?php
	
	}
	
	public function enqueueFrontendCSS()
	{
	/*
		When WordPress is enqueueing the styles, inject our slider CSS in.
	*/
	
		$origFile = plugin_dir_path( __FILE__ ) . 'css/slider_edit.css';
		$origUrl = plugin_dir_url( __FILE__ ) . 'css/slider_edit.css';
		
		$templateFile = plugin_dir_path( __FILE__ ) . 'templates/slider.css';
		$templateUrl = plugin_dir_url( __FILE__ ) . 'templates/slider.css';
		
		if (@file_exists($templateFile))
		{
			wp_register_style('vpm-slider-frontend', $templateUrl, array(), '20120214', 'all');
			wp_enqueue_style('vpm-slider-frontend');
		}		
		else if (@file_exists($origFile))
		{
			wp_register_style('vpm-slider-frontend', $origUrl, array(), '20120214', 'all');
			wp_enqueue_style('vpm-slider-frontend');
		}
	
	}
	
	public function registerAsWidget() {
	/*
		Register the output to the theme as a widget	
	*/
	
	register_widget('VPMSliderWidget');

}


};


class VPMSliderWidget extends WP_Widget {	
/*
	The VPM Slider Widget is responsible for allowing the user to place the slider in any
	‘sidebar’ defined in their theme and for invoking the Slider theme file for displaying
	the slides.
	
	This widget class also defines a minimalist API for the Slider theme files to use to display
	the slides.
*/

	/*
		These hold the data for the current slide we are working with.
		
		The theme file accesses these indirectly, through the the_… and get_the_… functions.
	*/
	private $slides; // stores all of the slides in this group
	private $instance; // has_slides needs access to the instance data
	protected $slide_title;
	protected $slide_description;
	protected $slide_background_url;
	protected $slide_link;
	protected $slide_x;
	protected $slide_y;
	protected $slide_identifier;
	protected $slider_iteration = 0;
	
	
	public function __construct(){
	/*
		Constructor, merely calls the WP_Widget constructor.
	*/
		parent::__construct(false, 'VPM Slider');
	}
	
	public function widget($args, $instance) {
	/*
		The widget function is responsible for rendering the widget's output. In the case
		of VPM Slider Widget, this will invoke the Slider theme file to output the slides
		to the desired widget area.
	*/
		
		if (!$this->instance)
		{
			// prepare instance data for has_slides()
			$this->instance = $instance;
		}
		
		$s = &$this; // $s is used by the theme to call our functions to actually display the data
		
		// look for a theme file for vpm-slider in the current active theme
		$themePath = get_theme_root();
		
		if ( @file_exists($themePath . '/vpm-slider.php' ) )
		{
			require_once($themePath . '/vpm-slider.php' );
		}
		else
		{ // if not, use our default
			require_once( dirname(__FILE__) . '/slider_default_theme.php' );
		}	
		
		
	?>


</ul>	
	<?php

	}
	
	public function form($instance)
	{
	/*
		The form function defines the settings form for the widget.
		
		In our case, we will allow the user to pick which Slide Group this widget is responsible
		for displaying.
	*/
	
	?><p>Choose a slide group for this widget to show:</p>
	
	<select id="<?php echo $this->get_field_id('groupSlug');?>" name="<?php echo $this->get_field_name('groupSlug');?>">
		<option value="**INVALID**">--------------------</option>
		<?php
		
			// find all the slide groups and offer them for the widget
			
			$slideGroups = get_option('vpm_slider_slide_groups');
			
			if (is_array($slideGroups) && count($slideGroups) > 0)
			{
				foreach($slideGroups as $group)
				{
					?><option value="<?php echo esc_attr($group->slug);?>"
						<?php if (array_key_exists('groupSlug', $instance)):
							echo ($group->slug == $instance['groupSlug']) ? ' selected="selected"' : '';
						endif; ?>
					><?php echo esc_html($group->name);?></option><?php
				}
			
			}				
		
		?>		
	</select>
	<?php
	
	}
	
	public function update($newInstance, $oldInstance)
	{
	/*
		Update the widget's settings with the new selected slide group from the form()
	*/
	
		if ($newInstance['groupSlug'] != '**INVALID**')
		{
			
			return array('groupSlug' => VPMSlider::sanitizeSlideGroupSlug($newInstance['groupSlug']));
		}
		else {
			return false;
		}
	
	}
	
	
	public function has_slides()
	{
	/*
		Behaves as an iterator for the purposes of slider theme files. It loads
		in the next slide, readying the other functions below for returning
		the data from this particular slide to the theme.
		
		
	*/
	
		if (!$this->instance)
		{
			throw new Exception("The widget's instance data, containing information about which slide group to show, could not be loaded.");
			return false;
		}
	
		if (!is_array($this->slides) || count($this->slides) < 1)
		{
			$this->slides = get_option('vpm_slider_slides_' . VPMSlider::sanitizeSlideGroupSlug($this->instance['groupSlug']));		
		}
		
		// on which slide should we work? does it exist?
		if (count($this->slides) < $this->slider_iteration + 1)
		{
			return false; // we are at the end of the slides
		}
		
		// otherwise, load in the data
		if (!empty ($this->slides[$this->slider_iteration]['title']) )
		{
			$this->slide_title = $this->slides[$this->slider_iteration]['title'];
		}
		if (!empty ($this->slides[$this->slider_iteration]['description']) )
		{
			$this->slide_description = $this->slides[$this->slider_iteration]['description'];
		}
		
		if (!empty ($this->slides[$this->slider_iteration]['id']) )
		{
			$this->slide_identifier = $this->slides[$this->slider_iteration]['id'];
		}
		
		// the background may be blank!
		if (!empty ($this->slides[$this->slider_iteration]['background']) )
		{
			$this->slide_background_url = $this->slides[$this->slider_iteration]['background'];
		}
		else {
			$this->slide_background_url = '';
		}
		
		// the link may be blank!
		if (!empty ($this->slides[$this->slider_iteration]['link']) )
		{
			$this->slide_link = $this->slides[$this->slider_iteration]['link'];
		}
		else {
			$this->slide_link = '';
		}
		
		// get X and Y coords
		if (!empty ($this->slides[$this->slider_iteration]['title_pos_x']) )
		{
			$this->slide_x = $this->slides[$this->slider_iteration]['title_pos_x'];
		}
		if (!empty ($this->slides[$this->slider_iteration]['title_pos_y']) )
		{
			$this->slide_y = $this->slides[$this->slider_iteration]['title_pos_y'];
		}
		
		
		
		// the data is ready, bump the iterator and return true
		$this->slider_iteration++;
		return true;
		
		
	}
	
	public function the_title()
	{
	/*
		Print the slide title to output, having sanitised it.
	*/
	
		echo $this->get_the_title();
	
	}
	
	public function get_the_title()
	{
	/*
		Return the slide title, having sanitised it.
	*/
	
		return esc_html( apply_filters( 'vpm-slider_slide_title', $this->slide_title ) );
	
	}
	
	public function the_description()
	{
	/*
		Print the slide description to output, having sanitised it.
	*/
	
		echo $this->get_the_description();
	
	}
	
	public function get_the_description()
	{
	/*
		Return the slide description, having sanitised it.
	*/
	
		return esc_html( apply_filters ( 'vpm-slider_slide_description', $this->slide_description ) );
	
	}
	
	public function the_background_url()
	{
	/*
		Print the background URL to output, having sanitised it.
	*/
	
		echo $this->get_the_background_url();
	
	}
	
	public function get_the_background_url()
	{
	/*
		Return the background URL, having sanitisied it.
	*/
		return esc_url( apply_filters ('vpm-slider_slide_background_url', $this->slide_background_url) );
	
	}
	
	public function the_link()
	{
	/*
		Print the slide link URL to output, having sanitised it.
	*/
	
		echo $this->get_the_link();
	
	}
	
	public function get_the_link()
	{
	/*
		Return the slide link URL, having sanitised it.
	*/
	
		return esc_url ( apply_filters('vpm-slider_slide_link', $this->slide_link) );
	
	}
	
	public function the_x()
	{
	/*
		Print the X coordinate to the output, having sanitised it.
	*/
	
		echo $this->get_the_x();
	
	}
	
	public function get_the_x()
	{
	/*
		Return the X coordinate, having sanitised it.
	*/
	
		return intval ( apply_filters( 'vpm-slider_slide_x', $this->slide_x ), 10 /* decimal */ );
	
	}
	
	public function the_y()
	{
	/*
		Print the Y coordinate to the output, having sanitised it.
	*/
	
		echo $this->get_the_y();
	
	}
	
	public function get_the_y()
	{
	/*
		Return the Y coordinate, having sanitised it.
	*/
	
		return intval ( apply_filters( 'vpm-slider_slide_y', $this->slide_y ), 10 /* decimal */ );
	
	}
	
	public function the_identifier()
	{
	/*
		Print the slide identifier to output, having sanitised it.
	*/
	
		echo $this->get_the_identifier();
		
	}
	
	public function get_the_identifier()
	{
	/*
		Return the slide identifier to output, having sanitised it.
	*/
	
		return esc_attr( apply_filters('vpm-slider_slide_identifier', $this->slide_identifier) );
	
	}
	
	public function iteration()
	{
	/*
		Return the iteration number. How many slides have we been through?
	*/
	
		return intval ( $this->slider_iteration - 1 );
		// has_slides() always bumps the iteration ready for the next run, but we
		// are still running for the theme's purposes on the previous iteration.
		// Hence, returning the iteration - 1.
	
	}


};

register_activation_hook(__FILE__, array('VPMSlider', 'createSlidesOptionField'));
add_action('admin_menu', array('VPMSlider', 'addAdminSubMenu'));
add_action('widgets_init', array('VPMSlider', 'registerAsWidget'));
add_action('admin_init', array('VPMSlider', 'passControlToAjaxHandler'));

add_action('wp_enqueue_scripts', array('VPMSlider', 'enqueueFrontendCSS'));

?>