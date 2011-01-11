<?php
/**
 * ImprovedZipArchive:
 *
 * Is free software, licensed under LGPL
 *
 * Hosted by github: https://github.com/julp/ImprovedZipArchive
 *
 * Documentation: https://github.com/julp/ImprovedZipArchive/wiki
 **/
class ImprovedZipArchive extends ZipArchive implements Iterator, Countable
{
    const VERSION = '0.3.0';

    const ENC_OLD_EUROPEAN = 'IBM850'; # CP850
    const ENC_OLD_NON_EUROPEAN = 'IBM437'; # CP437
    //const ENC_NEW = 'UTF-8';
    const ENC_DEFAULT = self::ENC_OLD_EUROPEAN;

    protected $_fs_enc;      // File System encoding
    protected $_php_int_enc; // PHP encoding
    protected $_zip_int_enc; // ZIP archive encoding
    protected $_translit;    // Transliterate?

    protected $_it_pos = 0;  // Internal position of the iterator

    public function __construct(/*$name, $mode, */$fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT, $translit = FALSE)
    {
        /*static $errors = array(
            self::ER_OK          => 'No error',
            self::ER_MULTIDISK   => 'Multi-disk zip archives not supported',
            self::ER_RENAME      => 'Renaming temporary file failed',
            self::ER_CLOSE       => 'Closing zip archive failed',
            self::ER_SEEK        => 'Seek error',
            self::ER_READ        => 'Read error',
            self::ER_WRITE       => 'Write error',
            self::ER_CRC         => 'CRC error',
            self::ER_ZIPCLOSED   => 'Containing zip archive was closed',
            self::ER_NOENT       => 'No such file',
            self::ER_EXISTS      => 'File already exists',
            self::ER_OPEN        => "Can't open file",
            self::ER_TMPOPEN     => 'Failure to create temporary file',
            self::ER_ZLIB        => 'Zlib error',
            self::ER_MEMORY      => 'Malloc failure',
            self::ER_CHANGED     => 'Entry has been changed',
            self::ER_COMPNOTSUPP => 'Compression method not supported',
            self::ER_EOF         => 'Premature EOF',
            self::ER_INVAL       => 'Invalid argument',
            self::ER_NOZIP       => 'Not a zip archive',
            self::ER_INTERNAL    => 'Internal error',
            self::ER_INCONS      => 'Zip archive inconsistent',
            self::ER_REMOVE      => "Can't remove file",
            self::ER_DELETED     => 'Entry has been deleted',
        );*/

        $this->_zip_int_enc = $zip_enc;

        if (!$php_enc) {
            $this->_php_int_enc = mb_internal_encoding();
        } else {
            if (!mb_internal_encoding($php_enc)) {
                throw new Exception(sprintf('Unknown encoding "%s"', $php_enc));
            }
            $this->_php_int_enc = $php_enc;
        }

        if (!$fs_enc) {
            $this->_fs_enc = mb_internal_encoding();
        } else {
            $this->_fs_enc = $fs_enc;
        }

        $this->_translit = $translit;

        /*if ($ret = $this->open($this->_phpToFs($name), $mode) !== TRUE) {
            throw new Exception($errors[$ret], $ret); // we can't use $this->getStatusString() (underlaying object uninitialized)
        }*/
    }

    public function open($name, $flags = 0)
    {
        return parent::open($this->_phpToFs($name), $flags);
    }

    public static function read($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT, $translit = FALSE)
    {
        $class = version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__; // new static
        /*return */$iza = new $class(/*$name, 0, */$fs_enc, $php_enc, $zip_enc, $translit);
        if (TRUE !== $iza->open($name, 0)) {
            return NULL; // TODO
        }

        return $iza;
    }

    public static function create($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT, $translit = FALSE)
    {
        $class = version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__; // new static
        /*return */$iza = new $class(/*$name, 0, */$fs_enc, $php_enc, $zip_enc, $translit);
        if (TRUE !== $iza->open($name, self::CREATE | self::EXCL)) {
            return NULL; // TODO
        }

        return $iza;
    }

    public static function overwrite($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT, $translit = FALSE)
    {
        $class = version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__; // new static
        /*return */$iza = new $class(/*$name, 0, */$fs_enc, $php_enc, $zip_enc, $translit);
        if (TRUE !== $iza->open($name, self::OVERWRITE)) {
            return NULL; // TODO
        }

        return $iza;
    }

    protected static function _iconv_helper($from, $to, $string)
    {
        if (($ret = iconv($from, $to, $string)) === FALSE) {
            throw new Exception(sprintf('Illegal character in input string or due to the conversion from "%s" to "%s"', $from, $to));
        }

        return $ret;
    }

    protected function _phpToZip($string)
    {
        return self::_iconv_helper($this->_php_int_enc, $this->_translit ? $this->_zip_int_enc . '//TRANSLIT' : $this->_zip_int_enc, $string);
    }

    protected function _zipToPHP($string)
    {
        return self::_iconv_helper($this->_zip_int_enc, $this->_php_int_enc, $string);
    }

    protected function _zipToFs($string)
    {
        return self::_iconv_helper($this->_zip_int_enc, $this->_fs_enc, $string);
    }

    protected function _fsToZip($string)
    {
        return self::_iconv_helper($this->_fs_enc, $this->_translit ? $this->_zip_int_enc . '//TRANSLIT' : $this->_zip_int_enc, $string);
    }

    protected function _phpToFs($string)
    {
        return self::_iconv_helper($this->_php_int_enc, $this->_fs_enc, $string);
    }

    protected function _fsToPHP($string)
    {
        return self::_iconv_helper($this->_fs_enc, $this->_php_int_enc, $string);
    }

    public function addFile($filename, $localname = '', $start = 0, $length = 0)
    {
        if ($localname === '') { // === operator required to permit '0' as filename
            $localname = $this->_phpToZip($filename);
        } else {
            $localname = $this->_phpToZip($localname);
        }

        return parent::addFile($this->_phpToFs($filename), $localname);
    }

    protected function _addFileFromFS($filename, $localname = '')
    {
        if ($localname === '') { // === operator required to permit '0' as filename
            $localname = $this->_fsToZip($filename);
        } else {
            $localname = $this->_phpToZip($localname);
        }

        return parent::addFile($filename, $localname);
    }

    public function addFromString($name, $content)
    {
        return parent::addFromString($this->_phpToZip($name), $content);
    }

    public function getFromName($name, $length = 0, $flags = 0)
    {
        return parent::getFromName($this->_phpToZip($name), $length, $flags);
    }

    public function deleteName($name)
    {
        return parent::deleteName($this->_phpToZip($name));
    }

    public function addEmptyDir($name)
    {
        return parent::addEmptyDir($this->_phpToZip($name));
    }

    public function getNameIndex($index, $flags = 0)
    {
        return $this->_zipToPHP(parent::getNameIndex($index, $flags));
    }

    public function locateName($name, $flags = 0)
    {
        return parent::locateName($this->_phpToZip($name), $flags);
    }

    public function renameIndex($index, $name)
    {
        return parent::renameIndex($index, $this->_phpToZip($name));
    }

    public function renameName($old_name, $new_name)
    {
        return parent::renameName($this->_phpToZip($old_name), $this->_phpToZip($new_name));
    }

    public function setCommentName($name, $comment)
    {
        return parent::setCommentName($this->_phpToZip($name), $comment);
    }

    private function _decodeStatResult($ret)
    {
        if (is_array($ret) && isset($ret['name'])) {
            $ret['name'] = $this->_zipToPHP($ret['name']);
        }

        return $ret;
    }

    public function statName($name, $flags = 0)
    {
        return $this->_decodeStatResult(parent::statName($this->_phpToZip($name), $flags));
    }

    public function statIndex($index, $flags = 0)
    {
        return $this->_decodeStatResult(parent::statIndex($index, $flags));
    }

    public function unchangeName($name)
    {
        return parent::unchangeName($this->_phpToZip($name));
    }

    public function getCommentName($name, $flags = 0)
    {
        return parent::getCommentName($this->_phpToZip($name), $flags);
    }

    public function getStream($entry)
    {
        return parent::getStream($this->_phpToZip($entry));
    }

    protected function mkdir_p($path)
    {
        $parts = preg_split('#/|' . preg_quote(DIRECTORY_SEPARATOR) . '#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $base = (iconv_substr($path, 0, 1, $this->_fs_enc) == '/' ? '/' : '');
        foreach ($parts as $p) {
            if (!file_exists($base . $p)) {
                if (!mkdir($base . $p)) {
                    return FALSE;
                }
            } else if (!is_dir($base . $p)) {
                return FALSE;
            }
            $base .= $p . DIRECTORY_SEPARATOR;
        }

        return TRUE;
    }

    public function extractTo($destination, $entries = NULL)
    {
        if ($entries === NULL) {
            for ($i = 0; $i < $this->numFiles; $i++) {
                if (($name = $this->getNameIndex($i)) === FALSE) { // === operator required to permit '0' as filename
                    return FALSE;
                }
                if (($content = $this->getFromIndex($i)) === FALSE) { // === operator required to permit '0' or empty content
                    return FALSE;
                }
                $to = $this->_phpToFs($destination . $name);
                if (!$this->mkdir_p(dirname($to))) {
                    return FALSE;
                }
                if (mb_substr($name, -1, 1) != '/') {
                    if (file_put_contents($to, $content) === FALSE) { // === operator required to permit empty file creation (0 returned)
                        return FALSE;
                    }
                }
            }
        } else {
            if (is_string($entries)) {
                $entries = array($entries);
            }
            // Alternate way: combine the zip stream wrapper to stream_copy_to_stream
            foreach ($entries as $entry) {
                if (($content = $this->getFromName($entry)) === FALSE) { // === operator required to permit '0' or empty content
                    return FALSE;
                }
                $to = $this->_phpToFs($destination . $entry);
                if (!$this->mkdir_p(dirname($to))) {
                    return FALSE;
                }
                if (mb_substr($entry, -1, 1) != '/') { // not safe
                    if (file_put_contents($to, $content) === FALSE) { // === operator required to permit empty file creation (0 returned)
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    protected function _add_options(Array $options, &$add_path, &$remove_path, &$remove_all_path)
    {
        $remove_all_path = $remove_path = $add_path = FALSE;
        if (isset($options['remove_all_path'])) {
            $remove_all_path = !!$options['remove_all_path'];
        }
        if (isset($options['remove_path']) && is_string($options['remove_path'])) {
            $remove_path = str_replace('\\', '/', $options['remove_path']);
            if (!in_array(mb_substr($add_path, -1), array('/', '\\'))) {
                $remove_path .= '/';
            }
        }
        if (isset($options['add_path']) && is_string($options['add_path'])) {
            $add_path = str_replace('\\', '/', $options['add_path']);
            /*if (!in_array(mb_substr($add_path, -1, 1), array('/', '\\'))) { // add unwanted leading / on Windows
                $add_path .= '/';
            }*/
        }
    }

    protected function _make_path($add_path, $remove_path, $remove_all_path, $dirname, $basename)
    {
        $dirname = $this->_fsToPHP($dirname);
        $basename = $this->_fsToPHP($basename);

        if ($add_path) {
            if ($remove_all_path) {
                $zipname = $basename;
            } else if ($remove_path && mb_strpos($dirname, $remove_path) === 0) {
                $zipname = self::strip($remove_path, $dirname . '/' . $basename);
            } else {
                $zipname = $dirname . '/' . $basename;
            }
            $zipname = $add_path . $zipname;
        } else {
            $zipname = $dirname . '/' . $basename;
        }

        return $zipname;
    }

    public static function strip($prefix, $filename)
    {
        if (mb_strpos($filename, $prefix) === 0) {
            $filename = mb_substr($filename, strlen($prefix));
        }

        return $filename;
    }

    public function addPattern($pattern, $path = '.', $options = array())
    {
        $_pattern = $this->_phpToFs($pattern);
        $_path = $this->_phpToFs($path);

        if (!file_exists($_path)) {
            throw new Exception(sprintf('"%s" does not exist', $path));
        }

        if (!is_dir($_path)) {
            throw new Exception(sprintf('"%s" exists and is not a directory', $path));
        }

        $this->_add_options($options, $add_path, $remove_path, $remove_all_path);

        $iter = new RegexIterator(new DirectoryIterator($_path), $_pattern);
        foreach ($iter as $entry) {
            if ($entry->isDir() || $entry->isFile()) {
                $zipname = self::_make_path($add_path, $remove_path, $remove_all_path, $entry->getPath(), $entry->getFilename());
                if ($entry->isDir()) {
                    if (!$this->addEmptyDir($zipname)) {
                        return FALSE;
                    }
                } else if ($entry->isFile()) {
                    if (!$this->_addFileFromFS($entry->getRealPath(), $zipname)) {
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    public function addGlob($pattern, $flags = 0, $options = array())
    {
        $ret = glob($this->_phpToFs($pattern), $flags);
        if ($ret === FALSE) { // Operator === required to distinguish empty array of FALSE (failure)
            return FALSE;
        } else {
            $this->_add_options($options, $add_path, $remove_path, $remove_all_path);

            foreach ($ret as $entry) {
                $zipname = self::_make_path($add_path, $remove_path, $remove_all_path, dirname($entry), basename($entry));
                if (!$this->_addFileFromFS($entry, $zipname)) {
                    return FALSE;
                }
            }

            return TRUE;
        }
    }

    public function addRecursive($directory, $options = array())
    {
        $_directory = $this->_phpToFs($directory);

        if (!file_exists($_directory)) {
            throw new Exception(sprintf('"%s" does not exist', $directory));
        }

        if (!is_dir($_directory)) {
            throw new Exception(sprintf('"%s" exists and is not a directory', $directory));
        }

        $this->_add_options($options, $add_path, $remove_path, $remove_all_path);
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_directory), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iter as $entry) {
            if (!$iter->isDot() && ($entry->isDir() || $entry->isFile())) {
                $zipname = self::_make_path($add_path, $remove_path, $remove_all_path, $iter->getInnerIterator()->getPath(), $iter->getInnerIterator()->getSubPathname());
                if ($entry->isDir()) {
                    if (!$this->addEmptyDir($zipname)) {
                        return FALSE;
                    }
                } else if ($entry->isFile()) {
                    if (!$this->_addFileFromFS($iter->getRealPath(), $zipname)) {
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    public function rewind()
    {
        $this->_it_pos = 0;
    }

    public function current()
    {
        $ret = $this->statIndex($this->_it_pos);
        if (is_array($ret)) {
            $ret['comment'] = $this->getCommentIndex($this->_it_pos);
        }

        return $ret;
    }

    public function key()
    {
        return $this->_it_pos;
    }

    public function next()
    {
        $this->_it_pos++;
    }

    public function valid()
    {
        return $this->_it_pos < $this->numFiles;
    }

    public function count()
    {
        return $this->numFiles;
    }

    public function __toString()
    {
        return sprintf(
            '%s (name: %s, file(s): %d, comment: %s, encodings: zip = %s, php = %s, file system = %s)',
            version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__,
            $this->_fsToPHP($this->filename),
            $this->numFiles,
            $this->comment ? $this->comment : '-',
            $this->_zip_int_enc,
            $this->_php_int_enc,
            $this->_fs_enc
        );
    }
}
