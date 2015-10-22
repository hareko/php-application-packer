# PHP Application Packer #

A complete solution to pack your application project files into the delivery package. The source code is minified and obfuscated, impeding unauthorized use.

The target audience: ISVs, freelancers, developers, resellers,  all who need a lightweight utility to turn the project into the delivery package. 
The solution is oriented to the PHP applications, but can be used for your front-end projects too.

There are many minifiers, obfuscators, encoders around. Some commercial encoders are very good, but rather expensive and may require sy extensions. 
The others are limited with a single source type only. The Packer is a simple integrated solution.

## How it works ##
PackApp.php packs the project files: minifies the source (html, css, js, json, php, xml), obfuscates the code (js, php) and compresses the result files.
The source can be either a single file or a folder which may have sub-folders. 
The destination is a packed file or a folder with the packed files.
The ZIP archives are handled also: folder-to-zip, zip-to-folder, zip-to-zip.

The Packer passes the source to according minify add-on depending on the file type.
The *css* and *js* detected inside the *html* are minified too. 
The *html*, *css* and *js* are looked for the embedded *php* to minify. 
You can create the user add-ons for more file types.


The *js* and *php* code can be obfuscated to inhibit its unauthorized use. The reverse engineering becomes too expensive.
The identifiers replacement algorithm used here is more protective than trivial applying of *base64_encode* and *eval*.
However, you must follow certain naming rules to avoid the renaming conflicts. The *php* obfuscation is not supported by free version.

There are several options to control the processing. Use them for specific cases and rely on defaults mostly. The Packer supplies various statistics about the processing results. 
You can display and/or save this data.

## The usage ##

The program requires PHP 5.3+. The starter script must be in the same directory with the *PackApp.php*. Instantiation and invocation:

**$obj = new PackApp( [$lvl [,$opt]] );**

**$obj->Pack( $old [,$new [,$rpl]] );**

### The parameters ###

**$lvl** - obfuscation level integer (default by 0 - minify only):

- *0* - no
- *1* - JavaScript
- *2* - PHP
- *3* - both

**$opt** - the options array:

- **sgn** - signature to prepend to the minified *php/js/css* code (default by *PackApp*, specify *null* to omit)
- **exf** - array of the folder/file name wildcards to exclude from the packing (default by *['\*.min.\*']*)
- **aon** - user add-ons (see below)
- **sfx** - default destination name suffix (default by *'_pkd'*)
- **tml** - time limit in seconds the program is allowed to run (for larger projects, default by *30*)

PHP obfuscation options:

- **ids** - the identifiers replacement flags (in any sequence, lowercase means case-insensitive, default by *'VDHF'*):
    - *V* - variables/properties
    - *D|d* - defined and class constants (d - defined constants only)
    - *H* - heredocs/nowdocs
    - *F|f* - functions/methods
    - *T|t* - traits
    - *C|c* - classes
- **exi** - array of the identifier wildcards to exclude from the obfuscation (added to predefined ones)
- **pfx** - prefix of the replacement identifier (default by *'_'*)
- **len** - replacement identifier's left-padding length with zeroes (default by 0 - no padding)
- **dbg** - debugging flag (default by *false*, *true* - don't minify)
    
**$old** - source file/folder/archives

**$new** -- destination (default by **$old** value suffixed by **sfx** option value)

**$rpl** -- replace destination if exists (default by *false*)

### Return data ###

The **$obj->Pack()** returns the results associative array:

- **code** - *'ok'* means success; failed otherwise
- **prompt** - brief message about the success/fail/error
- **factor** - the statistics array (success); if string then the installer html (extended version only)
- **string** - the statistics text (newline-separated)

You can display and/or save the statistics. See *example.php* about the using.

## Packing ##

The files are minified by default and obfuscated (*js, php*) if required.
The source file is processed when its type matches one of the following (wildcards allowed);

- *\*htm\** - html code (*htm, html, phtml,* ...) 
- *css\** - stylesheet
- *js* - JavaScript code
- *json* - json string
- *xml* - xml source
- *php\** - php code
- *inc* - php code

The rest of the files are simply copied. Use the **aon** option for additional types (see below). The folder or archived source is saved to the destination folder or archived depending on the *$new* parameter. 


### Minifying ###

The source files of relevant file type are minified by removing the comments, whitespaces and linebreaks. 
The *\*htm\** files are checked for the *style* and *script* tags which content is minified too. 
The files (except *xml*) are checked also for the *php* tags to pack.

### Obfuscating ###

The minified source is obfuscated depending on the **$lvl** parameter value.
The JavaScript code is packed into the *eval()* statement replacing the original identifiers. The PHP obfuscation requires the extended version (see below).

## Add-ons ##

The package includes the add-ons to minify according sources (see *package*). 
You can define your own add-ons for additional file types with the **aon** option:

**['aon' => ['type' => flag]**

- *type* - source file type, must be in accordance with the class name (for example, *'sql'* requires *PackSQL.php*)
- *flag* - boolean *true* - check for the embedded php code, *false* - bypass check

For example:

*$obj = new PackApp(1, ['aon' => ['sql' => false]]);* 

Place your add-on class in the *minify* folder. Make it callable via static *minify* method like here:

```
...
public static function minify($source, $options = []) {
  $min = new self($options);
  return $min->process($source);
}
...
```

Please [contact] if you would like to include your own add-on(s) into the package.

PHP obfuscation
---------------
The extended version (*PackAppE* extension) is required for the PHP obfuscation which can be obtained from [here]. It's recommended to follow certain naming rules in the planning and coding stage already to avoid the renaming conflicts (see below). 

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

Use the identifiers exclusion list (**exi** option) and follow the naming rules to avoid the replaced identifiers conflicts:

1. Be careful with the variable variables (*$$var*) or add their names to the exclusion list (see example).
2. Don't use the variable names that are the same as any (system) object property name used in the application.
3. The user constant name must not coincide with any system constant name used in the application.
4. The element name which value is got via __get/__set magic methods from the array/object or computed, must not be the same as any variable/property name used in application.
5. Don't obfuscate the class/trait names which to be autoloaded, and class/method/function names specified in the callbacks, like *call_user_func('myclass','mymethod')*.
6. The identifiers prefixed by *'__'* are not obfuscated. You can use this for your identifiers exclusion too.
7. Prefix your identifiers or add to exclusion list, if necessary, to follow (2,3,4,5).
8. Be careful with the case-insensitive names of the functions, classes, traits and defined constants.
9. Sample naming conventions:
    - variables, properties, constants - in camelCase;
    - heredocs, nowdocs - in UPPERCASE;
    - functions, methods, classes, traits - in PascalCase;

These rules are natural to follow and secure from the identifier name collisions. Use the **dbg** option to fix the naming issues in the non-minified obfuscation result.

## Installation ##

Unzip the obtained package and upload its files to your selected web directory. Then run *example.php*. The extended version launches the installer if not set up yet. 
Run *PackApp.php* from the browser to launch the installer directly.

The included example minifies/obfuscates the files from the *tests* folder and compresses the result into the *tests.zip*. 
A message informs about the result. The statistics collected by the packer are displayed and saved into *example.txt*. 

Make a copy from the *example.php* and try it with different sources, destinations and options.

## The package ##

The *PackAppE.\** files are not included in free version. The *minify* folder contains the add-ons adapted from the open source. The following files are included:

- *PackApp.php* - applications packer class
- *PackAppE.php* - obfuscation extension class
- *PackAppE.json* - extension setup data (created dynamically)
- *minify/PackCSS.php* - stylesheets minifier class by [Tubal Martin]
- *minify/PackHTM.php* - html's minifier class by [Stephen Clay]
- *minify/PackJS.php* - js minifier class by [Ryan Grove] and compressor class by [Nicolas Martin] (originally by *Dean Edwards*)
- *minify/PackJSON.php* - json minifier class by [Tiste]
- *minify/PackPHP.php* - php minifier class, algorithm by [GelaMu]
- *minify/PackXML.php* - xml minifier class by [Vallo Reima]
- *tests/test.\** - test files for supported types
- *example.php* - usage sample script
- *readme.md*

Special thanks to the authors referred. Please [contact] for any questions regarding the package.

[contact]: mailto://vallo@vregistry.com
[here]: http://vregistry.com
[Tubal Martin]: https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port
[Stephen Clay]: https://github.com/mrclay/minify
[Nicolas Martin]: http://joliclic.free.fr/php/javascript-packer/en/
[Ryan Grove]: https://github.com/rgrove/jsmin-php
[Tiste]: https://github.com/T1st3/php-json-minify
[GelaMu]: http://php.net/manual/en/function.php-strip-whitespace.php
[Vallo Reima]: https://github.com/hareko/php-merge-xml
