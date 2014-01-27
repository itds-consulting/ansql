<?php
#include ANSQL project
if (is_dir("../../ansql"))
	$ansql_path = "../../";
elseif (is_dir("../ansql/"))
	$ansql_path = "../";
else
	exit("Could not locate ansql library.");

set_include_path( get_include_path().PATH_SEPARATOR.$ansql_path);
require_once("ansql/lib_files.php");


JsObjFile::runUnitTests();

?>