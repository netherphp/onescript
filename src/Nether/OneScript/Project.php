<?php

namespace Nether\OneScript;
use \Nether;

use \Exception;
use \ReflectionProperty;

class Project {

	const ErrorFileUnreadable = 1;
	const ErrorFileUnwritable = 2;
	const ErrorFileInvalid = 3;

	////////////////////////////////
	////////////////////////////////

	public $Files = [];
	/*//
	@type array[string, ...]
	these are the files which will be appended to the output first. this is
	because maybe they are important for the module files to have their core
	framework loaded, and whatnot - fifo.
	//*/

	public $Directories = ['libs'];
	/*//
	@type array[string, ...]
	the directories in the project root for which we will search for
	additional files to append to the output.
	//*/

	public $Extensions = ['js'];
	/*//
	@type array[string, ...]
	the file extensions we will use to filter files by when searching for
	additional files to append to the output.
	//*/

	////////
	////////

	public $OutputFile = 'onescript.js';
	/*//
	@type string
	the filename to save the compiled script as.
	//*/

	public $OutputMinFile = 'onescript.min.js';
	/*//
	@type string
	the filename to save the minified compiled script as.
	//*/

	////////
	////////

	public $Print = false;
	/*//
	@type bool
	if true the output of the compiled file will be sent out to stdout. this
	is for if you are using a live .js.php type file thing on your dev to
	automatically recompile every save.
	//*/

	public $Minify = false;
	/*//
	if true the output of the compiled file will be run through the minify
	tool from https://www.npmjs.com/package/minifier and saved written to
	disk as the $OutputMinFile.
	//*/

	public $AddScriptHeader = true;
	/*//
	if true it will waste bytes outputting a header at the top of the build
	file that describes the build.
	//*/

	public $AddFileHeader = true;
	/*//
	if true it will waste bytes outputting a header that separates each file
	from eachother. this of course will be stripped out in the minified
	version.
	//*/

	////////////////////////////////
	////////////////////////////////

	protected $ProjectFile;
	/*//
	@type string
	//*/

	public function
	GetProjectFile() { return $this->ProjectFile; }

	public function
	SetProjectFile($p) { $this->ProjectFile = $p; return $this; }

	////////////////////////////////
	////////////////////////////////

	public function
	__construct($config=null) {
	/*//
	build a project from the input data.
	//*/

		$config = new Nether\Object(
			$config,
			$this->GetPublicProperties(),
			['DefaultKeysOnly']
		);

		foreach($config as $prop => $val)
		$this->{$prop} = $val;

		return;
	}

	public function
	__toString() {
	/*//
	@return string
	asking for the project in a string context is going to give you a json
	dump of the public things.
	//*/

		return json_encode(
			$this->GetPublicProperties(),
			JSON_PRETTY_PRINT
		).PHP_EOL;
	}

	////////////////////////////////
	////////////////////////////////

	protected function
	GetPublicProperties() {
	/*//
	@return array
	fetch the properties i have decided are safe to write to disk without
	privacy issues. e.g. all publics on this class.
	//*/

		$output = [];
		foreach($this as $prop => $val) {
			$ref = new ReflectionProperty(static::class,$prop);
			if($ref->isPublic()) $output[$prop] = $val;
		}

		ksort($output);
		return $output;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	FindTheFiles() {
	/*//
	@return array
	search the project for all the files that need to be compiled down
	into the final build.
	//*/

		return array_merge(
			$this->FindTheFiles_Main(),
			$this->FindTheFiles_Libs()
		);
	}

	protected function
	FindTheFiles_Main() {
	/*//
	@return array
	check the main files that were specified in the project and make sure
	that they exist. returns an array with the full file paths (relative to
	how the app needs to care) to all the files when verified.
	//*/

		$output = [];
		$root = dirname($this->ProjectFile);

		foreach($this->Files as $filename) {
			$filepath = "{$root}/src/{$filename}";

			if(!file_exists($filepath))
			throw new Exception(
				"file src/{$filename} not found",
				static::ErrorFileUnreadable
			);

			$output[] = $filepath;
		}

		return $output;
	}

	protected function
	FindTheFiles_Libs() {
	/*//
	@return array
	check all the optional module folders for files that match the extension
	that we want to automatically append to the end of the build.
	//*/

		$output = [];
		$root = dirname($this->ProjectFile);

		foreach($this->Directories as $dir) {
			$finder = new Nether\OneScript\FileFinder(
				"{$root}/src/{$dir}",
				$this->Extensions
			);

			foreach($finder as $filepath)
			$output[] = $filepath;
		}

		sort($output);
		return $output;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Build() {
	/*//
	compile the files down to the final form.
	//*/

		$source = '';
		$filelist = $this->FindTheFiles();
		$outfile = sprintf(
			'%s/%s',
			dirname($this->ProjectFile),
			$this->OutputFile
		);

		if($this->AddScriptHeader) $this->AppendScriptHeader(
			$filelist,
			$source
		);

		foreach($filelist as $filepath)
		$this->AppendFile($filepath,$source);

		if($this->OutputFile) {
			if(!$this->WriteToDisk($outfile,$source)) {
				if($this->Print) echo '// ';
				echo "output unchanged - not writing to disk.",PHP_EOL;
				if($this->Print) echo PHP_EOL;
			}
		}

		if($this->Print) {
			header('Content-type: application/json');
			echo $source, PHP_EOL;
		}

		return;
	}

	////////////////////////////////
	////////////////////////////////

	protected function
	AppendFile($filename,&$buffer) {
	/*//
	append the specified file to the end of the source buffer.
	optionally adds the file header if enabled.
	//*/

		if($this->AddFileHeader)
		$this->AppendFileHeader($filename,$buffer);

		$this->AppendFileContents($filename,$buffer);
		return;
	}

	protected function
	AppendFileHeader($filename,&$buffer) {
	/*//
	//*/

		$filename = trim(str_replace(
			dirname($this->ProjectFile),'',
			$filename
		),'\\/');

		$buffer .= str_repeat('/',75).PHP_EOL;
		$buffer .= "// {$filename} ";
		$buffer .= str_repeat('/',(71-strlen($filename)));
		$buffer .= PHP_EOL.PHP_EOL;

		return;
	}

	protected function
	AppendFileContents($filename,&$buffer) {
	/*//
	//*/

		$buffer .= trim(file_get_contents($filename));
		$buffer .= PHP_EOL.PHP_EOL;

		return;
	}

	protected function
	AppendScriptHeader($files,&$buffer) {
	/*//
	//*/

		foreach($files as &$file)
		$file = trim(str_replace(
			dirname($this->ProjectFile),'',
			$file
		),'\\/');

		$buffer .= '/*// nether-onescript //'.PHP_EOL;
		$buffer .= '@date '.date('Y-m-d H:i:s').PHP_EOL;
		$buffer .= '@files '.json_encode($files,JSON_PRETTY_PRINT).PHP_EOL;
		$buffer .= '//*/'.PHP_EOL.PHP_EOL;
		return;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	ShouldWriteToDisk($filename,$source) {
	/*//
	//*/

		if(!file_exists($filename))
		return true;

		$old = md5(static::StripScriptHeader(file_get_contents($filename)));
		$new = md5(static::StripScriptHeader($source));

		if($old !== $new) return true;
		else return false;
	}

	public function
	WriteToDisk($filename,$source) {
	/*//
	//*/

		if(!$this->ShouldWriteToDisk($filename,$source))
		return false;

		if(!file_exists($filename)) {
			if(!static::MakeDirectory(dirname($filename)))
			throw new Exception(
				'unable to create directory for output file.',
				static::ErrorFileUnwritable
			);
		}

		if(file_exists($filename) && !is_writable($filename))
		throw new Exception(
			'unable to write to output file.',
			static::ErrorFileUnwritable
		);

		file_put_contents($filename,$source);
		return true;
	}

	////////////////////////////////
	////////////////////////////////
	
	public function
	Bootstrap() {
	/*//
	//*/
	
		if(!$this->ProjectFile)
		throw new Exception('no project file set');	
		
		////////
		////////	
		
		$dir = dirname($this->ProjectFile);
	
		// make main source directory.
		static::MakeDirectory("{$dir}/src");
		
		// make module directories.
		foreach($this->Directories as $libdir)
		static::MakeDirectory("{$dir}/src/{$libdir}");

		// make blank mainfiles.		
		foreach($this->Files as $mainfile)
		touch("{$dir}/src/{$mainfile}");
			
		return $this;
	}

	public function
	Save($file=null) {
	/*//
	@return self
	save the project json file to disk.
	//*/

		if(!$file) $file = $this->ProjectFile;

		if(!file_exists($file) && !is_writable(dirname($file)))
		throw new Exception(
			'unable to write to project directory',
			static::ErrorFileUnwritable
		);

		if(file_exists($file) && !is_writable($file))
		throw new Exception(
			'unable to write to project file',
			static::ErrorFileUnwritable
		);

		file_put_contents($file,"{$this}");
		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	static public function
	FromFile($file) {
	/*//
	return Nether\OneScript\Project
	read a project file from disk.
	//*/

		if(!file_exists($file) && is_readable($file))
		throw new Exception(
			'file not found or unreadable',
			static::ErrorFileUnreadable
		);

		$input = json_decode(file_get_contents($file));

		if(!$input || !is_object($input))
		throw new Exception(
			'file appears to be invalid',
			static::ErrorFileInvalid
		);

		$project = (new static($input))
		->SetProjectFile($file);

		return $project;
	}

	static public function
	MakeDirectory($dir) {
	/*//
	@return bool
	make a directory. returns if successful or not. allows you to
	blindly call it if it already exists to ensure it exists.
	//*/

		if(is_dir($dir))
		return true;

		$umask = umask(0);
		@mkdir($dir,0777,true);
		umask($umask);

		return is_dir($dir);
	}

	static public function
	StripScriptHeader($source) {
		return preg_replace(
			'#/*// nether-onescript //(.+?)//*/#ms',
			'', $source
		);
	}

}
