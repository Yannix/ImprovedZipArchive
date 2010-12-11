# ImprovedZipArchive #

**Description:**

ImprovedZipArchive is a PHP class which extends the native class ZipArchive from the PHP5 zip extension, in order to:

1. do not have to manage any encoding conversion between PHP, file system and [outdated] zip [format] for the programmer
2. add (only) some usefull functionalities


**Warning:**

ImprovedZipArchive is **EXPERIMENTAL**. ImprovedZipArchive is free software (LGPL) and comes with ABSOLUTELY NO WARRANTY.


**Requirements:**

PHP 5 with extensions: zip, spl, pcre, mbstring and iconv.


**Author:**

julp ([website](http://julp.developpez.com/))


**Many thanks to:**

* benj (for his help)
* Gazoo (bug reports)

## License ##

License: LGPL

## Usage ##

    /**
     * encodings:
     * - php: UTF-8
     * - file system: ISO-8859-1
     * - zip: CP850 (default)
     **/

    if (PHP_SAPI != 'cli') {
        header('Content-type: text/html; charset=utf-8');
    }

    $zip = ImprovedZipArchive::create('élèves.zip', 'ISO-8859-1', 'UTF-8', ImprovedZipArchive::ENC_OLD_EUROPEAN);
    $zip->addFromString('CM2/Anaïs.txt', 'Un élève de la classe de CM2');
    $zip->addFile('CM2/Éloïse.txt');
    $zip->addRecursive('/home/julp/CM1/', array('add_path' => '/CM1/', 'remove_path' => '/home/julp/CM1'));
    $zip->close();

## Contribute ##

All:

* forks then pull requests (see [help](http://help.github.com/forking/))
* bug reports
* feedbacks
* comments
* requests

are welcome.

## TODO ##

* test add_path/remove_path/remove_all_path options (especially on windows with backslashes)
* add a transliteration option (//TRANSLIT) ? May be usefull with some characters like œ
* throw Exception instead of returning FALSE (except in ER_NOENT case ?) ?
* make a recursive addPattern implementation ?
* encode/decode comments (en/de $_zip_enc) ?
* in constructor: check encodings ? But mbstring is more limited than iconv.