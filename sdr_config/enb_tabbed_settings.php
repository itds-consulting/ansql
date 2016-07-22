<?php
/**
 * enb_tabbed_settings.php
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

require_once("ansql/tabbed_settings.php");
require_once("ansql/sdr_config/enb_fields.php");
require_once("ansql/sdr_config/check_validity_fields_enb.php");

class EnbTabbedSettings extends TabbedSettings
{
	protected $allow_empty_params = array("addr4", "addr6", "reportingPath", "radio_driver", "address", "port", "pci", "earfcn", "broadcast", "max_pdu", "AddrSubnet", "AuxRoutingTable", "Band", "streams", "dscp", "UplinkRbs", "UplinkRbStartIndex",  "PrachResponseDelay","mme_address_2", "local_2", "streams_2", "dscp_2","mme_address_3", "local_3", "streams_3", "dscp_3","mme_address_4", "local_4", "streams_4", "dscp_4","mme_address_5", "local_5", "streams_5", "dscp_5");

	protected $default_section    = "radio";
	protected $default_subsection = "enodeb";
	protected $title = "ENB";

	function __construct()
	{
		$this->current_section    = $this->default_section;
		$this->current_subsection = $this->default_subsection;
		$this->open_tabs = 2;
	}

	function getMenuStructure()
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		//The key is the MENU alias the sections from $fields 
		//and for each menu is an array that has the submenu data with subsections 
		$structure = array(
			"Radio" => array("EnodeB", "Bearers"),
			"Core" => array("GTP", "MME"/*, "S1AP"*/),
			"Hardware" => array(),
			"System" => array("System information", "Advanced", "Scheduler", "RadioHardware", "Measurements"),
			"Access channels" => array("PRACH", "PDSCH", "PUSCH", "PUCCH", "PDCCH"),
			// TBI! Define how sections under developers can be set
			//"Developers" => array("Radio", "General", "Uu-simulator", "Uu-loopback", "Test-Enb", "Test-scheduler")
		);

		return $structure;
	}

	function getSectionsDescription()
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		return array(
			"enodeb" => "These are the most basic configuration parameters and the ones most likely to be changed. They are gathered in this section to make them easy to find.", //basic section from file
			"bearers" => "Bearer parameters (RLC and PDCP layers)",

			"gtp" => "S1-U interface parameters",
			
			"mme" => "Hand-configured MME.
		    The eNodeB normally selects an MME using DNS,
		    but explicit MME selection is also possible.
		    This is an example of an explicit MME configuration.

		    This section configures an MME:
		    address = 192.168.56.62
		    local = 192.168.56.1
		    streams = 5
		    dscp = expedited",

			"scheduler" => "Parameters related to the MAC scheduler",
			"advanced" => 'Here, "advanced" just means parameters that are not normally changed.
		    It is not a reference to LTE-Advanced features.',
			"measurements" => "KPI-related performance measurements",
			"radiohardware" => "Control parameters for the lower PHY",

		/*	"radio" => "These are parameters for configuring the radio device",
			"general" => "Global configuration for the ENB Yate Module",
			"uu-simulator" => "Configuration parameters for the Uu interface simulator",
			"uu-loopback" => "Configuration parameters for the UeConnection test fixture",
			"test-enb" => "This section controls special test modes of the eNodeB."*/
		);
	}

	// Retrieve settings using get_enb_node and build array with renamed sections to match interface fields
	function getApiFields()
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		$response_fields = request_api(array(), "get_enb_node", "node");
		if (!isset($response_fields["openenb"])) {
			Debug::xdebug("tabs_enb", "Could not retrieve openenb fields in " . __METHOD__);
			return null;
		}

		$res = $response_fields["openenb"];
		if (isset($response_fields["gtp"]["sgw"])) {
			$gtp_settings = $response_fields["gtp"]["sgw"];
		} else {
			$gtp_settings = array();
			Debug::xdebug("tabs_enb", "Could not retrieve gtp fields in " . __METHOD__);
		}
		$res["gtp"] = $gtp_settings;

		if (isset($response_fields["satsite"])) {
			$hardware_settings = $response_fields["satsite"];
		} else {
			$hardware_settings = array();
			Debug::xdebug("tabs_enb", "Could not retrieve satsite/hardware fields in " . __METHOD__);
		}
		$res["hardware"] = $hardware_settings;

		// set mme fields
		foreach ($response_fields["openenb"] as $section_name=>$section_def) {
			if (substr($section_name,0,3)!="mme")
				continue;
			$index = substr($section_name, 3);
			if ($index=="1") {
				$res["mme"] = $section_def;
				$res["mme"]["mme_address"] = $res["mme"]["address"];
				unset($res["mme"]["address"]);
			} else {
				foreach ($section_def as $param_name=>$param_value)
					$res["mme"][$param_name."_$index"] = $param_value;
				$res["mme"]["mme_address"."_$index"] = $res["mme"]["address_$index"];
				unset($res["mme"]["address_$index"]);
			}
		}

		// rename some of the sections the parameters appear under: basic->enodeb, basic-> all subsections under access channels, basic-> system_information
		$aggregated_subsections = array("enodeb","system_information","pdsch","pusch","pucch","prach","pdcch");
		foreach ($aggregated_subsections as $subsection)
			$res[$subsection] = $res["basic"];

		return $res;
	}

	function getDefaultFields()
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		return get_default_fields_enb();
	}

	/**
	 * Build form fields by applying response fields over default fields
	 * @param $request_fields Array. Fields retrived using getApiFields
	 * @param $exists_in_response Bool. Verify if field exists in $request_fields otherwise add special marker. Default false
	 * @return Array
	 */
	function applyRequestFields($request_fields=null,$exists_in_response=null)
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		$structure = $this->buildConfSections();
		$fields    = $this->getDefaultFields();

		if (!$request_fields)
			return $fields;

		$trigger_names = array("mme"=>"add_mme_");

		foreach ($structure as $section=>$data) {
			foreach ($data as $key=>$subsection) {
				if (isset($request_fields[$subsection])) {
					foreach ($request_fields[$subsection] as $param=>$data) {
						if (!isset($fields[$section][$subsection]))
							continue;

						if (!isset($fields[$section][$subsection][$param])) 
							continue;

						if (isset($fields[$section][$subsection][$param]["display"]) && $fields[$section][$subsection][$param]["display"]=="select") {

							if ($data=="" && in_array("Factory calibrated", $fields[$section][$subsection][$param][0]))
								$data = "Factory calibrated";
							$fields[$section][$subsection][$param][0]["selected"] = $data;

						} elseif (isset($fields[$section][$subsection][$param]["display"]) && $fields[$section][$subsection][$param]["display"] == "checkbox")
 
							$fields[$section][$subsection][$param]["value"] = ($data=="yes" || $data=="on" || $data=="1")  ? "on" : "off";

						else 
							$fields[$section][$subsection][$param]["value"] = $data; 


						// unmark triggered fields if they are set
						if ((strlen($data) || getparam($param)) && isset($trigger_names[$subsection])) {
							$trigger_name = $trigger_names[$subsection];

							if (isset($fields[$section][$subsection][$param]["triggered_by"]) && ctype_digit($fields[$section][$subsection][$param]["triggered_by"])) {

								$triggered_by   = $fields[$section][$subsection][$param]["triggered_by"];
								$former_trigger = $triggered_by - 1;

								if (isset($fields[$section][$subsection][$trigger_name.$former_trigger]))
									unset($fields[$section][$subsection][$trigger_name.$former_trigger]);

								$fld = $fields[$section][$subsection];

								foreach ($fld as $fldn=>$fldd) {
									if (isset($fldd["triggered_by"]) && $fldd["triggered_by"]==$triggered_by) {
										unset($fields[$section][$subsection][$fldn]["triggered_by"]);
									}
								}

								unset($fields[$section][$subsection][$param]["triggered_by"]);
							}
						}

					}
				}
			}
		}

		return $fields;
	}

	function validateFields($section, $subsection)
	{
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		$parent_res = parent::validateFields($section, $subsection);
		if (!$parent_res[0]) 
			return $parent_res;
		
		$extra_validations = array(
			array("subsection"=>"gtp",  "cb"=>"validate_gtp_params"),
			array("subsection"=>"mme",  "cb"=>"validate_mme_params")
		);

		foreach ($extra_validations as $validation) {
			if ($subsection==$validation["subsection"]) {
				$res = call_user_func($validation["cb"]);
				if (!$res[0])
					$this->error_field[]   = array($res[1], $res[2][0]);
				elseif ($res[0] && isset($res[1]))
					$this->warning_field[] = array($res[1], $res[2][0]);
			}
		}

		if (count($this->error_field))
			return array(false, "fields"=>$parent_res["fields"], "request_fields"=>$parent_res["request_fields"]);
		return $parent_res;
	}

	// Send API request to save ENB configuration
	// Break error message in case of error and mark section/subsection to be opened
	function storeFormResult(array $fields)
	{
		//if no errors encountered on validate data fields then send API request
		Debug::func_start(__METHOD__, func_get_args(), "tabs_enb");

		$request_fields = array("openenb"=>array("basic"=>array()));//, "gtp"=>array());

		$basic_sections = array("enodeb","system_information","pdsch","pusch","pucch","prach","pdcch");
		foreach ($basic_sections as $basic_section) {
			$request_fields["openenb"]["basic"] = array_merge($request_fields["openenb"]["basic"], $fields[$basic_section]);
			unset($fields[$basic_section]);
		}

		$gtp = $fields["gtp"];
		unset($fields["gtp"]);

		if (isset($fields["hardware"])) {
			$satsite = $fields["hardware"];
			unset($fields["hardware"]);
		} else
			$satsite = null;

		if (strlen($fields["mme"]["mme_address"])) {

			$index = 1;
			$mme_fields = array("mme_address"=>"address","local","streams","dscp");

			while(true) {
				$suffix = ($index>1) ? "_$index" : "";
				$custom_mme = array();
				if (isset($fields["mme"]["mme_address$suffix"]) && strlen($fields["mme"]["mme_address$suffix"])) {
					foreach ($mme_fields as $field_index=>$field_name) {
						$form_name = (is_numeric($field_index)) ? $field_name.$suffix : $field_index.$suffix;
						$custom_mme[$field_name] = $fields["mme"][$form_name];
					}
					$fields["mme$index"] = $custom_mme;
					$index++;
				} else
					break;
			}
		}
		unset($fields["mme"]);

		if ($satsite)
			$request_fields["satsite"] = $satsite;

		$request_fields["openenb"]    = array_merge($request_fields["openenb"],$fields);
		$request_fields["gtp"]["sgw"] = $gtp;

		$res = make_request($request_fields, "set_enb_node");

		if (!isset($res["code"]) || $res["code"]!=0) {
			// find subsection where error was detected so it can be opened
			$mess        = substr($res["message"],-15);
			$pos_section = strrpos($mess,"'",-5);
			$subsection  = substr($mess,$pos_section+1);
			$subsection  = substr($subsection,0,strpos($subsection,"'"));
			if (substr($subsection,0,3)=="mme")
				$subsection = "mme";

			$section = $this->findSection($subsection);
			if (!$section) {
				Debug::output('enb tabs', "Could not find section for subsection '$subsection'");
				$section    = $this->default_section;
				$subsection = $this->default_subsection;
			}

			$this->current_subsection = $subsection;
			$this->current_section    = $section;
			$_SESSION[$this->title]["subsection"]   = $subsection;
			$_SESSION[$this->title]["section"]      = $section;

			return array(false, $res["message"]);
		} else {
			unset($_SESSION[$this->title]["section"], $_SESSION[$this->title]["subsection"]);
			return array(true);
		}
	}
}


?>
