<?php
/**
 * tabbed_settings.php
 * This file is part of the Yate-LMI Project http://www.yatebts.com
 *
 * Copyright (C) 2016 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once("lib.php");
require_once("check_validity_fields.php");

class TabbedSettings 
{
	protected $error = "";
	protected $warnings = "";
	protected $current_section = "";
	protected $current_subsection = "";

	function __construct()
	{
	}

	function getMenuStructure()
	{
		return array();
	}

	function getSectionsDescription()
	{
		return array();
	}

	function validateFields($section, $subsection)
	{
		$allow_empty_params = $this->allow_empty_params;

		$fields = $this->getDefaultFields();

		$new_fields = array();
		$request_fields = array();
		$this->error_field = array();
		$this->warning_field  = array();
		
		if (!isset($fields[$section][$subsection]))
			return array(true,"fields"=>array(),"request_fields"=>array());

		foreach ($fields[$section][$subsection] as $param_name => $data) {
			$paramname = str_replace(".", "_", $param_name);
			$new_fields[$section][$subsection][$param_name] = $data;

			$request_fields[$subsection][$param_name] = $data;

			$field_param = getparam($paramname);
			if (isset($data["display"]) && $data["display"] == "checkbox" && $field_param == NULL) 
				$field_param = "off";
			$field_param = htmlspecialchars_decode($field_param);

			if (!valid_param($field_param)) {
				if (!in_array($param_name, $allow_empty_params))
					$this->error_field[] = array("Field $param_name can't be empty.", $param_name);
			}

			$res = array(true);
			if ($data["display"] == "select" && !isset($data["validity"])) 
				$res = $this->validSelectField($param_name, $field_param, $data[0]);
			elseif (isset($data["validity"])) 
				$res = $this->cbValidityField($data["validity"], $param_name, $field_param);

			if (!$res[0])
				$this->error_field[] = array($res[1], $param_name);
			elseif ($res[0] && isset($res[1]))
				$this->warning_field[] = array($res[1], $param_name);

			// set to "" parameters that are not set or are set to "Factory calibrated"
			// they will be written commented in ybts.conf
			if (!valid_param($field_param) || $field_param=="Factory calibrated")
				$field_param = "";

			$request_fields[$subsection][$param_name] = $field_param;

			if ($data["display"] == 'select')
				$new_fields[$section][$subsection][$param_name][0]["selected"] = $field_param;
			else
				$new_fields[$section][$subsection][$param_name]["value"] = $field_param;
		}

		$status = (!count($this->error_field)) ? true : false;
		return array($status, "fields"=> $new_fields, "request_fields"=>$request_fields);
	}


	/**
	 * Makes the callback for the function set in "validity" field from array $fields (from ybts_fields.php).  
	 * Note: There are 4 types of callbacks:(call_function_from_validity_field will make the callback for the last 3 cases)
	 * - a generic function that test if a value is in an array (check_valid_values_for_select_field($param_name, $field_param, $select_array)) this is not set in "validity" field
	 * this function is triggered by $fields[$section][$subsection]["display"] == "select"
	 * - a generic function that test if a value is in a specific interval OR in a given regex(check_field_validity($field_name, $field_value, $min=false, $max=false, $regex=false))
	 * this is triggered from $fields[$section][$subsection]["validity"] field with the specific parameters.
	 * Ex: "validity" => array("check_field_validity", false, false, "^[0-9]{2,3}$") OR 
	 *     "validity" => array("check_field_validity", 0, 65535) 
	 * - a specified function name with 3 parameter: field_name, field_value, $restricted_value is the getparam("field_value")
	 * this is triggered from $fields[$section][$subsection]["validity"] that contains the name of the function and the specific parameters. Ex: "validity" => array("check_uplink_persistent", "Uplink_KeepAlive")
	 * - a specified function name with 2 parameters: field_name, field_value
	 * this is triggered from $fields[$section][$subsection]["validity"] that contains only the name of the function. Ex: "validity" => array("check_timer_raupdate") 
	 */
	function cbValidityField($validity, $param_name, $field_param)
	{
		if (function_exists("call_user_func_array")) { //this function exists in PHP 5.3.0
			$total = count($validity);
			$args = array($param_name,$field_param);
			
			for ($i=1; $i<$total; $i++) 
				$args[] = $total==2 ? getparam($validity[$i]) : $validity[$i];

			//call a function ($validity[0] = the function name; and his args as an array)
			$res = call_user_func_array($validity[0],$args);
		} else {
			$func_name = $validity[0];
			if (count($validity)>= 3) {
				$param1 = $validity[1];
				$param2 = $validity[2];
				$param3 = isset($validity[3])? $validity[3] : false;
				//call to specified validity function
				$res = $func_name($param_name, $field_param,$param1,$param2,$param3);
			} else  {
				if (!isset($validity[1])) 
					$res = $func_name($param_name, $field_param);
				else
					$res = $func_name($param_name, $field_param, getparam($validity[1]));
			}
		}
		return $res;
	}

	function validSelectField($field_name, $field_value, $select_array)
	{
		return check_valid_values_for_select_field($field_name, $field_value, $select_array);
	}


	 /**
	  * Build array with conf section names by taking all subsections and formating them: apply lowercase and replace " " with "_"
	  */
	function buildConfSections()
	{
		$structure = $this->getMenuStructure();

		$fields_structure = array();
		foreach($structure as $section => $data) {
			if (!$data)
				$data = array(strtolower($section));
			foreach($data as $key => $subsection) {
				$subsection = str_replace(" ", "_",strtolower($subsection));
				if (!$subsection)
					$subsection = strtolower($section);
				$fields_structure[$section][] = $subsection;
			}
		}
		return $fields_structure;
	}

	/**
	 * Find section for specified subsection
	 */
	function findSection($subsection)
	{
		$menu = $this->getMenuStructure();
		$subsection = str_replace("_", " ",strtolower($subsection));

		foreach ($menu as $section=>$subsections) {
			foreach ($subsections as $menu_subsection)
				if ($subsection == strtolower($menu_subsection))
					return $section;
		}

		return;
	}

	/**
	 * Returns true if it finds differences between the fields set in FORM and the ones from file (if file is not set from default fields) 
	 */ 
	function haveChanges($structure) 
	{
		$fields_modified = false;

		$response_fields = $this->getApiFields();
		if (!$response_fields)
			return true;

		$fields = $this->applyRequestFields($response_fields, true);

		foreach($structure as $m_section => $data) {
			foreach($data as $key => $m_subsection) {
				foreach($fields[$m_section][$m_subsection] as $param_name => $data) {
					$paramname = str_replace(".", "_", $param_name);
					$val_req = getparam($paramname);
					if ($data["display"] == 'checkbox') {
						$value = $data["value"];
						if ($data["value"] != "on" && $data["value"] != "off")
							$value = $data["value"] == "1" ? "on" : "off";
						$val_req = getparam($paramname) ? getparam($paramname) : "off";
					} elseif ($data["display"] == 'select')
						$value = isset($data[0]["selected"]) ? $data[0]["selected"] : "";	
					else
						$value = isset($data["value"]) ? $data["value"] : "";

					if ($value != $val_req) {
						$fields_modified = true;
						break 3;
					}
				}
			}
		}
		return $fields_modified;
	}

	/** Graphical methods */

	/**
	 * Display form fields with their values.
	 */
	function editForm($section, $subsection, $error=null, $error_fields=array())
	{
		$structure = $this->buildConfSections();

		$response_fields = $this->getApiFields();
		$default_fields = $this->getDefaultFields();

		//if the params are not set in ybts get the default values to be displayed
		if (!$response_fields)  
			$fields = $default_fields;
		else 
			$fields = $this->applyRequestFields($response_fields);
		
		if (strlen($this->error)) {//get fields value from getparam
			foreach($structure as $m_section => $params) {
				foreach ($params as $key => $m_subsection) {
					foreach($fields[$m_section][$m_subsection] as $param_name => $data) {
						$paramname = str_replace(".", "_", $param_name);
						if ($data["display"] == "select") 
							$fields[$m_section][$m_subsection][$param_name][0]["selected"] = getparam($paramname);
						elseif ($data["display"] == "checkbox")
							$fields[$m_section][$m_subsection][$param_name]["value"] = getparam($paramname)=="on"? "1" : "0";
						else
							$fields[$m_section][$m_subsection][$param_name]["value"] = getparam($paramname);

					}
				}
			}
		}

		print "<div id=\"err_$subsection\">";
		error_handle($error, $fields[$section][$subsection],$error_fields);
		print "</div>";
		start_form();
		foreach($structure as $m_section => $data) {
			foreach($data as $key => $m_subsection) {
				$style = $m_subsection == $subsection ? "" : "style='display:none;'";
				
				print "<div id=\"$m_subsection\" $style>";
				if (!isset($fields[$m_section][$m_subsection])) {
					print "Could not retrieve parameters for $m_subsection.";
					print "</div>";
					continue;
				}
				editObject(null,$fields[$m_section][$m_subsection],"Set parameters values for section [$m_subsection] to sent to API.");
				print "</div>";
			}
		}
		addHidden("database");
		end_form();
	}


	/**
	 * Displayes the explanation of each subsection. 
	 */
	function sectionDescriptions()
	{
		$subsection = $this->current_subsection;

		$section_desc = $this->getSectionsDescription();

		foreach ($section_desc as $subsect => $desc) {
			$style = (isset($section_desc[$subsection]) && $subsect == $subsection) ? "" : "style='display:none;'";
			print "<div id=\"info_$subsect\" $style>". $desc ."</div>";
		}
	}

	/**
	 * Build Tabbed Menu and submenu
	 */
	function tabbedMenu()
	{
		$section = $this->current_section;
		$subsection = $this->current_subsection;
		$structure = $this->getMenuStructure();

		foreach ($structure as $first_section=>$subsections) {
			foreach ($subsections as $first_subsection)
				break;
			break;
		}

		//Create the open/close menu structure
		?>
		<script type="text/javascript">
			var subsections = new Array();
			var sect_with_subsect = new Array();
		<?php
		$i = $j = 0; 
		foreach ($structure as $j_section => $j_subsections) {
			$j_section = str_replace(" ", "_",strtolower($j_section));
			if (count($j_subsections)) {
				echo "sect_with_subsect[\"" . $j . "\"]='" . $j_section . "';";
				$j++;
			} else { 
				echo "subsections[\"" . $i . "\"]='" . $j_section . "';";
				$i++;
			}

			foreach ($j_subsections as $key => $j_subsection) {
				if (isset($j_subsection)) {
					$j_subsection = str_replace(" ", "_",strtolower($j_subsection));
					echo "subsections[\"" . $i . "\"]='" . $j_subsection . "';";
					$i++;
				}  
			}
		}

		?>	
		</script>
		<table class="menu" cellspacing="0" cellpadding="0">
		<tr>
		<?php
		$i=0;
		$total = count($structure);
		foreach($structure as $menu => $submenu) {
			$subsect = strtolower($menu);
			if ($menu == $section)
				$css = "open";
			else
				$css = "close";
			print "<td class='menu_$css' id='section_$i' onclick=\"show_submenu_tabs($i, $total, '$subsect')\"";
			// If section that should be opened is the first one then it's ok to hide advanced
			if ($i && $section==$first_section)
				print "style='display:none;'";
			print ">". $menu."</td>";

			print "<td class='menu_space'>&nbsp;</td>";

			if ($i == $total-1) {
				print "<td class='menu_empty'>&nbsp;</td>";
			}
			if (!$i && $section==$first_section) {
				// If section that should be opened is the first one then it's ok to hide advanced and show the Advanced button
				print "<td class='menu_toggle' id='menu_toggle' onclick='toggle_menu();'>Advanced >></td>";
				print "<td class='menu_fill' id='menu_fill'>&nbsp;</td>";
				print "<td class='menu_space'>&nbsp;</td>";
			} elseif (!$i) {
				// Otherwise show them and add the Basic button
				print "<td class='menu_toggle' id='menu_toggle' onclick='toggle_menu();'> << Basic </td>";
				print "<td class='menu_fill' id='menu_fill' style='display:none;'>&nbsp;</td>";
				print "<td class='menu_space'>&nbsp;</td>";
			}

			$i++;
		}
		?>
		</tr>
		</table>
		</td>
		</tr>
		<tr><td class="submenu" id="submenu_line" colspan="2">
		<!--Create the submenu structure -->
		<table class="submenu" cellspacing="0" cellpadding="0">
		<tr> <td>
		<?php
		$css = "open";
		$i = 0;
		foreach($structure as $menu => $submenu) {
			if ($menu == $section) 
				$style = "";
			else
				$style = "style='display:none;'";
			if (count($submenu)<1) {
				$i++;
				continue;
			}
			print "<div class='submenu' id='submenu_$i' $style>";
			foreach ($submenu as $key => $name) {
				if (str_replace(" ", "_",strtolower($name)) == $subsection) 
					$css = "open";
				else
					$css = "close";
				$link = str_replace(" ","_",strtolower($name));
				print "<div class='submenu_$css' id=\"tab_$link\" onclick=\"show_submenu_fields('$link')\">".$name."</a></div>";
				print "<div class='submenu_space'>&nbsp;</div>";
			}
			$i++;
			print '</div>';
		}
		?>
		</td></tr>
		</table>
		<?php
	}

	function displayTabbedSettings()
	{
		?>
		<table class="page" cellspacing="0" cellpadding="0">
		<tr>
		    <td class="menu" colspan="2"><?php $this->tabbedMenu();?></td>
		<tr>
		    <td class="content_form"><?php $this->editForm($this->current_section, $this->current_subsection); ?></td>
		    <td class="content_info"><?php $this->sectionDescriptions(); ?></td>
		</tr>
		<tr><td class="page_space" colspan="2"> &nbsp;</td></tr>
		</table>
		<?php
	}

	function applyFormResults()
	{
		global $module;

		$structure = $this->buildConfSections();

		$fields = array();
		$this->warnings = "";
		$this->error = "";

		$warning_fields = array();
		$error_fields = array();
		foreach ($structure as $m_section => $data) {
			foreach($data as $key => $m_subsection) {

				$this->error_field = array();
				$this->warning_field = array();

				Debug::xdebug($module,"Preparing to validate subsection $m_subsection");
				$res = $this->validateFields($m_section, $m_subsection);

				foreach ($this->warning_field as $key => $err) {
					$this->warnings .=  $err[0]."<br/>";
					$warning_fields[] =  $err[1];
				}
				foreach ($this->error_field as $key => $err) {
					$this->error .=  $err[0]."<br/>";
					$error_fields[] =  $err[1];
				}

				if (!$res[0]) {
					$this->current_section = $m_section;
					$this->current_subsection = $m_subsection;
					$_SESSION["section"] = $m_section;
					$_SESSION["subsection"] = $m_subsection;
					break;
				} else {
					$fields = array_merge($fields, $res["request_fields"]);  
				}

			}
			if (strlen($this->error))
				break;
		}

		if (!strlen($this->error)) {
			// see if there were changes
			$fields_modified = $this->haveChanges($structure);
			if ($fields_modified) {
				$res = $this->storeFormResult($fields);
				if (!$res[0])
					$this->error = $res[1];
			}
		}

		?>
		<table class="page" cellspacing="0" cellpadding="0">
		<tr>
		    <td class="menu" colspan="2"><?php $this->tabbedMenu();?>
		<tr> 
			<td class="content_form"><?php 
			if (strlen($this->error)) {
				if (strlen($this->warnings))
					message("Warning! ".$this->warnings, "no");
				$this->editForm($this->current_section, $this->current_subsection, $this->error, $error_fields);
			} else {
				$message = (!$fields_modified) ? "Finish editing sections. Nothing to update in ybts.conf file." : "Finished configuring BTS.";
				print "<div id=\"notice_".$this->current_subsection."\">";
				message($message, "no");
				print "</div>";

				if (strlen($this->warnings))
					message("Warning! ".$this->warnings,"no");

				$this->editForm($this->current_section, $this->current_subsection);
			}
		?></td>
		    <td class="content_info"><?php $this->sectionDescriptions(); ?></td>
		</tr>
		<tr><td class="page_space" colspan="2"> &nbsp;</td></tr>
		</table>
		<?php
	}
}

?>