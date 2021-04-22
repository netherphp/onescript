<?php

namespace Nether\OneScript;

use FilterIterator;
use FilesystemIterator;
use EmptyIterator;

class FileFinder
extends FilterIterator {
/*//
finds files in the specified directory with the allowed list of file
extensions. packs a FilesystemIterator into a FilterIterator. jakefolio
will be unable to resist falling in love with me now.
//*/

	protected array
	$Exts = [];
	/*//
	@type array
	list of the extensions we want to allow, flipped into a hash index.
	//*/

	////////////////////////////////
	////////////////////////////////

	public function
	__Construct(string $Directory, array $Extensions=[]) {
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
		if(count($Extensions))
		$this->Exts = array_flip($Extensions);

		return;
	}

	////////////////////////////////
	////////////////////////////////

	public function
	Accept():
	bool {
	/*//
	@override
	//*/

		// provide a way for me to reuse this to accept all files. then
		// i can reuse this is a recursive copy.
		if($this->Exts === NULL)
		return TRUE;

		// else do the test.
		return array_key_exists(
			$this->GetInnerIterator()?->GetExtension(),
			$this->Exts
		);
	}

}
