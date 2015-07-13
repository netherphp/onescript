<?php

namespace Nether\OneScript;
use \Nether;
use \Exception;

class Builder {

	protected $Opt;

	// these properties will get generated.
	protected $Filepath;
	protected $MainFiles = [];
	protected $ModuleFiles = [];

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	public function
	__construct($opt=null) {

		$this->Opt = new Nether\Object($opt,[

			'Extension' => 'js',

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

			'Key' => false
			// if set to a string value, it will require that a get variable
			// called `key` exists to perform serious operations like writing
			// to disk.

		],[ 'DefaultKeysOnly'=>true ]);

		return;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	CheckFinalForm() {
	/*//
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
	//*/

		// make sure the entire directory tree exists.
		$umask = umask(0);
		@mkdir(dirname($this->Filepath),0777,true);
		umask($umask);

		// make sure we can write.
		if(!is_writable(dirname($this->Filepath))) {
			printf(
				"alert('Script Error: Unable to write final directory.');\n\n"
			);

			$this->Opt->Write = false;
		}

		return $this;
	}

	protected function
	CheckFinalForm_WriteToFile() {
	/*//
	//*/

		if(!is_writeable($this->Opt->FinalForm)) {
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

		foreach($this->Opt->Files as $file) {
			$filepath = "{$this->Opt->ProjectRoot}/src/{$file}";

			if(!file_exists($filepath) || !is_readable($filepath))
			continue;

			$this->MainFiles[] = $filepath;
		}

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	CheckModuleDirs() {
	/*//
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
	compile all the files down into the final file.
	//*/

		$this->CheckFinalForm();
		$this->CheckMainFiles();
		$this->CheckModuleDirs();

		$output = '';

		// script header.
		if($this->Opt->AddScriptHeader)
		$this->AppendScriptHeader($output);

		// main files.
		$this->AppendMainFiles($output);

		// module files.
		$this->AppendModuleFiles($output);

		// write to disk.
		if($this->Opt->Write)
		$this->WriteToDisk($output);

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
	//*/

		return $this->AppendFileMarker(
			$output,
			"ERROR: File Missing - {$filepath}"
		);
	}

	protected function
	AppendFileUnreadable(&$output,$filepath) {
	/*//
	//*/

		return $this->AppendFileMarker(
			$output,
			"ERROR: Cannot Read File - {$filepath}"
		);
	}

	protected function
	AppendFileUnwritable(&$output,$filepath) {
	/*//
	//*/

		return $this->AppendFileMarker(
			$output,
			"ERROR: Cannot Write File - {$filepath}"
		);
	}

	protected function
	AppendFileContents(&$output,$filepath) {
	/*//
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
	render out the main files.
	//*/

		foreach($this->MainFiles as $filepath)
		$this->AppendFile($output,$filepath);

		return $this;
	}

	protected function
	AppendModuleFiles(&$output) {
	/*//
	render out the module files.
	//*/

		foreach($this->ModuleFiles as $filepath)
		$this->AppendFile($output,$filepath);

		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	WriteToDisk($source) {
	/*//
	//*/

		if(!$this->HasValidKey()) return $this;
		if(!$this->HasFileChanged($source)) {
			echo "// not writing to disk - output unchanged.\n\n";
			return $this;
		}

		file_put_contents($this->Filepath,$source);
		return $this;
	}

	///////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////

	protected function
	HasValidKey() {
	/*//
	//*/

		// if no key was defined then let it pass.
		if(!$this->Opt->Key) return true;

		// if the key matches let it pass.
		if(array_key_exists('key',$_GET) && $_GET['key'] === $this->Opt->Key) return true;

		// else a failure.
		return false;
	}

	protected function
	HasFileChanged($source) {

		$outwithit = function($input) {
			return preg_replace('/^@date .*?$/msi','',$input);
		};

		$old = md5($outwithit(file_get_contents($this->Filepath)));
		$new = md5($outwithit($source));

		return !($old === $new);
	}

}
