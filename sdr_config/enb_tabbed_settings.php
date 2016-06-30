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

class EnbTabbedSettings extends TabbedSettings
{
	protected $allow_empty_params = array("addr4", "addr6", "reportingPath", "radio_driver", "address", "port", "pci", "earfcn", "broadcast", "max_pdu", "AddrSubnet", "AuxRoutingTable", "Band", "mme_address", "local", "streams", "dscp", "UplinkRbs", "UplinkRbStartIndex",  "PrachResponseDelay");

	protected $default_section = "radio";
	protected $default_subsection = "enodeb";

	function __construct()
	{
		$this->current_section = $this->default_section;
		$this->current_subsection = $this->default_subsection;
		$this->open_tabs = 2;
	}

	function getMenuStructure()
	{
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		//The key is the MENU alias the sections from $fields 
		//and for each menu is an array that has the submenu data with subsections 
		$structure = array(
			"Radio" => array("EnodeB", "Bearers"),
			"Core" => array("GTP", "MME"/*, "S1AP"*/),
			"Hardware" => array(),
			"System" => array("System information","Advanced", "Scheduler", "RadioHardware", "Measurements"),
			"Access channels" => array("PRACH", "PDSCH", "PUSCH", "PUCCH", "PDCCH"),
			// TBI! Define how sections under developers can be set
			//"Developers" => array("Radio", "General", "Uu-simulator", "Uu-loopback", "Test-Enb", "Test-scheduler")
		);

		return $structure;
	}

	function getSectionsDescription()
	{
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		return array(
			"enodeb" => "These are the most basic configuration parameters and the ones most likely to be changed. They are gathered in this section to make them easy to find.", //basic section from file
			"bearers" => "Bearer parameters (RLC and PDCP layers)",

			"gtp" => "S1-U interface parameters
<br/><br/>
GTP tunnel type; must be either gtp-access (for user mode GTP-U) or gtp-tunnel (for kernel mode GTP-U). <br/>
Note: the kernel mode GTP-U requires the GTP-U kernel driver.",
			
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
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		$response_fields = request_api(array(), "get_enb_node", "node");
		if (!isset($response_fields["openenb"])) {
			Debug::xdebug("tabs_enb", "Could not retrieve openenb fields in ".__METHOD__);
			return null;
		}

		$res = $response_fields["openenb"];
		if (isset($response_fields["gtp"])) {
			$gtp_settings = $response_fields["gtp"];
		} else {
			$gtp_settings = array();
			Debug::xdebug("tabs_enb", "Could not retrieve gtp fields in ".__METHOD__);
		}
		$res["gtp"] = $gtp_settings;

		if (isset($response_fields["satsite"])) {
			$hardware_settings = $response_fields["satsite"];
		} else {
			$hardware_settings = array();
			Debug::xdebug("tabs_enb", "Could not retrieve satsite/hardware fields in ".__METHOD__);
		}
		$res["hardware"] = $hardware_settings;
		// rename some of the sections the parameters appear under: basic->enodeb, basic-> all subsections under access channels, basic-> system_information
		$aggregated_subsections = array("enodeb","system_information","pdsch","pusch","pucch","prach","pdcch");
		foreach ($aggregated_subsections as $subsection)
			$res[$subsection] = $res["basic"];

		return $res;
	}

	function getDefaultFields()
	{
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		return get_default_fields_enb();
	}

	/**
	 * Build form fields by applying response fields over default fields
	 * @param $request_fields Array. Fields retrived using getApiFields
	 * @param $exists_in_response Bool. Verify if field exists in $request_fields otherwise add special marker. Default false
	 * @return Array
	 */
	function applyRequestFields($request_fields=null,$exists_in_response = false)
	{
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		$structure = $this->buildConfSections();
		$fields = $this->getDefaultFields();

		if (!$request_fields)
			return $fields;

		foreach($structure as $section => $data) {
			foreach($data as $key => $subsection) {
				if (isset($request_fields[$subsection])) {
					foreach($request_fields[$subsection] as $param => $data) {
						if (!isset($fields[$section][$subsection]))
							continue;

						if (!isset($fields[$section][$subsection][$param])) 
							continue;

						if (isset($fields[$section][$subsection][$param]["display"]) && $fields[$section][$subsection][$param]["display"] == "select") {
							if ($data=="" && in_array("Factory calibrated", $fields[$section][$subsection][$param][0]))
								$data = "Factory calibrated";
							$fields[$section][$subsection][$param][0]["selected"] = $data;
						} elseif (isset($fields[$section][$subsection][$param]["display"]) && $fields[$section][$subsection][$param]["display"] == "checkbox") 
							$fields[$section][$subsection][$param]["value"] = ($data == "yes" || $data=="on" || $data=="1")  ? "on" : "off";
						else 
							$fields[$section][$subsection][$param]["value"] = $data; 
					}
				}
			}
		}

		return $fields;
	}

	function validateFields($section, $subsection)
	{
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

		$parent_res = parent::validateFields($section, $subsection);
		if (!$parent_res[0])
			return $parent_res;

		$extra_validations = array(
			/*array("mode"=>"roaming", "subsection"=>"roaming",      "cb"=>"validate_roaming_params"),
			array("mode"=>"dataroam","subsection"=>"gprs_roaming", "cb"=>"validate_dataroam_params"),
			array("mode"=>"dataroam","subsection"=>"roaming",      "cb"=>"validate_piece_roaming")*/
		);

		foreach ($extra_validations as $validation) {
			if (getparam("mode")==$validation["mode"] && $subsection==$validation["subsection"]) {
				$res = $validation["cb"]();
				if (!$res[0])
					$this->error_field[] = array($res[1], $res[2][0]);
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
	function storeFormResult($fields)
	{
		//if no errors encountered on validate data fields then send API request
		Debug::func_start(__METHOD__,func_get_args(),"tabs_enb");

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
			$custom_mme = $fields["mme"];
			$custom_mme["address"] = $custom_mme["mme_address"];
			unset($custom_mme["mme_address"]);
			$fields["mme1"] = $custom_mme;
		}
		unset($fields["mme"]);

		if ($satsite)
			$request_fields["satsite"] = $satsite;
		$request_fields["openenb"] = array_merge($request_fields["openenb"],$fields);
//		$request_fields["gtp"] = $gtp;

		$res = make_request($request_fields, "set_enb_node");

		if (!isset($res["code"]) || $res["code"]!=0) {
			// find subsection where error was detected so it can be opened
			$mess = substr($res["message"],-15);
			$pos_section = strrpos($mess,"'",-5);
			$subsection = substr($mess,$pos_section+1);
			$subsection = substr($subsection,0,strpos($subsection,"'"));

			$section = $this->findSection($subsection);
			if (!$section) {
				Debug::output('enb tabs',"Could not find section for subsection '$subsection'");
				$section = $this->default_section;
				$subsection = $this->default_subsection;
			}

			$this->current_subsection = $subsection;
			$this->current_section = $section;
			$_SESSION["subsection"] = $subsection;
			$_SESSION["section"] = $section;

			return array(false, $res["message"]);
		} else {
			unset($_SESSION["section"], $_SESSION["subsection"]);
			return array(true);
		}
	}
}


?>