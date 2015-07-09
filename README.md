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

And our JS Lib `mylib` is structured like this.

* `src/mylib-main.js`
* `src/mylib-whatever.js`
* `src/ext/extension1.js`
* `src/ext/extension2.js`

As we all know, doing `<script src>` for each one will suck as now the server
and client have to establish extra connections (maybe) to download all the
files which make up one single JS application. The simple thing to do is to
bake them down into one single file we can include instead. Something like:

* `mylib.js`

and then of course, the next step after that:

* `mylib.min.js`

# Using OneScript

First I am going to create the "Dev Script" - in this case a PHP script which
will read all of the scripts and then print them out. Every time its refreshed
we will re-read the entire directory so that we can develop against changes
live without having to rebake the project. Don't worry, we won't use this in
production.

To start with, I am going to create the file `mylib.js.php` which lives in
`/opt/website/www/share/mylib` meaning every time I hit
`http://mysite.com/share/mylib/mylib.js.php` I am getting a refreshed view of
all the JS code that made the project.

	<?php

	$bob = new Nether\OneScript\Builder([

		'FinalForm' => str_replace('.php','',basename(__FILE__),
		// write mylib.js.php as mylib.js to disk.

		'ProjectRoot' => dirname(__FILE__),
		// in this case i am building in my public space. i want to
		// drop the mylib.js file right next to this file.

		'Files' => [ 'mylib-main.js', 'mylib-whatever.js' ],
		// these are files which may need to be loaded before and
		// in this specific order, for the rest of the app to work.

		'ModuleDirs' => [ 'plugins' ]
		// these are the directories for added modules or plugins
		// which should not depend on the order they are loaded.
		// OneScript will sort them alphabetically.

	]);

	$bob->Build();

By default no smashing or minifying is done. All the scripts will be concatinated
into one single long file, and comments will be inserted showing the file breaks.
There are options to turn off the file markers, and in the future minifying will
be a feature as well.

So with that script as `mylib.js.php` I can now do something like this in my
website's theme file:

	<script src="/share/mylib/mylib.<?php echo((!defined('DEV'))?('js'):('js.php')) ?>"></script>

Every time I test my site on DEV I will get the updated source and an
automatic build for production.

# Securing

In the Builder constructor you can add a property called `Key` which is a string
that will be required in the _GET variable `key` in order to perform
write-to-disk operations. While this security is weak, it will be more or less
good enough if you push `mylib.js.php` to production.

The best solution however is that you don't pull (or delete after) the builder
dev script on production, leaving only the last version of `mylib.js` it compiled
from your dev sever. This way you won't have to worry about leaving the directory
writable.

The best solution for that solution would probably be to not have the builder in
the public directory at all. In the future I will probably include a composer bin
file for compiling via command line.

