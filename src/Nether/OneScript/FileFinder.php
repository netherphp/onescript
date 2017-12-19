<?php

namespace Nether\OneScript;

use \Nether as Nether;

use \Exception;
use \FilterIterator;
use \FilesystemIterator;
use \EmptyIterator;

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
	__construct($Directory, $Extensions=[]) {
	/*//
	@override
	//*/

		if(is_dir($Directory) && is_readable($Directory))
		parent::__construct(new FilesystemIterator(
			$Directory,
			(FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO)
		));

		else
		parent::__construct(new EmptyIterator);

		// if given some extensions, flip them to generate a hash lookup
		// table instead.
		if(is_array($Extensions))
		$this->Exts = array_flip($Extensions);

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
