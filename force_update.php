#! /usr/bin/php -q
<?php
/**
 * force_update.php
 * This file is part of the FreeSentral Project http://freesentral.com
 *
 * FreeSentral - is a Web Graphical User Interface for easy configuration of the Yate PBX software
 * Copyright (C) 2008-2009 Null Team
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 */

/*
 * To use make symlink to it from main project directory (it can't be run from this directory)
 *
 * >> ln -s ansql/force_update.php force_update.php
 * >> ./force_update.php
 */

require_once ('ansql/lib.php');
require_once ('ansql/framework.php');

include_classes();
require_once ('ansql/set_debug.php');


$stdout = (php_sapi_name() == 'cli') ? 'php://stdout' : 'web';
if (isset($logs_in)) {
	if (is_array($logs_in)) {
		if (!in_array($stdout,$logs_in))
			$logs_in[] = $stdout;
	} elseif ($logs_in!=$stdout)
		$logs_in = array($logs_in, $stdout);
} else 
	$logs_in = $stdout;

$res = Model::updateAll();
if ($res)
	Debug::Output("Succesfully performed sql structure update.");
// Error is printed from framework
//else
//	Debug::Output("Errors update sql structure. Please see above messages.");

?>
