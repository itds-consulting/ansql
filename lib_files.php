<?php
require_once("ansql/base_classes.php");

class GenericFile extends GenericStatus
{
	protected $filename;
	protected $read_handler;
	protected $write_handler;

	function __construct($file_name)
	{
		$this->filename = $file_name;
	}

	function openForRead()
	{
		if (isset($this->read_handler))
			return;

		if(!is_file($this->filename)) {
			$this->setError("File doesn't exists");
		} else {
			$this->read_handler = fopen($this->filename,"r");
			if (!$this->read_handler)
				$this->setError("Could not open file for reading.");
		}
	}

	function openForWrite()
	{
		if (isset($this->write_handler))
			return;

		$this->write_handler = fopen($this->filename,"w");
		if (!$this->write_handler)
			$this->setError("Could not open file for writting.");
	}

	function getHandler($type="r")
	{
		if ($type == "r")
			$this->openForRead();
		elseif ($type == "w")
			$this->openForWrite();

		if (!$this->status())
			return;

		return ($type=="r") ? $this->read_handler : $this->write_handler;
	}

	function close()
	{
		if (isset($this->read_handler)) {
			fclose($this->read_handler);
			unset($this->read_handler);
		}
		if (isset($this->write_handler)) {
			fclose($this->write_handler);
			unset($this->write_handler);
		}
	}

	function createBackup()
	{
		$backup = $this->filename.".tmp";

		if (!file_exists($this->filename))
			return;

		if (!copy($this->filename, $backup))
			$this->setError("Failed to create backup of existing file: ".$this->filename);
	}

	function restoreBackup()
	{
		$backup_file = $this->filename.".tmp";

		if (!file_exists($backup_file))
			return;

		if (!copy($backup_file, $this->filename))
			return array(false, "Failed to restore backup for file: ".$dest);

		$this->removeBackup();
	}

	function removeBackup()
	{
		$bk_file = $this->filename.".tmp";

		if (file_exists($bk_file))
			if (!unlink($bk_file))
				$this->setError("Failed to remove backup file ".$bk_file);
	}

	function safeSave($content=NULL)
	{
		$this->createBackup();
		if (!$this->status())
			return;

		if (!method_exists($this,"save"))
			return $this->setError("Please implement 'save' method for class ".get_class($this));

		$this->save($content);
		if (!$this->status()) {
			$this->restoreBackup();
			return;
		}

		$this->removeBackup();
	}
}

class ConfFile extends GenericFile
{
	public $sections = array();
	public $structure = array();
	public $chr_comment = array(";","#");
	public $initial_comment = null;
	public $write_comments = false;

	function __construct($file_name,$read=true,$write_comments=true)
	{
		parent::__construct($file_name);
		$this->write_comments = $write_comments;

		if ($read)
			$this->read();
	}

	function read($close=true)
	{
		$this->openForRead();
		if (!$this->status())
			return;

		$last_section = "";
		while(!feof($this->read_handler))
		{
			$row = fgets($this->read_handler);
			$row = trim($row);
			if (!strlen($row))
				continue;
			if ($row == "")
				continue;
			// new section started
			// the second paranthesis is kind of weird but i got both cases
			if (substr($row,0,1)=="[" && substr($row,-1,1)) {
				$last_section = substr($row,1,strlen($row)-2);
				$this->sections[$last_section] = array();
				$this->structure[$last_section] = array();
				continue;
			}
			if (in_array(substr($row,0,1),$this->chr_comment)) {
				if ($last_section == "")
					array_push($this->structure, $row);
				else
					array_push($this->structure[$last_section], $row);
				continue;
			}
			// this is not a section (it's part of a section or file does not have sections)
			$params = explode("=", $row, 2);
			if (count($params)>2 || count($params)<2)
				// skip row (wrong format)
				continue;
			if ($last_section == ""){
				$this->sections[$params[0]] = trim($params[1]);
				$this->structure[$params[0]] = trim($params[1]);
			} else {
				$this->sections[$last_section][$params[0]] = trim($params[1]);
				$this->structure[$last_section][$params[0]] = trim($params[1]);
			}
		}
		if ($close)
			$this->close();
	}

	public function save()
	{
		global $write_comments;

		$this->openForWrite();
		if (!$this->status())
			return;

		$wrote_something = false;
		if ($this->initial_comment)
			fwrite($this->write_handler, $this->initial_comment."\n");

		foreach($this->structure as $name=>$value)
		{
			// make sure we don't write the initial comment over and over
			if ($this->initial_comment && !$wrote_something && in_array(substr($value,0,1),$this->chr_comment) && $write_comments)
				continue;
			if (!is_array($value)) {
				if(in_array(substr($value,0,1),$this->chr_comment) && is_numeric($name)) {

					//writing a comment
					if ($this->write_comments)
						fwrite($this->write_handler, $value."\n");
					continue;
				}
				$wrote_something = true;
				fwrite($this->write_handler, "$name=".ltrim($value)."\n");
				continue;
			}else
				fwrite($this->write_handler, "[".$name."]\n");
			$section = $value;
			foreach($section as $param=>$value)
			{
				if (is_array($value)) {
					foreach($value as $key => $val)
						fwrite($this->write_handler, $param."=".ltrim($val)."\n");
				} else {
					//writing a comment
					if (in_array(substr($value,0,1),$this->chr_comment) && is_numeric($param)) {
						if ($this->write_comments)
							fwrite($this->write_handler, $value."\n");
						continue;
					}

					$wrote_something = true;
					fwrite($this->write_handler, "$param=".ltrim($value)."\n");
				}
			}
			fwrite($this->write_handler, "\n");
		}
	}
}

?>