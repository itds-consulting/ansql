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

function toggle_column(element)
{
	form = document.forms["current_form"];
	for(var z=0; z<form.length;z++) {
		if (form[z].type != 'checkbox')
			continue;
		if (form[z].disabled == true)
			continue;
		form[z].checked = element.checked;
	}
}

function show_all_tabs(count_sections,partids)
{
	if (partids==null)
		partids="";
	var img = document.getElementById("img_show_tabs"+partids);
	if (img.src.substr(-12)=="/sm_show.png")
		img.src = "images/sm_hide.png";
	else
		img.src = "images/sm_show.png";
	var i;
	for (i=1; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+partids+i);
		section_fill = document.getElementById("fill_"+partids+i);
		if (section_tab==null) {
			alert("Don't have section tab for "+partids+i);
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

function show_section(section_name,count_sections,partids,custom_css)
{
	//alert(section_name);

	if (partids==null)
		partids="";
	if (custom_css==null)
		custom_css="";

	var i, section_div, section_tab;
	for (i=0; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+partids+i);
		section_div = document.getElementById("sect_"+partids+i);
		if (section_tab==null) {
			alert("Don't have section tab for "+i);
			continue;
		}
		if (section_div==null) {
			alert("Don't have section div for "+i);
			continue;
		}
		if (i==section_name) {
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

function show_hide_comment(id)
{
	var fontvr = document.getElementById("comment_"+id);
	if(fontvr == null)
		return;
	if (fontvr.style.display == "none")
		fontvr.style.display = "block";
	else
		if(fontvr.style.display == "block")
			fontvr.style.display = "none";
}

function show_hide(element)
{
	var div = document.getElementById(element);

	if (div.style.display == "none") {
		if(div.tagName == "TR")
			div.style.display = (ie > 1 && ie<8) ? "block" : "table-row";//"block";//"table-row";
		else
			if(div.tagName == "TD")
				div.style.display = (ie > 1 && ie<8) ? "block" : "table-cell";
			else
				if (div.tagName=="IMG")
					div.style.display="";
				else
					div.style.display = "block";
	}else{
		div.style.display = "none";
	}
}

function submit_form(formid)
{
	document.getElementById(formid).submit();
}

function get_selected(id_name)
{
	var selector_obj = document.getElementById(id_name);
	if (selector_obj==null)
		return null;
	var sel = selector_obj.options[selector_obj.selectedIndex].value || selector_obj.options[selector_obj.selectedIndex].text;
	return sel;
}

function set_html_obj(id, html)
{
        var obj = document.getElementById(id);
        if (obj)
                obj.innerHTML = (html == null) ? "" : html;
}

function make_request(url, cb)
{
        url = encodeURI(url);
        make_api_request(url, cb);
}

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
                        call_function(cb,response);
                }
        }
        xmlhttp.open("GET", url, true);
        xmlhttp.send(null);
}

function call_function(cb, response)
{
        if (cb && typeof(cb) === "function") {
                // execute the callback, passing parameters as necessary
                cb(response);
        } else
                console.error("Trying to call invalid callback "+cb.toString()+", type for it is: "+typeof(cb));
}

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
		return next_step(step_no, wizard_name);
	}

	var err = "";
	// variable required_fields is a global array 
	// it is usually created from method requiredFieldsJs() from Wizard class

	var field_name, field_value;
	for (field_name in required_fields) {
		field_value = window.document.getElementById(field_name).value;
		if (field_value=="")
			err += "Please set "+required_fields[field_name]+"! ";
	}
	if (err!="") {
		error(err);
		return false;
	}
	return true;
}
