<?php

namespace Nether\OneScript;
use \Nether;

use \Exception;
use \FilterIterator;
use \FilesystemIterator;

class FileFinder extends FilterIterator {
/*//
finds files in the specified directory with the allowed list of file
extensions. packs a FilesystemIterator into a FilterIterator. jakefolio
will be unable to resist falling in love with me now.
//*/

	protected $Exts;
	/*//
	@type array
	list of the extensions we want to allow, flipped into a hash index.
	//*/

	////////////////////////////////
	////////////////////////////////

	public function
	__construct($directory, $extensions=[]) {
	/*//
	@override
	//*/

		parent::__construct(new FilesystemIterator(
			$directory,
			(FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO)
		));

		// if given some extensions, flip them to generate a hash lookup
		// table instead.
		if(is_array($extensions))
		$this->Exts = array_flip($extensions);

		return;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Accept() {
	/*//
	@override
	//*/
	
		// provide a way for me to reuse this to accept all files. then
		// i can reuse this is a recursive copy.
		if($this->Exts === null)
		return true;

		// else do the test.
		return array_key_exists(
			$this->getInnerIterator()->getExtension(),
			$this->Exts
		);
	}

}
