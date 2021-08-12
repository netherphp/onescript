<?php

namespace Nether\OneScript;
use Nether;

use Exception;
use ReflectionProperty;

class Project {

	const
	ErrorFileUnreadable = 1,
	ErrorFileUnwritable = 2,
	ErrorFileInvalid    = 3;

	////////////////////////////////
	////////////////////////////////

	public array
	$Files = [];
	/*//
	these are the files which will be appended to the output first. this is
	because maybe they are important for the module files to have their core
	framework loaded, and whatnot - fifo.
	//*/

	public array
	$Directories = ['libs'];
	/*//
	the directories in the project root for which we will search for
	additional files to append to the output.
	//*/

	public array
	$Extensions = ['js'];
	/*//
	the file extensions we will use to filter files by when searching for
	additional files to append to the output.
	//*/

	////////
	////////

	public string
	$OutputFile = 'onescript.js';
	/*//
	the filename to save the compiled script as.
	//*/

	public string
	$OutputMinFile = 'onescript.min.js';
	/*//
	the filename to save the minified compiled script as.
	//*/

	////////
	////////

	public bool
	$Print = FALSE;
	/*//
	if true the output of the compiled file will be sent out to stdout.
	this is for if you are using a live .js.php type file thing on your
	dev to automatically recompile every save.
	//*/

	public bool
	$Minify = FALSE;
	/*//
	if true the output of the compiled file will be run through the minify
	tool from https://www.npmjs.com/package/minifier and saved written to
	disk as the $OutputMinFile.
	//*/

	public bool
	$AddScriptHeader = TRUE;
	/*//
	if true it will waste bytes outputting a header at the top of the build
	file that describes the build.
	//*/

	public bool
	$AddFileHeader = TRUE;
	/*//
	if true it will waste bytes outputting a header that separates each file
	from eachother. this of course will be stripped out in the minified
	version.
	//*/

	public string
	$DistDir = 'dist';
	/*//
	the directory compiled files will be stored into. this is the
	distribution directory, the goal is you can copy or symlink that into
	your public web if you so choose.
	//*/

	public string
	$ContentType = 'text/javascript';
	/*//
	the content type to serve as if using print mode.
	//*/

	public bool
	$Updated = FALSE;
	/*//
	if an updated file was written to disk.
	//*/

	////////////////////////////////
	////////////////////////////////

	protected string
	$ProjectFile;
	/*//
	@type string
	//*/

	public function
	GetProjectFile() {
	/*//
	since we put in a full filepath for the project file, we will
	give back a full filepath as well.
	//*/

		return $this->InputDir.DIRECTORY_SEPARATOR.$this->ProjectFile;
	}

	public function
	SetProjectFile(string $Filename) {
	/*//
	set the project file and generate input/output directories based on it
	if they were not yet set.
	//*/

		$this->ProjectFile = basename($Filename);

		if(!$this->InputDir)
		$this->InputDir = dirname($Filename);

		if(!$this->OutputDir)
		$this->OutputDir = dirname($Filename);

		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	protected ?string
	$InputDir = NULL;

	public function
	GetInputDir():
	?string {
	/*//
	@date 2015-08-07
	//*/

		return $this->InputDir;
	}

	public function
	SetInputDir(string $Dir):
	static {
	/*//
	@date 2015-08-07
	//*/

		$this->InputDir = $Dir;
		return $this;
	}

	protected ?string
	$OutputDir = NULL;

	public function
	GetOutputDir():
	?string {
	/*//
	@date 2015-08-07
	//*/

		return $this->OutputDir;
	}

	public function
	SetOutputDir(string $Dir):
	static {
	/*//
	@date 2015-08-07
	//*/

		$this->OutputDir = $Dir;
		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	__Construct($Config=NULL) {
	/*//
	build a project from the input data.
	//*/

		$Prop = NULL;
		$Val = NULL;

		$Config = new Nether\Object\Mapped(
			$Config,
			$this->GetPublicProperties(),
			['DefaultKeysOnly']
		);

		foreach($Config as $Prop => $Val)
		$this->{$Prop} = $Val;

		// default to javascript.

		if($this->Print === TRUE)
		$this->Print = 'js';

		return;
	}

	public function
	__ToString():
	string {
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

	public function
	GetPublicProperties():
	array {
	/*//
	fetch the properties i have decided are safe to write to disk without
	privacy issues. e.g. all publics on this class.
	//*/

		$Prop = NULL;
		$Val = NULL;
		$Output = [];

		foreach($this as $Prop => $Val) {
			$Ref = new ReflectionProperty(static::class,$Prop);
			if($Ref->IsPublic()) $Output[$Prop] = $Val;
		}

		ksort($Output);
		return $Output;
	}

	public function
	GetFullContentType():
	string {
	/*//
	@date 2017-11-08
	fetch the expanded content type as http will expect to se eit.
	//*/

		switch($this->ContentType) {
			case 'css':
			case 'stylesheet':
			return 'text/css';

			case 'js':
			case 'javascript':
			return 'text/javascript';
		}

		return $this->ContentType;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	FindTheFiles():
	array {
	/*//
	search the project for all the files that need to be compiled down
	into the final build.
	//*/

		return array_merge(
			$this->FindTheFiles_Main(),
			$this->FindTheFiles_Libs()
		);
	}

	protected function
	FindTheFiles_Main():
	array {
	/*//
	check the main files that were specified in the project and make sure
	that they exist. returns an array with the full file paths (relative to
	how the app needs to care) to all the files when verified.
	//*/

		$Filename = NULL;
		$Output = [];
		$DS = DIRECTORY_SEPARATOR;

		foreach($this->Files as $Filename) {
			$Filepath = "{$this->InputDir}{$DS}src{$DS}{$Filename}";

			if(!file_exists($Filepath))
			throw new Exception(
				"file src{$DS}{$Filename} not found",
				static::ErrorFileUnreadable
			);

			$Output[] = $Filepath;
		}

		return $Output;
	}

	protected function
	FindTheFiles_Libs():
	array {
	/*//
	check all the optional module folders for files that match the extension
	that we want to automatically append to the end of the build.
	//*/

		$DS = DIRECTORY_SEPARATOR;
		$Grouped = [];
		$Item = NULL;

		foreach($this->Directories as $Item) {
			$Dir = "{$this->InputDir}{$DS}src{$DS}{$Item}";

			if(!is_dir($Dir))
			continue;

			$Finder = new FileFinder($Dir,$this->Extensions);
			$Grouped[$Item] = [];

			foreach($Finder as $Info)
			$Grouped[$Item][] = $Info->GetPathname();

			sort($Grouped[$Item]);
		}

		return array_merge(...array_values($Grouped));
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Build():
	static {
	/*//
	compile the files down to the final form.
	//*/

		$Filepath = NULL;
		$DS = DIRECTORY_SEPARATOR;
		$Source = '';
		$Filelist = $this->FindTheFiles();
		$Outfile = sprintf(
			"%s{$DS}%s{$DS}%s",
			$this->OutputDir,
			$this->DistDir,
			$this->OutputFile
		);

		if($this->Print)
		header(sprintf(
			"Content-type: %s",
			$this->GetFullContentType()
		));

		if($this->AddScriptHeader)
		$this->AppendScriptHeader(
			$Filelist,
			$Source
		);

		foreach($Filelist as $Filepath)
		$this->AppendFile($Filepath,$Source);

		if($this->OutputFile)
		if(!($this->Updated = $this->WriteToDisk($Outfile,$Source))) {
			if($this->Print)
			echo PHP_EOL;
		}

		if($this->Print)
		echo $Source, PHP_EOL;

		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Copy(string $Dest):
	static {
	/*//
	@date 2015-08-07
	//*/

		static::CopyDir(
			$this->OutputDir,
			$Dest
		);

		return $this;
	}

	public function
	Deploy(string $Dest):
	static {
	/*//
	@date 2015-08-07
	//*/

		static::CopyDir(
			sprintf(
				"%s%s%s",
				$this->OutputDir,
				DIRECTORY_SEPARATOR,
				$this->DistDir
			),
			$Dest
		);

		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	protected function
	GetComment(string $Lang, string $Text):
	string {
	/*//
	//*/

		if($Lang) switch($Lang) {
			case 'c':
			case 'css':
			case 'js':
			case 'php':
			case 'standard': {
				return sprintf('\/* %s */%s',$Text,PHP_EOL);
			}
		}

		return sprintf('%s%s',$Text,PHP_EOL);
	}

	protected function
	AppendFile(string $Filename, string &$Buffer):
	static {
	/*//
	append the specified file to the end of the source buffer.
	optionally adds the file header if enabled.
	//*/

		if($this->AddFileHeader)
		$this->AppendFileHeader($Filename,$Buffer);

		$this->AppendFileContents($Filename,$Buffer);

		return $this;
	}

	protected function
	AppendFileHeader(string $Filename, string &$Buffer):
	static {
	/*//
	//*/

		switch($this->GetFullContentType()) {
			case 'text/css':
			$this->AppendFileHeader_ForCSS($Filename,$Buffer);
			break;

			case 'text/javascript':
			$this->AppendFileHeader_ForJavascript($Filename,$Buffer);
			break;
		}

		return $this;
	}

	protected function
	AppendFileHeader_ForCSS(string $Filename, string &$Buffer):
	static {
	/*//
	@date 2017-11-08
	//*/

		$Filename = trim(str_replace(
			$this->InputDir, '',
			$Filename
		),'\\/');

		$Buffer .= '/*';
		$Buffer .= str_repeat('/',73).PHP_EOL;
		$Buffer .= "// {$Filename} ";
		$Buffer .= str_repeat('/',(69-strlen($Filename)));
		$Buffer .= '*/'.PHP_EOL.PHP_EOL;

		return $this;
	}

	protected function
	AppendFileHeader_ForJavascript(string $Filename, string &$Buffer):
	static {
	/*//
	@date 2017-11-08
	//*/

		$Filename = trim(str_replace(
			$this->InputDir, '',
			$Filename
		),'\\/');

		$Buffer .= str_repeat('/',75).PHP_EOL;
		$Buffer .= "// {$Filename} ";
		$Buffer .= str_repeat('/',(71-strlen($Filename)));
		$Buffer .= PHP_EOL.PHP_EOL;

		return $this;
	}

	protected function
	AppendFileContents(string $Filename, string &$Buffer):
	static {
	/*//
	//*/

		$Buffer .= trim(file_get_contents($Filename));
		$Buffer .= PHP_EOL.PHP_EOL;

		return $this;
	}

	protected function
	AppendScriptHeader(array $Files, string &$Buffer):
	static {
	/*//
	//*/

		$File = NULL;

		foreach($Files as &$File)
		$File = trim(str_replace(
			$this->InputDir,'',
			$File
		),'\\/');

		$Buffer .= '/*// nether-onescript //'.PHP_EOL;
		$Buffer .= '@date '.date('Y-m-d H:i:s').PHP_EOL;
		$Buffer .= '@files '.json_encode($Files,JSON_PRETTY_PRINT).PHP_EOL;
		$Buffer .= '//*/'.PHP_EOL.PHP_EOL;

		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	ShouldWriteToDisk(string $Filename, string $Source):
	bool {
	/*//
	//*/

		if(!file_exists($Filename))
		return TRUE;

		$Old = md5(static::StripScriptHeader(file_get_contents($Filename)));
		$New = md5(static::StripScriptHeader($Source));

		if($Old !== $New)
		return TRUE;

		return FALSE;
	}

	public function
	WriteToDisk(string $Filename, string $Source):
	bool {
	/*//
	//*/

		if(!$this->ShouldWriteToDisk($Filename,$Source))
		return FALSE;

		if(!file_exists($Filename))
		if(!static::MakeDirectory(dirname($Filename)))
		throw new Exception(
			'unable to create directory for output file.',
			static::ErrorFileUnwritable
		);

		if(file_exists($Filename) && !is_writable($Filename))
		throw new Exception(
			'unable to write to output file.',
			static::ErrorFileUnwritable
		);

		file_put_contents($Filename,$Source);
		return TRUE;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Bootstrap():
	static {
	/*//
	@date 2015-08-07
	//*/

		$LibDir = NULL;
		$MainFile = NULL;
		$DS = DIRECTORY_SEPARATOR;

		if(!$this->ProjectFile)
		throw new Exception('no project file set');

		if(!$this->OutputDir)
		throw new Exception('no output directory set.');

		if(!$this->InputDir)
		throw new Exception('no input direcetory set.');

		////////

		// make main source directory.
		static::MakeDirectory("{$this->InputDir}{$DS}src");

		// make module directories.
		foreach($this->Directories as $LibDir)
		static::MakeDirectory("{$this->InputDir}{$DS}src{$DS}{$LibDir}");

		// make blank mainfiles.
		foreach($this->Files as $MainFile)
		touch("{$this->InputDir}{$DS}src{$DS}{$MainFile}");

		return $this;
	}

	public function
	Save():
	static {
	/*//
	@return self
	save the project json file to disk.
	//*/

		if(!$this->OutputDir)
		throw new Exception('no output dir set');

		$DS = DIRECTORY_SEPARATOR;
		$File = "{$this->InputDir}{$DS}{$this->ProjectFile}";

		if(!file_exists($File) && !is_writable(dirname($File)))
		throw new Exception(
			'unable to write to project directory',
			static::ErrorFileUnwritable
		);

		if(file_exists($File) && !is_writable($File))
		throw new Exception(
			'unable to write to project file',
			static::ErrorFileUnwritable
		);

		file_put_contents($File,"{$this}");
		return $this;
	}

	////////////////////////////////
	////////////////////////////////

	static public function
	FromFile(string $File):
	static {
	/*//
	return Nether\OneScript\Project
	read a project file from disk.
	//*/

		if(!file_exists($File) && is_readable($File))
		throw new Exception(
			'file not found or unreadable',
			static::ErrorFileUnreadable
		);

		$Input = json_decode(file_get_contents($File));

		if(!$Input || !is_object($Input))
		throw new Exception(
			'file appears to be invalid',
			static::ErrorFileInvalid
		);

		$Project = (
			(new static($Input))
			->SetProjectFile($File)
		);

		return $Project;
	}

	static public function
	MakeDirectory(string $Dir):
	bool {
	/*//
	@return bool
	make a directory. returns if successful or not. allows you to
	blindly call it if it already exists to ensure it exists.
	//*/

		if(is_dir($Dir))
		return TRUE;

		$Umask = umask(0);
		@mkdir($Dir,0777,TRUE);
		umask($Umask);

		return is_dir($Dir);
	}

	static public function
	CopyDir(string $Source, string $Dest):
	void {
	/*//
	do a recursive copy of a directory.
	it will not copy over the .git folder.
	//*/

		$Cur = NULL;
		$Finder = new Nether\OneScript\FileFinder($Source,NULL);

		foreach($Finder as $Cur) {
			if(is_dir($Cur->GetPathname())) {
				if($Cur->GetFilename() === '.git') continue;

				if(!static::MakeDirectory("{$Dest}/{$Cur->GetFilename()}"))
				throw new Exception("unable create new directory in destination.");

				static::CopyDir(
					$Cur->GetPathname(),
					"{$Dest}/{$Cur->GetFilename()}"
				);
			}

			elseif(is_file($Cur->GetPathname())) {
				copy($Cur->GetPathname(),"{$Dest}/{$Cur->GetFilename()}");
			}
		}

		return;
	}

	static public function
	StripScriptHeader($Source) {
	/*//
	@date 2015-08-07
	//*/

		return preg_replace(
			'#/*// nether-onescript //(.+?)//*/#ms',
			'',
			$Source
		);
	}

}
