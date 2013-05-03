<?php
/**
 * socketconn.php
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

// Class used to open a socket, send and receive information from it
// after connecting the header information send by yate si stripped and you can authentify if required
class SocketConn
{
	var $socket;
	var $error = "";

	function __construct($ip = null, $port = null)
	{
		global $default_ip, $default_port, $rmanager_pass, $socket_timeout;
		global $default_tries;

		$protocol_list = stream_get_transports();

		if (!isset($socket_timeout))
			$socket_timeout = 5;
		$default_tries = $socket_timeout*100;

		if (!$ip)
			$ip = $default_ip;
                if (!$port)
			$port = $default_port;

		if (substr($ip,0,4)=="ssl:" && !in_array("ssl", $protocol_list))
			die("Don't have ssl support.");

		$errno = 0;
		$socket = fsockopen($ip,$port,$errno,$errstr,$socket_timeout);		
		if (!$socket) {
			$this->error = "Can't connect:[$errno]  ".$errstr;
			$this->socket = false;
		} else {
			$this->socket = $socket;
			stream_set_blocking($this->socket,false);
			stream_set_timeout($this->socket,$socket_timeout);
			$line1 = $this->read(); // read and ignore header
			if (isset($rmanager_pass) && strlen($rmanager_pass)) {
				$res = $this->command("auth $rmanager_pass");
				if (substr($res,0,30)!="Authenticated successfully as ") {
					fclose($socket);
					$this->socket = false;
					$this->error = "Can't authenticate: ".$res;
				}
			}
		}
	}

	function write($str)
	{
		fwrite($this->socket, $str."\r\n");
	}

	function read($marker_end = "\r\n",$limited_tries=false)
	{
		global $default_tries;
		if (!$limited_tries)
			$limited_tries = $default_tries; 
		$keep_trying = true;
		$line = "";
		$i = 0;
		//print "<br/>limited_tries=$limited_tries: ";
		while($keep_trying) {
			$line .= fgets($this->socket,8192);
			if($line === false)
				continue;
			usleep(10000); // sleep 10 miliseconds
			if(substr($line, -strlen($marker_end)) == $marker_end)
				$keep_trying = false;
			$i++;
			if ($limited_tries && $limited_tries<=$i)
				$keep_trying = false;
			if ($i>1500) {  // don't try to read for more than 15 seconds
				//print "<br/>------force stop<br/>";
				$keep_trying = false;
			}
		}
		$line = str_replace("\r\n", "", $line);
		return $line;
	}

	function close()
	{
		fclose($this->socket);
	}

	/**
		Commands
		status
		uptime
		reload
		restart
		stop
		.... -> will be mapped into an engine.command
	 */
	function command($command, $marker_end = "\r\n", $limited_tries=false)
	{
		// if after sending command to yate, the page seems to stall it might be because the generated message has not handled or retval was not set
		$this->write($command);
		return $this->read($marker_end,$limited_tries);
	}
}

?>
