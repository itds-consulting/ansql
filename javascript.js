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
