<?php
/**
 * debug.php
 * This file is part of the FreeSentral Project http://freesentral.com
 *
 * FreeSentral - is a Web Graphical User Interface for easy configuration of the Yate PBX software
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

// true/false
// if true then all debug messages are remembered unless they are set in $debug_tags to be excluded
// if false then only the tags listed in $debug_tags are included
$debug_all = true;

// array of tags that should be included or excluded from debug
// a tag can't contain white spaces
$debug_tags = array("paranoid","in_framework");

// default tag if tag is not specified
$default_tag = "logic";

/*
// options to notify in case a report was triggered
$debug_notify = array(
    "mail" => array("email@domain.com", "email2@domain.com"),
    "web"  => array("notify", "dump")
);
*/
$debug_notify = array("web"=>array("notify"));

if(is_file("defaults.php"))
	include_once("defaults.php");
if(is_file("config.php"))
	include_once("config.php");

class Debug
{
	/**
	 * Used when entering a function
	 * Usage: Debug::func_start(__FUNCTION__,func_get_args()); -- in functions
	 * Usage: Debug::func_start(__METHOD__,func_get_args(),"framework");  -- in methods
	 * Output: Entered function_name(0 => array (0 => 'val1',1 => 'val2'),1 => NULL,2 => 'param3')
	 * @param $func Function name
	 * @param $args Array of arguments
	 * @param $tag String Optional. If not set $default_tag is used
	 */
	public static function func_start($func,$args,$tag=null)
	{
		global $default_tag;
		if (!$tag)
			$tag = $default_tag;

		Debug::xdebug($tag,"Entered ".$func."(".Debug::format_args($args).")");
	}

	/**
	 * Function triggers the sending of a bug report
	 * Current supported methods: mail, web (dump or notify)
	 * Ex:
	 * $debug_notify = array(
	 * 	"mail" => array("email@domain.com", "email2@domain.com"),
	 * 	"web"  => array("notify", "dump")
	 * );
	 * 'mail' send emails with the log file as attachment or the xdebug log directly if logging to file was not configured
         * 'dump' dumps the xdebug log on the web page
	 * 'notify' Sets 'triggered_error' in $_SESSION. Calling method button_trigger_report() will print a notice on the page
	 *
	 * The report can be triggered by the developper directly when detecting an abnormal situation
	 * or by the user that uses the 'Send bug report' button
	 *
	 * @param $tag String associated Used only when triggered from code
	 * @param $message String Used only when triggered from code
	 * If single parameter is provided and string contains " "(spaces) then it's assumed 
	 * the default tag is used. Default tag is 'logic'
	 */
	public static function trigger_report($tag,$message=null)
	{
		global $debug_notify;
		global $server_email_address;
		global $logs_in;
		global $proj_title;
		global $module;

		if ($tag) 
			self::xdebug($tag,$message);

		// save xdebug
		$xdebug = $_SESSION["xdebug"];
		self::dump_xdebug();

		foreach($debug_notify as $notification_type=>$notification_options) {
			if (!count($notification_options))
				continue;
			switch ($notification_type) {
				case "mail":
					if (!isset($server_email_address))
						$server_email_address = "bugreport@localhost.lan";

					$subject = "Bug report for '".$proj_title."'";

					$ip = $_SERVER['SERVER_ADDR']." (".$_SERVER['SERVER_NAME'].")";
					$body = "Application is running on ".$ip."\n";

					$user = $_SESSION["username"];
					$reporter = getparam("name");
					if ($reporter)
						$user .= "($reporter)";
					$body .= "User: ".$user/"\n";

					$description = getparam("bug_description");
					if ($description)
						$body .= "User description: ".$description."\n";

					$attachment = ($logs_file = self::get_log_file()) ? array(array("file"=>$logs_file,"content_type"=>"text/plain")) : false;
					if (!$attachment)
						// logs are not kept in file, add xdebug to email body
						$body .= "\n\n$xdebug";

					for ($i=0; $i<count($notification_options); $i++)
						send_mail($notification_options[$i], $server_email_address, $subject, $body, $attachment,null,false);

					break;
				case "web":
					for ($i=0; $i<count($notification_options); $i++) {
						switch ($notification_options[$i]) {
							case "notify":
								$_SESSION["triggered_report"] = true;
								break;
							case "dump":
								print "<pre>";
								if (!in_array("web",$logs_in))
									// of "web" is not already in $logs_in
									// then print directly because otherwise it won't appear on screen
									print $xdebug;
								print "</pre>";
								print "<a class='llink' href='".$_SESSION["main"]."?module=$module&method=clear_triggered_error'>Clear</a></div>";
								break;
						}
					}
					break;
			}
		}
	}

	/**
	 * Contacts $message to $_SESSION["xdebug"].
	 * The writting of this log can be triggered or not.
	 * Unless users report a bug or code reaches a developer triggered report,
	 * this log is lost when user session is closed.
	 * @param $tag String associated Used only when triggered from code
	 * @param $message String Used only when triggered from code
	 * If single parameter is provided and string contains " "(spaces) then it's assumed 
	 * the default tag is used. Default tag is 'logic'
	 */
	public static function xdebug($tag, $message=null)
	{
		global $default_tag;
		global $debug_all;
		global $debug_tags;

		if (!isset($_SESSION["xdebug"]))
			$_SESSION["xdebug"] = "";

		if ($message==null && strpos($tag," ")) {
			$message = $tag;
			$tag = $default_tag;
		}
		if (!$message) {
			self::output("Error in Debug::debug() tag=$tag, empty message in .");
			return;
		}
		if ( ($debug_all==true && !in_array($tag,$debug_tags)) || 
		     ($debug_all==false && in_array($tag,$debug_tags)) ) {
			$date = gmdate("[D M d H:i:s Y]");
			$_SESSION["xdebug"].= "\n$date".strtoupper($tag).": ".$message;
		}
	}

	/**
	 * Logs/Prints a nice formated output of PHP debug_print_backtrace()
	 */
	public static function trace()
	{
		$trace = self::debug_string_backtrace(__METHOD__);
		$trace = print_r($trace,true);
		self::output("------- Trace\n".$trace);
	}

	/**
	 * Logs/Prints a message
	 * output is controled by $logs_in setting
	 * Ex: $logs_in = array("web", "/var/log/applog.txt", "php://stdout");
	 * 'web' prints messages on web page
	 * If $logs_in is not configured default is $logs_in = array("web")
	 * @param $tag String Tag for the message
	 * @param $msg String Message to pe logged/printed
	 */
	public static function output($tag,$msg=NULL)
	{
		global $logs_in;

		// log output in xdebug as well
		// if xdebug is written then this log will be duplicated
		// but it will help debugging to have it inserted in appropriate place in xdebug log
		self::xdebug($tag,$msg);
		if ($msg==null && strpos($tag," ")) {
			$msg = $tag;
			$tag = "output";
		}
		if (!$msg) {
			self::output("error", "Error in Debug::debug() tag=$tag, empty message in .");
			return;
		}

		if (!isset($logs_in))
			$logs_in = "web";

		$arr = $logs_in;
		if(!is_array($arr))
			$arr = array($arr);

		for ($i=0; $i<count($arr); $i++) {
			if ($arr[$i] == "web") {
				print "<br/>\n<br/>\n$msg<br/>\n<br/>\n";
			} else {
				$date = gmdate("[D M d H:i:s Y]");
				if (!is_file($arr[$i]))
					$fh = fopen($arr[$i], "w");
				else
					$fh = fopen($arr[$i], "a");
				fwrite($fh, $date.strtoupper($tag).": ".$msg."\n");
				fclose($fh);
			}
		}
	}

	/**
	 * Outputs xdebug log in file of web page depending on $logs_in
	 */
	public static function dump_xdebug()
	{
		$xdebug = "------- XDebug:".$_SESSION["xdebug"];
		Debug::output($xdebug);
		// reset debug to make sure we don't dump same information more than once
		$_SESSION["xdebug"] = "";
	}

	/**
	 * Contacts array of arguments and formats then returning a string
	 * @param $args Array of function arguments
	 * @return String with nicely formated arguments
	 */
	public static function format_args($args)
	{
		$res = str_replace("\n","",var_export($args,true));
		$res = str_replace("  ","",$res);
		$res = str_replace(",)",")",$res);
		// exclude 'array ('
		$res = substr($res,7);
		// exclude last ', )'
		$res = substr($res,0,strlen($res)-1);
		return $res;
	}

	/**
	 * Looks in $logs_in and returns a log file if set. It ignores 'web' and entry containing 'stdout'
	 * @return String Log File 
	 */
	public static function get_log_file()
	{
		global $logs_in;
    
		$count_logs = count($logs_in);
		for ($i=0; $i<$count_logs; $i++) {
			if ($logs_in[$i]=="web" || strpos($logs_in[$i],"stdout"))
				continue;
			return $logs_in[$i];
		}
		return false;
	}

	/**
	 * Clears "triggered_report" variable from session
	 */
	public static function clear_triggered_error()
	{
		unset($_SESSION["triggered_report"]);
	}

	/**
	 * Returns output of PHP debug_print_backtrace after stripping out name and name provided in @exclude
	 * @param String Function name to exclude from trace
	 * @return String Trace
	 */
	private static function debug_string_backtrace($exclude=null) 
	{
		ob_start();
		debug_print_backtrace();
		$trace = ob_get_contents();
		ob_end_clean();

		$exclude = ($exclude) ? array(__METHOD__,$exclude) : array(__METHOD__);
		for ($i=0; $i<count($exclude); $i++) {
			// Remove first item from backtrace as it's this function which
			// is redundant.
			$trace = preg_replace ('/^#0\s+' . $exclude[$i] . "[^\n]*\n/", '', $trace, 1);
			// Renumber backtrace items.
			$trace = preg_replace ('/^#(\d+)/me', '\'#\' . ($1 - 1)', $trace);
		}

		return $trace;
	}

	// METHODS THAT COME AFTER THIS USE FUNCTIONS FROM lib.php THAT WAS NOT REQUIRED IN THIS FILE
	// IN CASE YOU WANT TO USE THE Debug CLASS SEPARATELY YOU NEED TO REIMPLEMENT THEM

	public static function button_trigger_report()
	{
		global $debug_notify;
		global $module;

		print "<div class='trigger_report'>";
		if (isset($debug_notify["mail"]) && count($debug_notify["mail"]))
			print "<a class='llink' href='main.php?module=".$module."&method=form_bug_report'>Send bug report</a>";
		if (isset($_SESSION["triggered_report"]) && $_SESSION["triggered_report"]==true && isset($debug_notify["web"]) && in_array("notify",$debug_notify["web"]))
			print "<div class='triggered_error'>!ERROR <a class='llink' href='".$_SESSION["main"]."?module=$module&method=clear_triggered_error'>Clear</a></div>";
		print "</div>";
	}

	public static function form_bug_report()
	{
		$fields = array(
			"bug_description"=>array("display"=>"textarea", "compulsory"=>true, "comment"=>"Description of the issue. Add any information you might consider relevant or things that seem weird.<br/><br/>This report will be sent by email message."),

			"name"=>array()
		);

		start_form();
		addHidden(null,array("method"=>"send_bug_report"));
		editObject(null,$fields,"Send bug report","Send");
		end_form();
	}

	public static function send_bug_report()
	{
		$report = getparam("bug_description");
		$from = "From: ".getparam("name");
		$report = "$from\n\n$report";
		self::trigger_report("REPORT",$report);
	}
}
?>