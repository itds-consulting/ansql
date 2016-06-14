<?php
/**
 * bts_tabbed_settings.php
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
require_once("ansql/sdr_config/ybts_fields.php");
require_once("ansql/sdr_config/check_validity_fields_ybts.php");
require_once("ansql/sdr_config/create_radio_band_select_array.php");

class BtsTabbedSettings extends TabbedSettings
{
	protected $allow_empty_params = array("Args", "DNS", "ShellScript", "MS.IP.Route", "Logfile.Name", "peer_arg", "RadioFrequencyOffset", "TxAttenOffset", "Radio.RxGain", "my_sip", "reg_sip", "nodes_sip", "gstn_location", "neighbors", "gprs_nnsf_bits", "nnsf_dns", "network_map", "local_breakout" );

	function __construct()
	{
		$this->current_section = "GSM";
		$this->current_subsection = "gsm";
	}

	function getMenuStructure()
	{
		//The key is the MENU alias the sections from $fields 
		//and for each menu is an array that has the submenu data with subsections 
		$structure = array(
			"GSM" => array("GSM","GSM Advanced"),
			"GPRS" => array("GPRS", "GPRS Advanced", "SGSN", "GGSN"),
			"Control" => array(),
			"Transceiver" => array(),
			"Tapping" => array(),
			"Test" => array(),
			"YBTS" => array("YBTS","Security","Roaming","Handover", "GPRS Roaming")
		);

		return $structure;
	}

	function getSectionsDescription()
	{
		return array(
			"gsm" => "Section [gsm] controls basic GSM operation.
			You MUST set and review all parameters here before starting the BTS!",
			"gsm_advanced" => "Section [gsm_advanced] controls more advanced GSM features.
			You normally don't need to make changes in this section",
			"gprs" => "Section [gprs] controls basic GPRS operation.
			You should review all parameters in this section.",
			"gprs_advanced" => "Section [gprs_advanced] controls more advanced GPRS features.
			You normally don't need to make changes in this section.",
			"ggsn" => "Section [ggsn] has internal GGSN function configuration.",
			"sgsn" => "Section [sgsn] has internal SGSN function configuration.",
			"control" => "Section [control] - configuration for various MBTS controls.
			You normally don't need to change anything in this section.",
			"transceiver" => "Section [transceiver] controls how the radio transceiver is started and operates.",
			"tapping" => "Section [tapping] - settings control if radio layer GSM and GPRS packets are tapped to Wireshark.",
			"test" => "Section [test] has special parameters used to simulate errors.",
			"ybts" => "Section [ybts] configures ybts related parameters.",
			"security" => "Section [security] configures security related parameters.",
			"roaming" => "Section [roaming] controls parameters used by roaming.js when connecting YateBTS to a core network.",
			"handover" => "Section [handover] controls handover parameters used by roaming.js.",
			"gprs_roaming" => "Section [gprs_roaming] controls parameters used by dataroam.js when connecting YateBTS to a core data network."
		);
	}

	function getApiFields()
	{
		$response_fields = request_api(array(), "get_bts_node", "node");
		if (!isset($response_fields["ybts"]))
			return null;

		return $response_fields["ybts"];
	}

	function getDefaultFields()
	{
		return get_default_fields_ybts();
	}
    
	/**
	 * Build form fields by applying response fields over default fields
	 * @param $request_fields Array. Fields retrived using getApiFields
	 * @param $exists_in_response Bool. Verify if field exists in $request_fields otherwise add special marker. Default false
	 * @return Array
	 */
	function applyRequestFields($request_fields=null,$exists_in_response = false)
	{
		$structure = $this->buildConfSections(); //get_fields_structure_from_menu();
		$fields = $this->getDefaultFields();

		if (!$request_fields)
			return $fields;

		foreach($structure as $section => $data) {
			foreach($data as $key => $subsection) {
				if ($exists_in_response) {
					if (!isset($request_fields[$subsection])) {
						$fields["not_in_response"] = true;
						break 2;
					}

					foreach($fields[$section][$subsection] as $paramname => $data_fields) {
						$allow_empty_params = array("Args", "DNS", "ShellScript", "MS.IP.Route", "Logfile.Name", "peer_arg");
						if (!in_array($paramname, $allow_empty_params) && !isset($request_fields[$subsection][$paramname])) {
							$fields["not_in_response"] = true;	
							break 3;
						}
					}
				}
			}
		}

		$network_map = "";
		foreach($structure as $section => $data) {
			foreach($data as $key => $subsection) {
				if (isset($request_fields[$subsection])) {
					foreach($request_fields[$subsection] as $param => $data) {
						if (!isset($fields[$section][$subsection]))
							continue;
						if ($subsection=="gprs_roaming" && Numerify($param)!="NULL") {
							if (strlen($network_map))
								$network_map .= "\r\n";
							$network_map .= "$param=$data";
							continue;
						}
						if ($subsection=="gprs_roaming" && $param=="nnsf_bits")
							$param = "gprs_nnsf_bits";
						if (!isset($fields[$section][$subsection][$param])) 
							continue;

						if ($fields[$section][$subsection][$param]["display"] == "select") {
							if ($data=="" && in_array("Factory calibrated", $fields[$section][$subsection][$param][0]))
								$data = "Factory calibrated";
							$fields[$section][$subsection][$param][0]["selected"] = $data;
						} elseif ($fields[$section][$subsection][$param]["display"] == "checkbox") 
							$fields[$section][$subsection][$param]["value"] = ($data == "yes" || $data=="on" || $data=="1")  ? "on" : "off";
						else 
							$fields[$section][$subsection][$param]["value"] = $data; 
					}
				}
			}
		}
		if (strlen($network_map)) 
			$fields["YBTS"]["gprs_roaming"]["network_map"]["value"] = $network_map;

		if (isset($fields['GSM']['gsm']['Radio.Band'][0]["selected"])) {
			$particle = $fields['GSM']['gsm']['Radio.Band'][0]["selected"];
			$fields['GSM']['gsm']["Radio.C0"][0]["selected"] = "$particle-".$fields['GSM']['gsm']["Radio.C0"][0]["selected"];
		}
		return $fields;
	}

	function validateFields($section, $subsection)
	{
		$parent_res = parent::validateFields($section, $subsection);
		if (!$parent_res[0])
			return $parent_res;

		$extra_validations = array(
			array("mode"=>"roaming", "subsection"=>"roaming",      "cb"=>"validate_roaming_params"),
			array("mode"=>"dataroam","subsection"=>"gprs_roaming", "cb"=>"validate_dataroam_params"),
			array("mode"=>"dataroam","subsection"=>"roaming",      "cb"=>"validate_piece_roaming")
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

	function storeFormResult($fields)
	{
		//if no errors encountered on validate data fields then send API request

		$c0 = $fields['gsm']['Radio.C0'];
		$c0 = explode("-",$c0);
		$c0 = $c0[1];
		$fields['gsm']['Radio.C0'] = $c0;

		$fields['gprs_roaming']['nnsf_bits'] = $fields['gprs_roaming']['gprs_nnsf_bits'];
		unset($fields['gprs_roaming']['gprs_nnsf_bits']);


		$network_map = $fields['gprs_roaming']['network_map'];
		$network_map = explode("\r\n",$network_map);
		unset($fields['gprs_roaming']['network_map']);
		foreach ($network_map as $assoc) {
			$assoc = explode("=",$assoc);
			if (count($assoc)!=2)
				continue;
			$fields['gprs_roaming'][$assoc[0]] = trim($assoc[1]);
		}

		$fields = array("ybts"=>$fields);
		$res = make_request($fields, "set_bts_node");

		if (!isset($res["code"]) || $res["code"]!=0) {
			// find subsection where error was detected so it can be opened
			$pos_section = strrpos($res["message"],"'",-15);
			$subsection = substr($res["message"],$pos_section+1);
			$subsection = substr($subsection,0,strpos($subsection,"'"));

			$section = $this->findSection($subsection);
			if (!$section) {
				$section = "GSM";
				$subsection = "gsm";
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