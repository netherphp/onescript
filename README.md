# Nether OneScript

A silly Javascript "Compiler"

The only things I dislike more than Javascript are other people's Javascript
frameworks and build systems. So like a complete tool I wrote my own that
works in the way I typically build my scripts.


# Use Case

Pretending our website project is setup like this.

* Our Project Root: `/opt/website`
* Our Public Root: `/opt/website/www`
* A JS Lib We Wrote: `/opt/website/www/share/mylib`
* So our shell sits in `/opt/website` most of the time.

And our JS Lib `mylib` is structured like this.

* `src/mylib-main.js`
* `src/mylib-whatever.js`
* `src/libs/extension1.js`
* `src/libs/extension2.js`

# Quick Start

This library provides a vendor bin file. On Windows you will need to run it
as `vendor\bin\nether-onescript` while upon anything else you will need to
call it as `php vendor/bin/nether-onescript` - I will use the NotWindows
notation since thats what any sane server is.

	php vendor/bin/nether-onescript help

# Creating a new OneScript project.

OneScript expects the project source files to be within the `src` directory.
By default it wil use `libs` as the module directory looking for the `js`
extension. So the command required for our example project above:

	php vendor/bin/nether-onescript create www/share/mylib \
	--files=mylib-main.js,mylib-whatever.js

This will create `onescript.json` (which can be changed by --filename) in 
`www/share/jslib/` - in this JSON file you can see all the options
available to change. You will notice that it will have added the main files
you listed on the command line in the Files option, and automatically listed
`libs` in the Directories option with `js` in the Extenions option. It also
will have set the OutputFile to `onescript.js` as well.

# Building the OneScript project via CLI.

	php vendor/bin/nether-onescript build www/share/mylib/onescript.json
	
Assuming there are no errors like file permissions or missing files, this
will compile your OneScript project down into `onescript.js` - your
potentially final file for distribution.

The build system works like so:

* Load the `Files` and bake them into a single file, in the order you specified.
* Next it will go through each of the `Directories` looking for files with the
	extensions listed in `Extensions` and bake them to the end of the file. It
	will save that as your `OutputFile` as the final product.
* Once Minify support is done, it will then pass that through the minification
	process to create the `OutputMinFile` - the minification feature is not yet
	impelemented.

# Building the OneScript project via a live compiler.

Sometimes it is handy, like on your dev server, to have the projects built
automaticaly every time you change them. For my example here I would probably
create a new file called `onescript.js.php` in my JS project folder.

	$project = Nether\OneScript\Project::FromFile(sprintf(
		'%s/onescript.json',
		dirname(__FILE__)
	));
	
	$project->Print = true;
	$project->Build();
	
If you reference this `onescript.js.php` in your script tag it will be built
every time the page is loaded and the files have changed. You won't realy want
to commit this to your prodution space.