/**
 * Copyright (C) 2008-2014 Null Team
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

/** 
 * Get Internet Explorer Version
 * @return the version of Internet Explorer or a -1 (indicating the use of another browser)
 */
function getInternetExplorerVersion()
{
	var rv = -1; // Return value assumes failure.
	if (navigator.appName == 'Microsoft Internet Explorer') {
		var ua = navigator.userAgent;
		var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
		if (re.exec(ua) != null)
			rv = parseFloat( RegExp.$1 );
	}
	return rv;
}

//var ie = getInternetExplorerVersion();

/**
 * Show hidden element 
 * Function tries to set the right  style.display depending on the tag type
 * @param element_id String. Id of the element to be displayed
 */
function show(element_id)
{
	if (typeof ie == 'undefined' || ie==null)
		ie = getInternetExplorerVersion();

	var element = document.getElementById(element_id);
	if (element == null)
		return;

	if (element.tagName == "TR")
		element.style.display = (ie > 1 && ie<8) ? "block" : "table-row";
	else
		if (element.tagName == "TD")
			element.style.display = (ie > 1 && ie<8) ? "block" : "table-cell";
		else
			element.style.display = "block";
}

/**
 * Hide element
 * Function sets style.display to 'none'.
 * @param element_id String. Id of the element to be hidden
 */ 
function hide(element_id)
{
	if (typeof ie == 'undefined' || ie==null)
		ie = getInternetExplorerVersion();

	var element = document.getElementById(element_id);
	if (element == null)
		return;
	element.style.display = "none";
}

/**
 * Show/hide advanced fields in a form and change src and title associated 
 * to the image clicked to perform this action
 * This function is used from editObject() from lib.php is used with fields marked as advanced.
 * @param identifier String. In case there are multiple forms in a single page, 
 * all elements from a form should start with this identified
 */
function advanced(identifier)
{
	var form = document.getElementById(identifier);

	var elems = (form!=null) ? form.elements : [];
	var elem_name;

	for (var i=0;i<elems.length;i++) {
		elem_name = elems[i].name;
		if (identifier.length>elem_name.length && elem_name.substr(0,identifier.length)!=identifier)
			continue;

		show_hide("tr_"+elem_name);
	}

	var img = document.getElementById(identifier+"advanced");
	if (img!=null && img.tagName=="img") {
		var imgsrc= img.src;
		var imgarray = imgsrc.split("/");
		if (imgarray[imgarray.length-1] == "advanced.jpg") {
			imgarray[imgarray.length-1] = "basic.jpg";
			img.title = "Hide advanced fields";
		} else {
			imgarray[imgarray.length-1] = "advanced.jpg";
			img.title = "Show advanced fields";
		}

		img.src = imgarray.join("/");
	} else
		Console.log("advanced() was called, but img='"+img+"' tagName='"+img.tagName+"'");
}

/**
 * Check/Uncheck all checkboxes from form containing the element
 * Usually used from tableOfObjects() from lib.php
 * @param element. 'Select all' checkbox whose checked state dictates the state of the other checkboxes
 */
function toggle_column(element)
{
	var containing_form = parent_by_tag(element, "form");
	if (containing_form==null)
		return;

	for(var z=0; z<containing_form.length;z++) {
		if (containing_form[z].type != 'checkbox')
			continue;
		if (containing_form[z].disabled == true)
			continue;
		containing_form[z].checked = element.checked;
	}
}

/**
 * Retrieve containing tag of certain type where element resides.
 * @param element Object. Element whose containing element you need
 * Note! This is not the id of the element but the element itself
 * @param tagname String. Lowercase value of the tag type of the desired container
 * @return first parent element with specified tagType or null if not found
 */
function parent_by_tag(element, tagname)
{
        while(true) {
                parent_element = element.parentElement;
                if (parent_element==null)
                        return null;
		
		if (parent_element.tagName.toLowerCase()==tagname)
			return parent_element;

                element = parent_element;
        }
}

/*
 * Show/Hide tabs 
 * Used from generic_tabbed_settings() from lib.php
 * @param count_sections Integer. Total number of tabs
 * @param part_ids String. Particle identifying specific elements to be showed/hidden.
 * Defaults to ''
 */
function show_all_tabs(count_sections,part_ids)
{
	if (part_ids==null)
		part_ids = "";
	var img = document.getElementById("img_show_tabs"+part_ids);
	if (img.src.substr(-12)=="/sm_show.png")
		img.src = "images/sm_hide.png";
	else
		img.src = "images/sm_show.png";
	var i;
	for (i=1; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+part_ids+i);
		section_fill = document.getElementById("fill_"+part_ids+i);
		if (section_tab==null) {
			alert("Don't have section tab for "+part_ids+i);
			continue;
		}
		if (section_tab.style.display=="") {
			section_tab.style.display = "none";
			if (section_fill!=null)
				section_fill.style.display = "";
		} else {
			section_tab.style.display = "";
			if (section_fill!=null)
				section_fill.style.display = "none";
		}
	}
}

/**
 * Show a section when clicked - when tabs are used.
 * Function sets classname to  "section_selected basic "+custom_css+"_selected" for first section when selected
 * or "section_selected "+custom_css+"_selected" in case another one is selected
 * and to "section basic "+custom_css when first section is closed
 * and to "section "+custom_css when another section is closed
 * @param section_index Integer. Number of the section to be shown. 
 * @param count_sections Integer. Total number of tabs
 * @param part_ids String. Particle identifying specific elements to be showed/hidden.
 * Defaults to ''
 * @param custom_css String. Name of custom css class
 */
function show_section(section_index,count_sections,part_ids,custom_css)
{
	if (part_ids==null)
		part_ids="";
	if (custom_css==null)
		custom_css="";

	var i, section_div, section_tab;
	for (i=0; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+part_ids+i);
		section_div = document.getElementById("sect_"+part_ids+i);
		if (section_tab==null) {
			Console.log("Don't have section tab for "+i);
			continue;
		}
		if (section_div==null) {
			Console.log("Don't have section div for "+i);
			continue;
		}
		if (i==section_index) {
			if (i==0)
				section_tab.className = "section_selected basic "+custom_css+"_selected";
			else
				section_tab.className = "section_selected "+custom_css+"_selected";
			section_div.style.display = "";
		} else {
			cls = section_tab.className;
			if (cls.substr(0,16)=="section_selected") {
				if (i==0)
					section_tab.className = "section basic "+custom_css;
				else
					section_tab.className = "section "+custom_css;
				section_div.style.display = "none";
			}
		}
	}
}

/**
 * Show/hide comment associated to a field
 * @param id String. Id of the object whose comment should be shown
 */
function show_hide_comment(id)
{
	show_hide("comment_"+id);
}

/**
 * Show/hide element
 * @param element_id String. Id of the element to to shown/hidden
 */ 
function show_hide(element_id)
{
	var element = document.getElementById(element_id);
	if (element==null)
		return;

	if (typeof ie == 'undefined')
		ie = getInternetExplorerVersion();

	if (element.style.display=="none") {
		if (element.tagName=="TR")
			element.style.display = (ie > 1 && ie<8) ? "block" : "table-row";//"block";//"table-row";
		else
			if (element.tagName=="TD")
				element.style.display = (ie > 1 && ie<8) ? "block" : "table-cell";
			else
				if (element.tagName=="IMG")
					element.style.display = "";
				else
					element.style.display = "block";
	} else {
		element.style.display = "none";
	}
}

/**
 * Make HTTP request to specified url.
 * encodeURI function is used on url before using @make_api_request function
 * @param url String. Where to make request to
 * @param cb Callback. If set, call it passing response from HTTP request as argument
 */
function make_request(url, cb)
{
        url = encodeURI(url);
        make_api_request(url, cb);
}

/**
 * Make HTTP request to specified url. 
 * If callback cb is defined, call with by passing response from HTTP request as parameter
 * Use @make_reques function to make sure url is encoded since this function assumes it already was encoded.
 * @param url String. Where to make request to
 * @param cb Callback. If set, call it passing response from HTTP request as argument
 */
function make_api_request(url, cb)
{
        xmlhttp = GetXmlHttpObject();
        if (xmlhttp == null) {
                alert("Your browser does not support XMLHTTP!");
                return;
        }

        xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4) {
                        var response = xmlhttp.responseText;
			if (typeof cb != 'undefined' && cb !== null)
				call_function(cb,response);
                }
        }
        xmlhttp.open("GET", url, true);
        xmlhttp.send(null);
}

/**
 * Make callback 
 * @param cb Callback. Name of the function to be called
 * @param param. Parameter to be passed when calling cb
 */
function call_function(cb, param)
{
        if (cb && typeof(cb) === "function") {
                // execute the callback, passing parameters as necessary
                cb(param);
        } else
                console.error("Trying to call invalid callback "+cb.toString()+", type for it is: "+typeof(cb));
}

/**
 * Retrieve object used to make HTTP request.
 * @returns object. Function returns new XMLHttpRequest or ActiveXObject("Microsoft.XMLHTTP") depending on browser or null if none of the two is available
 */
function GetXmlHttpObject()
{
        if (window.XMLHttpRequest)
        {
                /* code for IE7+, Firefox, Chrome, Opera, Safari*/
                return new XMLHttpRequest();
        }
        if (window.ActiveXObject)
        {
                /* code for IE6, IE5*/
                return new ActiveXObject("Microsoft.XMLHTTP");
        }
        return null;
}

/**
 * Check if required fields were set.
 * The required_fields global variable is checked
 * Ex: required_fields={"username":"username", "contact_info":"Contact information"}
 * "contact_info" is the actual field_name in the form while "Contact information" is what the user sees associated to that form element
 * @return Bool. True when required fields are set, false otherwise 
 * If required_fields is undefined then function returns true and a message is logged in console
 */
function check_required_fields()
{
	if (typeof(required_fields) === "undefined") {
		console.log("The required fields are not defined!");
		return false;
	}

	var err = "";
	// variable required_fields is a global array 
	// it is usually created from method requiredFieldsJs() from Wizard class

	var field_name, field_value;
	for (field_name in required_fields) {
		field_value = document.getElementById(field_name).value;
		if (field_value=="")
			err += "Please set "+required_fields[field_name]+"! ";
	}
	if (err!="") {
		error(err);
		return false;
	}
	return true;
}

/**
 * Display error. Function currently used alert.
 * @param error String. Error to be displayed to the user
 */
function error(error)
{
	alert(error);
}

/**
 * Verify if value is numeric. Used isNaN js function.
 * @param val String. Value to be checked
 * @return Bool
 */
function is_numeric(val)
{
	return !isNaN(val);  
}

/**
 * Delete element
 * @param id String. Id of the tag to remove
 */
function delete_element(id)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.parentNode.removeChild(obj);
}

/**
 * Change object/tag id
 * @param id String. Current id of the tag
 * @param new_id String.
 */
function set_id_obj(id, new_id)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.id = new_id;
}

/**
 * Set value of object/tag
 * @param id String. Id of the tag 
 * @param val String. Value to be set in tag with specified id
 */
function set_val_obj(id, val)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.value = val;
}

/**
 * Submit form 
 * @param formid String. Id of the form to submit
 */
function submit_form(formid)
{
	var form_to_submit = document.getElementById(formid);
	if (form_to_submit)
		form_to_submit.submit();
}

/** 
 * Retrieve selected value from dropdown
 * @param id_name String. The id of the select tag 
 * @return String with selected option or null if tag with it is not found or no value is selected
 */
function get_selected(id_name)
{
	var selector_obj = document.getElementById(id_name);
	if (selector_obj==null)
		return null;
	var sel = selector_obj.options[selector_obj.selectedIndex].value || selector_obj.options[selector_obj.selectedIndex].text;
	return sel;
}

/** 
 * Sets innerHTML for specific tag
 * @param id String. Id of the tag to set content into
 * @param html String. The content to be set in the tag specified by id
 */
function set_html_obj(id, html)
{
        var obj = document.getElementById(id);
        if (obj)
                obj.innerHTML = (html == null) ? "" : html;
}
