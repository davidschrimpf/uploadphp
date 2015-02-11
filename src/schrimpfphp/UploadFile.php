<?php
namespace schrimpfphp;

class UploadFile
{
	protected $destination; // protected means these can't be accessed outside this class definition.
	protected $messages = array();
	protected $maxSize = 153600;
	protected $permittedTypes = array(
		'image/jpeg',
		'image/gif',
		'image/png' // array of file types that are accepted for uploading.
	);

	protected $newName;
	protected $typeCheckingOn = true;
	protected $notTrusted = array('bin', 'cgi', 'exe', 'js', 'pl', 'php', 'py', 'sh');
	protected $suffix = '.upload'; // suffix to be added to files that aren't trusted.
	protected $renameDuplicates;

	public function __construct($uploadFolder)
	{
		if (!is_dir($uploadFolder) || !is_writeable($uploadFolder)) { // checks that the upload folder is a dir and is writeable.
			throw new \Exception("$uploadFolder must be a valid writeable folder.");
		}
		if ($uploadFolder[strlen($uploadFolder)-1] != '/') { // checks that upload folder ends with a trailing slash.
			$uploadFolder .= '/';
		}
		$this->destination = $uploadFolder;
	}

	public function setMaxSize($bytes)
	{
		$serverMax = self::convertToBytes(ini_get('upload_max_filesize'));
		if ($bytes > $serverMax) {
			throw new \Exception('Maximum size cannot exceed server-set limit, which is ' . self::convertFromBytes($serverMax));
		}
		if (is_numeric($bytes) && $bytes > 0) { // if $bytes is a number and greater than 0.
			$this->maxSize = $bytes;
		}
	}

	public static function convertToBytes($val)
	{
		$val = trim($val);
		$last - strtolower($val[strlen($val)-1]);
		if (in_array($last, array('g', 'm', 'k'))) {
			switch ($last) {
				case 'g':
					$val *= 1024;
				case 'm':
					$val *= 1024;
				case 'k':
					$val *= 1024;
			}
		}
		return $val;
	}

	public static function convertFromBytes($bytes)
	{
		$bytes /= 1024;
		if ($bytes > 1024) {
			return number_format($bytes/1024, 1) . ' MB';
		} else {
			return number_format($bytes, 1) . ' KB';
		}
	}

	public function allowAllTypes($suffix = null)
	{
		$this->typeCheckingOn = false;
		if (!is_null($suffix)) { // there is a suffix, need to check it.
			if (strpos($suffix, '.') === 0 || $suffix == '') { // check if suffix begins with a dot. 0 means that the dot is right at beginning of string.
				$this->suffix = $suffix; // if it has the suffix dot, just assign this suffix to $suffix
			} else {
				$this->suffix = ".$suffix"; // otherwise add the dot to $suffix.
			}
		}
	}

	public function upload($renameDuplicates = true)
	{
		$this->renameDuplicates = $renameDuplicates;
		$uploaded = current($_FILES); // gets the current element of the _FILES superglobal array.
		if (is_array($uploaded['name'])) {
			foreach ($uploaded['name'] as $key => $value) {
				$currentFile['name'] = $uploaded['name'][$key];
				$currentFile['type'] = $uploaded['type'][$key];
				$currentFile['tmp_name'] = $uploaded['tmp_name'][$key];
				$currentFile['error'] = $uploaded['error'][$key];
				$currentFile['size'] = $uploaded['size'][$key];
				if ($this->checkFile($currentFile)) {
				$this->moveFile($currentFile);
				}
			}
		} else {
			if ($this->checkFile($uploaded)) {
				$this->moveFile($uploaded);
			}
		}
	}

	public function getMessages()
	{
		return $this->messages;
	}

	protected function checkFile($file)
	{
		if ($file['error'] != 0) { // if error does not equal zero, there's a problem.
			$this->getErrorMessage($file); // Passed to getErrorMessage to deal with problem.
			return false;
		}
		if (!$this->checkSize($file)) { // checks size based on checkSize method below.
			return false;
		}
		if ($this->typeCheckingOn) {
			if (!$this->checkType($file)) { // checks type based on checkType method below.
				return false;
			}
		}
		$this->checkName($file);
		return true;
	}

	protected function getErrorMessage($file)
	{
		switch($file['error']) { // Checks the value or error.
			case 1:
			case 2:
				$this->messages[] = $file['name'] . ' is too large: (max: ' . self::convertFromBytes($this->maxSize) . ').'; // if error is 1 or 2, file is too big.
				break;
			case 3:
				$this->messages[] = $file['name'] . ' is too large.';
			case 4:
				$this->messages[] = "You didn't choose a file.";
				break;
			default: // If it's not one of the above errors, show generic error message.
				$this->messages[] = 'Sorry, something went wrong when trying to upload ' . $file['name'];
				break;

 		}
	}

	protected function checkSize($file)
	{
		if  ($file['size'] == 0) {
			$this->messages[] = $file['name'] . ' is empty.';
			return false;
		} elseif ($file['size'] > $this->maxSize) {
			$this->messages[] = $file['name'] . ' exceeds maximum size for a file to be uploaded which is ' . self::convertFromBytes($this->maxSize);
			return false;
		} else {
			return true;
		}
	}

	protected function checkType($file)
	{
		if (in_array($file['type'], $this->permittedTypes)) {
			return true;
		} else {
			$this->messages[] = $file['name'] . ' is not a permitted file type.';
			return false;
		}
	}

	protected function checkName($file)
	{
		$this->newName = null; // since the class handles multiple file uploads, newName must be set to null before each run.
		$nospaces = str_replace(' ', '_', $file['name']); // replaces spaces with underscores in the filename.
		if ($nospaces != $file['name']) { // check whether the name has been changed. Compare nospaces with filename.
			$this->newName = $nospaces; // if the above is not equal, name has been changed, so assign newName to the new name with no spaces.
		}
		$nameparts = pathinfo($nospaces); //use pathinfo to get the filename extension.
		$extension = isset($nameparts['extension']) ? $nameparts['extension'] : ''; // if extension has been set, assign it to extension. If not, make it an empty string.
		if (!$this->typeCheckingOn && !empty($this->suffix)) {
			if (in_array ($extension, $this->notTrusted) || empty($extension)) {
				$this->newName = $nospaces . $this->suffix;
			}
		}
		if ($this->renameDuplicates) {
			$name = isset($this->newName) ? $this-newName : $file['name'];
			$existing = scandir($this->destination); // will return an array of names in the folder.
			if (in_array ($name, $existing)) { // if the name is in the existing arrange, need to rename.
				$i = 1; // initialize a counter;
				do {
					$this->newName = $nameparts['filename'] . '_' . $i++;
					if (!empty($extension)) {
						$this->newName .= ".$extension";
					}
					if (in_array($extension, $this->notTrusted)) {
						$this->newName .= $this->suffix;
					}
				} while (in_array($this->newName, $existing)); // checks if the name exists already or not. If so, loop again to add a number to the name with i++.
			}
		}
	}

	protected function moveFile($file)
	{
		$filename = isset($this->newName) ? $this->newName : $file['name'];
		$success = move_uploaded_file($file['tmp_name'], $this->destination . $filename);
		if ($success) {
			$result = $file['name'] . ' was uploaded';
			if (!is_null($this->newName)) { // if newName is not null, we know renaming occured.
				$result .= ', and was renamed ' . $this->newName;
			}
			$result .= '.'; // add a period to the end of the sentence.
			$this->messages[] = $result;
		} else {
			$this->messages[] = 'Could not upload ' . $file['name'];
		}
	}
}