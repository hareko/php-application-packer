# PHP Application Packer #

PackApp.php is a complete solution to pack your application's project files into the delivery package. The source code is minified and obfuscated, impeding unauthorized use.

The target audience: ISVs, freelancers, developers, resellers,  all who need a lightweight utility to turn the project into the delivery package. 
The solution is oriented to the PHP applications, but can be used for the front-end projects too.

There are many minifiers, obfuscators, encoders around. Some commercial encoders are very good, but rather expensive and may require system extensions. 
The others are limited with a single source type only. The Packer is a handy integrated solution.

## How it works ##
PackApp.php packs the project files: minifies the source (html, css, js, json, php, xml), obfuscates the code (js, php) and compresses the result files.
The source can be either a single file or a folder which may have sub-folders. 
The destination is a packed file or a folder with the packed files.
The ZIP archives are handled also: folder-to-zip, zip-to-folder, zip-to-zip.

The Packer passes the source to according minify plugin depending on the file type.
The *css* and *js* that detected inside the *html* are minified too. 
The *html*, *css* and *js* are looked for the embedded *php* to minify (except obfuscated *js*). 
You can create the user plugins for more file types.

The *js* and *php* code can be obfuscated to inhibit its unauthorized use. The reverse engineering of the packed code becomes too expensive.
The identifiers replacement algorithm used by the Packer is more protective than trivial applying of the *base64_encode* and *eval*.
However, you must follow certain naming rules to avoid the renaming conflicts. The *php* obfuscation is supported by the standard edition.

There are several options to control the processing. Use them for specific cases and rely on defaults mostly. 
Launch the Packer via the GUI or instantiate and call directly. Various statistics are returned about the processing results. You can display and/or save this data.

## Versioning ##

The Packer has the free and extended versions. Free version minifies the source and can be launched via instantiation only. 

Extended version includes also the GUI and requires the setup (license registration) before using. The extended version has the following editions:

- PackApp Lte - lite edition; minifies the source (html, css, js, json, php, xml) and obfuscates the JavaScript code.
- PackApp Std - standard edition; supplies the lite functionality and obfuscates the PHP code.

You can obtain the extended version from [here].

## The usage ##

The program requires PHP 5.4+. Start it from your script via GUI (except free version) or instantiation (see *The package*). 

The starter script must be in the same directory with the *PackApp.php*: 

**require('PackApp.php');**

A) Launching the GUI:

**echo PackApp::Packer();**

B) Instantiation and invocation:

**$obj = new PackApp( [$lvl [,$opt]] );**

**$obj->Pack( $old [,$new [,$rpl]] );**

### The parameters ###

**$lvl** - processing level, the sum of the following integers:

- *0* - minify (default)
- *1* - encode JS (Base62, shrink variables)
- *2* - obfuscate PHP (replace identifiers)
- *4* - compact CSS (remove excessive data)

**$opt** - the options array:

- **exf** - array of the folder/file name wildcards to exclude from the packing (default by *['\*.min.\*']*)
- **sbd** - recurse sub-directories (default by *true*)
- **cpy** - copy non-minified files (default by *true*)
- **pgn** - user plugins (see below)
- **sfx** - default destination name suffix (default by *'_pkd'*)
- **sgn** - signature to prepend to the minified *php/js/css* code (specify empty value to suppress the default); the replacements:
    - *{app}* - Packer name;
    - *{ver}* - Packer version;
    - *{time}* - current UTC time;
- **tml** - time limit in seconds the program is allowed to run (for larger projects, default by *30*)
- **log** - logging level number: 0 - no, 1 - ok only, 2 - all (default by *0*)

PHP obfuscation options:

- **ids** - the identifiers replacement flags (in any sequence, lowercase means case-insensitive, default by *'VDHF'*):
    - *V* - variables/properties
    - *D|d* - defined and class constants (d - defined constants only)
    - *H* - heredocs/nowdocs
    - *F|f* - functions/methods
    - *C|c* - classes
    - *T|t* - traits
- **exi** - array of the identifier wildcards to exclude from the obfuscation (added to predefined ones)
- **pfi** - prefix of the replacement identifier (default by *'_'*)
- **lni** - replacement identifier's left-padding length with zeroes (default by 0 - no padding)
- **dbg** - debugging flag (default by *false*, *true* - don't minify)
    
**$old** - source file/folder/archives

**$new** -- destination (default by **$old** value suffixed by **sfx** option value)

**$rpl** -- replace destination if exists (default by *false*)

### Return data ###

The **$obj->Pack()** returns the results associative array:

- **code** - *'ok'* means success; failed otherwise
- **prompt** - brief message about the success/fail/error
- **factor** - the statistics array (success); if string then the installer html (except free version)
- **string** - the statistics text (newline-separated)

You can display and/or save the statistics. See *example.php* about the using.

## Packing ##

The files are minified by default and obfuscated (*js, php*) if required. The php templates are packed for the *html, js, css, php*.
The source file is processed when its type matches one of the following (wildcards allowed);

- *\*htm\** - html code (*htm, html, phtml,* ...) 
- *css\** - stylesheet
- *js* - JavaScript code
- *json* - json string
- *xml* - xml source
- *php\** - php code
- *inc* - php code

The rest of the files are simply copied or skipped. Use the **pgn** option for additional types (see below). The folder or archived source is saved to the destination folder or archived depending on the *$new* parameter. 


### Minifying ###

The source files of relevant file type are minified by removing the comments, whitespaces and linebreaks. 
The *\*htm\** files are checked for the *style* and *script* tags which content is minified too. 
The files (except *xml*) are checked also for the *php* tags to pack their content.

### Obfuscating ###

The minified source is obfuscated depending on the **$lvl** parameter value.
The JavaScript code is packed into the *eval()* statement replacing the original identifiers. The PHP obfuscation requires the standard edition.

## Plugins ##

The package includes the plugins to minify according sources (see *The package*). 
You can define your own plugins for additional file types with the **pgn** option:

**['pgn' => ['type' => flag]]**

- *type* - source file type, must be in accordance with the class name (for example, *'sql'* requires *PackSQL.php*)
- *flag* - boolean *true* - check for the embedded php code, *false* - bypass check

For example:

*$obj = new PackApp(1, ['pgn' => ['sql' => false]]);* 

Place your plugin class into the *plugins* folder. Make it callable via static *minify* method like here:

```
...
public static function minify($source, $options = []) {
  $min = new self($options);
  return $min->process($source);
}
...
```

Please [contact] if you would like to include your own plugin(s) into the package.

## PHP obfuscation ##

The standard edition with the *PackAppO* add-on is required for the PHP obfuscation. It's recommended to follow certain naming rules in the planning and coding stage already to avoid the renaming conflicts (see below). 

The *php* source is processed in two passes. 1st pass registers the identifiers found from the code during the minifying.
2nd pass performs cross-file renaming of the identifiers registered by 1st pass. 
The following identifiers can be replaced depending on the **obf** option:

- variables/properties 
- constants (class and defined ones)
- heredocs/nowdocs
- functions/methods
- classes
- traits

The replacement can be case-insensitive (except variables/properties, heredocs/nowdocs, class constants).

### PHP identifiers ###

Use the identifiers exclusion list (**exi** option) and follow the naming rules to avoid the identifiers' replacement conflicts:

1. Be careful with the variable variables (*$$var*) or add their names to the exclusion list (see example).
2. Don't use the variable names that are the same as any (system) object property name used in the application.
3. The user constant name must not coincide with any system constant name used in the application.
4. The element name which value is got via __get/__set magic methods from the array/object or is computed, must not be the same as any variable/property name used in application.
5. Don't obfuscate the class/trait names which to be autoloaded, and class/method/function names specified in the callbacks, like *call_user_func('myclass','mymethod')*.
6. The identifiers prefixed by *'__'* are not obfuscated. You can use this for your identifiers exclusion too.
7. Prefix your identifiers or add to exclusion list, if necessary, to follow (2,3,4,5).
8. Be careful with the case-insensitive names of the functions, classes, traits and defined constants.
9. Sample naming conventions:
    - variables, properties, constants - in camelCase;
    - heredocs, nowdocs - in UPPERCASE;
    - functions, methods, classes, traits - in PascalCase;

These rules are natural to follow and secure from the identifier name collisions. Use the **dbg** option to fix the renaming issues in the non-minified obfuscated result.

## Installation ##

Unzip the obtained package and upload the files to your selected web directory. Then run *example.php*. The extended version requires the Setup before exploiting. It launches the installer automatically if not set up yet. 
Run *PackApp.php* from the browser to launch the installer directly.

The included example minifies/obfuscates the files from the *tests* folder and compresses the result into the *tests.zip*. 
A message informs about the result. The statistics collected by the packer are displayed and saved into *example.txt*. 

Make a copy from the *example.php* and try it with different sources, destinations and options. Run *index.php* to launch the GUI supplied by an extended version.

### Updates ###

The extended version supplies the version updates. The *About* section of the GUI displays the installation and update information and allows to edit your contact data. Run *PackApp.php* from the browser and click the *Update* button to check for the updates directly. Your contact data will be used for important product-related messages only. If your contacts change, please edit.

If the updating fails on any reason then the *update.php* and *update.json* files created allow to restore - run *update.php*.


## The package ##

The *plugins* folder contains the minifiers adapted from the open source. The *addons* folder is not included in free version. The files list:

- *PackApp.php* - applications packer class
- *PackApp.log* - packer log (created dynamically) 
- *addons/.htaccess* - deny access from outside
- *addons/PackAppO.php* - obfuscation extension class
- *addons/PackAppS.php* - services class
- *addons/PackAppS.json* - configuration settings
- *plugins/PackCSS.php* - stylesheets minifier class by [Tubal Martin]
- *plugins/PackHTM.php* - html's minifier class by [Stephen Clay]
- *plugins/PackJS.php* - js minifier class by [Ryan Grove] and js compressor class by [Nicolas Martin], originally by *Dean Edwards*
- *plugins/PackJSON.php* - json minifier class by [Tiste], originally by [Kyle Simpson]
- *plugins/PackPHP.php* - php minifier class, adjusted from the algorithm by [GelaMu]
- *plugins/PackXML.php* - xml minifier class by [Vallo Reima]
- *tests/test.\** - test folders/files for the usage sample
- *example.php* - instantiation sample script
- *index.php* - GUI starter script (except free version)

Special thanks to the authors referred. Please [contact] for any questions regarding the Packer.

[contact]: mailto://vallo@vregistry.com
[here]: http://vregistry.com
[Tubal Martin]: https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port
[Stephen Clay]: https://github.com/mrclay/minify
[Nicolas Martin]: http://joliclic.free.fr/php/javascript-packer/en/
[Ryan Grove]: https://github.com/rgrove/jsmin-php
[Tiste]: https://github.com/T1st3/php-json-minify
[Kyle Simpson]: https://github.com/getify
[GelaMu]: http://php.net/manual/en/function.php-strip-whitespace.php
[Vallo Reima]: https://github.com/hareko/php-merge-xml

## ChangeLog ##
- 16 Aug 2016
    - Compact CSS option (either Reinhold-Weber or YUICompressor method)
    - GUI result download
