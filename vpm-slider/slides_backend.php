<?php
/********************************************************************************
VPM Slider Backend

Custom data handling access backend

    Copyright (C) 2011-2012 Peter Upfold.

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


*********************************************************************************/

if (!defined('VPM_SLIDER_IN_FUNCTIONS'))
{
	header('HTTP/1.1 403 Forbidden');
	die('<h1>Forbidden</h1>');
}


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
			
			
			background and link may also be numeric, encoded as a string
			
			if numeric and integers, they will be interpreted as WP post IDs to look up
				
	
	*/
	
class VPM_Slide_Group { 
/*
	Defines a slide group object for the purposes of storing a list of available
	groups in the wp_option	'vpm_slider_slide_groups'.
	
	This object specifies the slug and friendly group name. We then use the slug
	to work out which wp_option to query later -- vpm_slider_slides_[slug].
*/

	public $slug;
	public $originalSlug;
	public $name;
	
	public function __construct($slug, $name = null)
	{
	/*
		Set the slug and name for this group.
	*/
	
		$this->slug = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug), 0, 64);
		$this->originalSlug = $this->slug;
		
		if ($name)
		{
			$this->name = $name;
		}
	}
	
	public function load() {
	/*
		Load this slide group's name and slug into the object, from the DB.
	*/
	
		if (!get_option('vpm_slider_slide_groups'))
		{
			return false;
		}
		
		// get the current slide groups
		$currentGroups = get_option('vpm_slider_slide_groups');
		
		$theIndex = false;
		
		// loop through to find one with this original slug
		foreach($currentGroups as $key => $group)
		{
			if ($group->slug == $this->originalSlug)
			{
				$theIndex = $key;
				break;
			}
		}
		
		if ($theIndex === false)
		{
			return false;
		}
		else {
			$this->name = $currentGroups[$theIndex]->name;
			$this->slug = VPM_Slider::sanitizeSlideGroupSlug($currentGroups[$theIndex]->slug);
			return true;
		}
	}
	
	public function save() {
	/*
		Save this new slide group to the slide groups option.
	*/
	
		if (!get_option('vpm_slider_slide_groups'))
		{
			// create option
			add_option('vpm_slider_slide_groups', array(), '', 'yes');
		}
		
		// get the current slide groups
		$currentGroups = get_option('vpm_slider_slide_groups');
		
		$theIndex = false;
		
		// loop through to find one with this original slug
		foreach($currentGroups as $key => $group)
		{
			if ($group->slug == $this->originalSlug)
			{
				$theIndex = $key;
				break;
			}
		}
		
		if ($theIndex === false)
		{
			// add this as a new slide group at the end
			$currentGroups[] = $this;
		}
		else {
			// replace the group at $theIndex with the new information
			$currentGroups[$theIndex] = $this;
		}
		
		// save the groups list
		update_option('vpm_slider_slide_groups', $currentGroups);
	
	}
	
	public function delete()
	{
	/*
		Delete the slide group with this slug from the list.
	*/
	
		if (!get_option('vpm_slider_slide_groups'))
		{
			return false;
		}
		
		// get the current slide groups
		$currentGroups = get_option('vpm_slider_slide_groups');
		
		$theIndex = false;
		
		// loop through to find one with this original slug
		foreach($currentGroups as $key => $group)
		{
			if ($group->slug == $this->originalSlug)
			{
				$theIndex = $key;
				break;
			}
		}
		
		if ($theIndex === false)
		{
			return false;
		}
		else {
			// remove this group at $theIndex
			unset($currentGroups[$theIndex]);
		}
		
		// save the groups list
		update_option('vpm_slider_slide_groups', $currentGroups);
	
	}

};
	
class VPM_Slider_Backend {

	private $groupSlug; // the slug of this slide group

	public function __construct($slug)
	{
	/*
		Construct the backend handler, passing in the slug of the desired
		group to modify.
	*/
	
		$this->groupSlug = $this->sanitizeSlideGroupSlug($slug);
		
		if (get_option('vpm_slider_slides_' . $this->groupSlug) === false)
		{
			throw new Exception('The specified slide group does not exist.', 1);
			return false;
		}
	
	}
	
	protected function createSlideGroup($slug)
	{
	/*
		If the slide group did not exist at edit time, then create it at this stage.
	*/
	
		$newSlug = $this->sanitizeSlideGroupSlug(sanitize_title_with_dashes($slug));
		
		$newGroup = new VPMSlideGroup($newSlug, $newSlug); // not ideal for title, but we're not expecting
															// this to happen at all.
		$newGroup->save();	
		
		// add the new slides option for this group
		add_option('vpm_slider_slides_'.$newSlug, array(), '', 'yes');
	
	}
	
	public function sanitizeSlideGroupSlug($slug)
	{
	/*
		Sanitize a slide group slug, for accessing the wp_option row with that slug name.		
	*/
		return substr(preg_replace('/[^a-zA-Z0-9]/', '', $slug), 0, 64);
	}
	

	public function createNewSlide($title, $description, $background, $link, $title_pos_x, $title_pos_y)
	{
		/*
			Given a pre-validated set of data (title, description, backgorund,
			link, title_pos_x and title_pos_y, create a new slide and add to the
			option. Return the new slide ID for resorting in another function.
		*/
		
		$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
		
		if ($currentSlides === false)
		{
			
			$this->createSlideGroup($this->groupSlug);
			
			$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
			if ($currentSlides === false)
			{
				return false; //can't do it
			}
		}
		
		$newId = str_replace('.', '', uniqid('', true));
		
		$newSlide = array(
		
			'id' => $newId,
			'title' => $title,
			'description' => $description,
			'background' => $background,
			'link' => $link,
			'title_pos_x' => $title_pos_x,
			'title_pos_y' => $title_pos_y		
		
		);	
		
		$currentSlides[count($currentSlides)] = $newSlide;
		
		if ($this->writeNewSlidesOptionWithSlides($currentSlides))
		{
			return $newId;
		}
		else {
			return false;
		}
	
	}
	
	public function getSlideDataWithID($slideID) {
	/*
		Fetch the whole object for the given slide ID.
	*/
	
		$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
		
		if ($currentSlides === false || !is_array($currentSlides) || count($currentSlides) < 0)
		{
			return false;
		}
		
		else {
		
			foreach($currentSlides as $slide) {
			
				if ($slide['id'] == $slideID) {
				
					if ((int)$slide['link'] == $slide['link'])
					{	// if slide link is a number, and therefore a post ID of some sort
						$slp = (int) $slide['link'];
						$linkPost = get_post($slp);
						$slide['link_post_title'] = $linkPost->post_title;
					}
					
					if ((int)$slide['background'] == $slide['background'])
					{
						// if slide background is a number, it must be an attachment ID
						// so get its URL
						$slide['background_url'] = wp_get_attachment_url((int)$slide['background']);
					}
				
					return $slide;
				
				}			
			
			}
			
			// if we didn't find it
			
			return false;
		}
	
	}
	
	public function updateSlideWithIDAndData($slideID, $title, $description, $background, $link, $title_pos_x, $title_pos_y)
	{
	/*
		Given the slideID, update that slide with the pre-filtered data specified.
	*/
	
	$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
		
		if ($currentSlides === false || !is_array($currentSlides) || count($currentSlides) < 0)
		{
			return false;
		}
		
		else {
		
			$found = false;
		
			foreach($currentSlides as $i => $slide) {
			
				if ($slide['id'] == $slideID) {
				
					// we found the record we were looking for. update it
					$currentSlides[$i]['title'] = $title;
					$currentSlides[$i]['description'] = $description;
					$currentSlides[$i]['background'] = $background;
					$currentSlides[$i]['link'] = $link;
					$currentSlides[$i]['title_pos_x'] = $title_pos_x;
					$currentSlides[$i]['title_pos_y'] = $title_pos_y;
				
					$found = true;
				
				}	
			
			}
			
			if (!$found)
			{
				return false;
			}
		}
		
		// $currentSlides now holds the slides we want to save
		return $this->writeNewSlidesOptionWithSlides($currentSlides);
	
	}
	
	private function writeNewSlidesOptionWithSlides($slidesToWrite)
	{
	/*
		Dumb function that just updates the option with the array it is given.
	*/
	
		return update_option('vpm_slider_slides_' . $this->groupSlug, $slidesToWrite);
	
	}

	public function validateURL($url)
	{
	/*
		Assess whether or not a given string is a valid URL format, based on
		parse_url(). Returns true for valid format, false otherwise.
		
		Imported from Drupal 7 common.inc:valid_url.
		
		This function is Drupal code and is Copyright 2001 - 2010 by the original authors.
		This function is GPL2-licensed.
		
	*/
	
		return (bool)preg_match("
      /^                                                      # Start at the beginning of the text
      (?:ftp|https?|feed):\/\/                                # Look for ftp, http, https or feed schemes
      (?:                                                     # Userinfo (optional) which is typically
        (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
      )?
      (?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
        |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
      )
      (?::[0-9]+)?                                            # Server port number (optional)
      (?:[\/|\?]
        (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
      *)?
    $/xi", $url);
	
	}
	
	public function deleteSlideWithID($slideID) {
	/*
		Remove the slide with slideID from the slides
		option.
	*/
	
		$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
		
		if ($currentSlides === false)
		{
			$this->createSlideGroup($this->groupSlug);
			
			$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
			if ($currentSlides === false)
			{
				return false; //can't do it
			}
		}	
		
		if (is_array($currentSlides) && count($currentSlides) > 0)
		{
			$foundIt = false;		
			
			foreach($currentSlides as $index => $slide)
			{
			
				if ($slide['id'] == $slideID)
				{
					unset($currentSlides[$index]);
					$foundIt = true;
					break;
				}
			
			}
			
			if (!$foundIt)
				return false;
			else
			{
				return $this->writeNewSlidesOptionWithSlides($currentSlides);
			}
		
		}
		
		else {
			return false;
		}			
	
	}

	public function reshuffleSlides($newSlideOrder)
	{
	/*
		Given a new, serialised set of slide order IDs in an array,
		this function will shuffle the order of the slides with said
		IDs in the options array.
	*/
	
		$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
		
		if ($currentSlides === false)
		{
			
			$this->createSlideGroup($this->groupSlug);
			
			$currentSlides = get_option('vpm_slider_slides_' . $this->groupSlug);
			if ($currentSlides === false)
			{
				return false; //can't do it
			}
		}	
		
		
		if (is_array($currentSlides) && count($currentSlides) > 0)
		{
		
			$newSlides = array();	
			
			$newSlideNotFoundInCurrent = false;	
			
			foreach($newSlideOrder as $newIndex => $newSlideID)
			{			
				$foundThisSlide = false;
			
				foreach($currentSlides as $index => $slide)
				{
					if ($slide['id'] == $newSlideID)
					{
						$newSlides[count($newSlides)] = $slide;
						$foundThisSlide = true;
						continue;
					}
				}
				
				if (!$foundThisSlide)
				{
					$newSlideNotFoundInCurrent = true;
				}
				
			}
			
			if (count($currentSlides) != count($newSlides) || $newSlideNotFoundInCurrent)
			{
				// there is a disparity -- so a slide or more will be lost
				return 'disparity';
			}
			
			return $this->writeNewSlidesOptionWithSlides($newSlides);
		
		}
		else
		{
			return false;
		}
	
	}


};


?>