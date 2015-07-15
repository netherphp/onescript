<?php

namespace Nether\OneScript;
use \Nether;
use \Exception;

class Builder {
/*//
this class is the main builder which will find all the javascript files that
you need to boil down into the single onescript build. it is able to be given
specific files to render in the order given, as well as being able to search
directories to find any modules or extensions to append after that.
//*/

	public $Opt;
	/*//
	@type object
	stores the options that were input into the class to describe the project
	that we want to build.
	//*/
	
	protected $Verbose = false;
	/*//
	@type bool
	if true enables text output, mainly for the console api.
	//*/

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected $Filepath;
	/*//
	@type string
	the generated full path to the final file that we want to compile down
	into.
	//*/
	
	protected $MainFiles = [];
	/*//
	@type array(string, ...)
	a generated list of full paths to the mainfiles that were defined.
	//*/
	
	protected $ModuleFiles = [];
	/*//
	@type array(string, ...)
	a generated list of full paths to any module files that were found.
	//*/

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	__construct($opt=null) {

		$this->Opt = new Nether\Object($opt,[

			'Extension' => 'js',
			// this is the extension we will filter extension files by for
			// inclusion.

			'ProjectRoot' => '.',
			// where we are looking for our files.

			'FinalForm'  => 'onescript.js',
			// this is the name of the final file that will be written to
			// disk. your production reference.

			'Files'  => [],
			// these are files that bootstrap the library, order will be
			// maintained just incase the app depends on it.

			'ModuleDirs' => [ 'ext' ],
			// these are directories we will scan to find all files to
			// append to the final file after the main files. the order
			// of these will be alphabetical, but your modules should not
			// depend on anything except the main files anyway.

			'AddScriptHeader' => true,
			// if true it will waste bytes adding a header about the build.

			'AddFileMarkers' => true,
			// if true it will waste bytes adding comments between each file.

			'Minify' => false,
			// if true it will unwaste bytes doing the normal minificiation
			// stuff we are used to.
			// TODO - not implemented.

			'Print' => true,
			// if true it will print the file to stdout.

			'Write' => true,
			// if true it will also write the file to disk.

			'WriteProjectFile' => true,
			// if true will write a .json describing the job.

			'Key' => false
			// if set to a string value, it will require that a get variable
			// called `key` exists to perform serious operations like writing
			// to disk.

		],[ 'DefaultKeysOnly'=>true ]);

		return;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	Check() {
	/*//
	@return self
	run all the build checks.
	//*/

		$this->CheckFinalForm();
		$this->CheckMainFiles();
		$this->CheckModuleDirs();

		return $this;
	}

	protected function
	CheckFinalForm() {
	/*//
	@return self
	construct the final form to the full file path and check that we will
	even be able to write to it.
	//*/

		$this->Filepath = "{$this->Opt->ProjectRoot}/{$this->Opt->FinalForm}";

		// if there was no valid key then do not even bother testing if
		// we can write, because we aren't going to write.
		if(!$this->HasValidKey()) return $this;

		// check that we can write to the places that matter.
		if(!file_exists($this->Filepath)) $this->CheckFinalForm_WriteToDirectory();
		else $this->CheckFinalForm_WriteToFile();

		return $this;
	}

	protected function
	CheckFinalForm_WriteToDirectory() {
	/*//
	@return self
	if the file didn't exist we need to check that the directory we want to
	write to is writable so we can create the new files inside of it.
	//*/

		// make sure the entire directory tree exists.
		$umask = umask(0);
		@mkdir(dirname($this->Filepath),0777,true);
		umask($umask);

		// make sure we can write.
		if(!is_writable(dirname($this->Filepath))) {
			printf(
				"alert('Script Error: Unable to write to directory.');\n\n",
				$this->FinalForm
			);

			$this->Opt->Write = false;
		}

		return $this;
	}

	protected function
	CheckFinalForm_WriteToFile() {
	/*//
	@return self
	if the file did exist we need to make sure it is writable so that we can
	overwrite it.
	//*/

		if(!is_writeable($this->Filepath)) {
			printf(
				"alert('Script Error: Unable to write final file (%s).');\n\n",
				$this->Opt->FinalForm
			);

			$this->Opt->Write = false;
		}

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	CheckMainFiles() {
	/*//
	@return self
	run through the list of specified main files and see that they exist and
	are readable. if not, then remove them from the list.
	//*/

		foreach($this->Opt->Files as $file) {
			$filepath = "{$this->Opt->ProjectRoot}/src/{$file}";

			if(!file_exists($filepath) || !is_readable($filepath)) {
				if($this->Verbose)
				printf("// file not found: %s\n",basename($filename));
				
				continue;	
			}

			$this->MainFiles[] = $filepath;
		}

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	CheckModuleDirs() {
	/*//
	@return self
	run through the list of specified extension directories and find an files
	matching the file extension they contain for inclusion.
	//*/

		$files = [];
		foreach($this->Opt->ModuleDirs as $dir) {
			$files = array_merge(
				$files,
				glob("{$this->Opt->ProjectRoot}/src/{$dir}/*.{$this->Opt->Extension}")
			);
		}

		foreach($files as $file) {
			if(!file_exists($file) || !is_readable($file))
			continue;

			$this->ModuleFiles[] = $file;
		}

		sort($this->ModuleFiles);

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	Build() {
	/*//
	@return self
	compile all the files down into the final file.
	//*/

		$this->Check();

		$output = '';

		// script header.
		if($this->Opt->AddScriptHeader)
		$this->AppendScriptHeader($output);

		// main files.
		$this->AppendMainFiles($output);

		// module files.
		$this->AppendModuleFiles($output);

		// write to disk.
		if($this->Opt->Write) {
			$this->WriteToDisk($output);

			if($this->Opt->WriteProjectFile)
			$this->WriteProjectFile();
		}

		////////
		////////

		if($this->Opt->Print) {
			header("Content-type: text/javascript");
			//header("Content-length: ".mb_strlen($output));
			echo $output;
		}

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	AppendFile(&$output,$filepath) {
	/*//
	@return self
	append the contents of the specified file to the output buffer. if there
	was a problem reading the file for any reason then append an error message
	stating that instead.
	//*/

		if(!file_exists($filepath)) {
			$this->AppendFileMissing($output,$filepath);
		}

		elseif(!is_readable($filepath)) {
			$this->AppendFileUnreadable($output,$filepath);
		}

		else {
			$this->AppendFileMarker($output,$filepath);
			$this->AppendFileContents($output,$filepath);
		}

		return $this;
	}

	protected function
	AppendFileMarker(&$output,$filename) {
	/*//
	@return self
	append a little comment header into the output buffer.
	//*/

		// dont add the marker if disabled.
		if(!$this->Opt->AddFileMarkers) return $this;

		$filename = trim(str_replace($this->Opt->ProjectRoot,'',$filename),'/');

		// figure out how long our dynamic string is.
		$string = "// {$filename} ";
		$string .= str_repeat('/',(80 - strlen($string)));

		// print the header.
		$output .= sprintf("%s\n",str_repeat('/',80));
		$output .= "{$string}\n\n";

		return $this;
	}

	protected function
	AppendFileMissing(&$output,$filepath) {
	/*//
	@return self
	append a comment mentioning that a file could not be found.
	//*/

		return $this->AppendFileMarker(
			$output,
			"ERROR: File Missing - {$filepath}"
		);
	}

	protected function
	AppendFileUnreadable(&$output,$filepath) {
	/*//
	@return self
	append a comment mentioning that a file could not be read.
	//*/

		return $this->AppendFileMarker(
			$output,
			"ERROR: Cannot Read File - {$filepath}"
		);
	}

	protected function
	AppendFileContents(&$output,$filepath) {
	/*//
	@return self
	append the contents of a file from disk into the buffer.
	//*/

		$output .= trim(file_get_contents($filepath));
		$output .= "\n\n";

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	AppendScriptHeader(&$output) {
	/*//
	@return self
	render out a giant byte wasting header about the build.
	//*/

		$filelist = array_map(function($val){
			return trim(str_replace($this->Opt->ProjectRoot,'',$val),'/');
		},array_merge($this->MainFiles,$this->ModuleFiles));

		$output .= "/*//\n";
		$output .= sprintf("@date %s\n",date('Y-m-d H:i:s'));
		$output .= sprintf("@files %s\n",json_encode(
			$filelist,
			JSON_PRETTY_PRINT
		));
		$output .= "//*/\n\n";

		return $this;
	}

	protected function
	AppendMainFiles(&$output) {
	/*//
	@return self
	render out the main files.
	//*/

		foreach($this->MainFiles as $filepath)
		$this->AppendFile($output,$filepath);

		return $this;
	}

	protected function
	AppendModuleFiles(&$output) {
	/*//
	@return self
	render out the module files.
	//*/

		foreach($this->ModuleFiles as $filepath)
		$this->AppendFile($output,$filepath);

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	WriteToDisk($source) {
	/*//
	@return self
	write the buffer to disk as the final file. checks if the resulting file
	will has changed at all before actually committing the write.
	//*/

		if(!$this->HasValidKey()) return $this;
		if(!$this->ShouldWriteToDisk($source)) {
			echo "// not writing to disk - output unchanged.\n\n";
			return $this;
		}

		file_put_contents($this->Filepath,$source);
		return $this;
	}

	public function
	WriteProjectFile() {
	/*//
	@return self
	write the options used to disk so we can reuse them later from the command
	line tool or something.
	//*/

		$config = (array)clone($this->Opt);
		unset(
			$config['ProjectRoot'],
			$config['Key']
		);
		ksort($config);

		file_put_contents(
			sprintf(
				'%s/%s',
				dirname($this->Filepath),
				str_replace('.js','.json',basename($this->Opt->FinalForm))
			),
			json_encode($config,JSON_PRETTY_PRINT)
		);

		return;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	HasValidKey() {
	/*//
	@return bool
	if an access key was defined check that it exists and that it was correct.
	//*/

		// if no key was defined then let it pass.
		if(!$this->Opt->Key) return true;

		// if the key matches let it pass.
		if(array_key_exists('key',$_GET) && $_GET['key'] === $this->Opt->Key) return true;

		// else a failure.
		return false;
	}

	protected function
	ShouldWriteToDisk($source) {
	/*//
	@return bool
	check if the source buffer is different than the current existing file.
	//*/
		
		if(!file_exists($this->Filepath)) return true;

		$StripVars = function($input) {
			return preg_replace('/^@date .*?$/ms','',$input);
		};

		$new = md5($StripVars($source));
		$old = md5($StripVars(file_get_contents($this->Filepath)));

		return !($old === $new);
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	SetVerbose($state) {
	/*//
	@return self
	allow printing of messages, mainly for command line mode.
	//*/

		$this->Verbose = (bool)$state;
		return $this;
	}

}
