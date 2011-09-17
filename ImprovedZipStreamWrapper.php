<?php
class ImprovedZipStreamWrapper {

    protected $_archive; // The archive (as ImprovedZipArchive object)

    protected $_offset = 0; // Position of the internal pointer (in bytes)

    protected $_entry_index; // Index of the targetted entry in to the archive

    protected $_data; // Data of the targetted entry

    protected $_data_length; // Data length (in bytes)

    public static function register()
    {
        stream_wrapper_unregister('zip');
        stream_wrapper_register('zip', version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__);
    }

    protected function _parse($url, &$archivepath, &$entry) // instance method, should consider (PHP) encoding
    {
        if (FALSE === ($fragment_pos = mb_strpos($url, '#'))) {
            return FALSE;
        }

        $archivepath = mb_substr($url, strlen('zip://'), $fragment_pos - strlen('zip://'));
        $entry = mb_substr($url, $fragment_pos + 1);

        if ('' === $archivepath || '' === $entry) {
            return FALSE;
        }

        return TRUE; //array('archivepath' => $archivepath, 'entry' => $entry);
    }

    protected function _open($archivepath)
    {
        static $default_options = array(
            'translit' => FALSE,
            'encodings' => array(
                'fs' => '',
                'php' => '',
                'zip' => ImprovedZipArchive::ENC_DEFAULT,
            ),
        );
        $options = array_merge($default_options, stream_context_get_options($this->context));

        $this->_archive = ImprovedZipArchive::read(
            $archivepath,
            $options['encodings']['fs'],
            $options['encodings']['php'],
            $options['encodings']['zip'],
            $options['translit']
        );

        return FALSE !== $this->_archive;
    }

    public function stream_open($url, $mode, /* unused */ $options, /*unused */ &$opened_path)
    {
        if ('r' != $mode[0]) {
            return FALSE;
        }
        if (!$this->_parse($url, $archivepath, $entry)) {
            return FALSE;
        }
        if (!$this->_open($archivepath)) {
            return FALSE;
        }
        if (FALSE === ($this->_entry_index = $this->_archive->locateName($entry))) {
            return FALSE;
        }
        if (FALSE === ($this->_data = $this->_archive->getFromIndex($this->_entry_index))) {
            return FALSE;
        }
        $this->_data_length = strlen($this->_data);

        return TRUE;
    }

    public function stream_stat()
    {
        return $this->_archive->statIndex($this->_offset);
    }

    public function stream_read($count)
    {
        $ret = substr($this->_data, $this->_offset, $count);
        $this->_offset += strlen($ret);
        return $ret;
    }

    public function stream_tell()
    {
        return $this->_offset;
    }

    public function stream_eof()
    {
        return $this->_offset >= $this->_data_length;
    }

    public function stream_close()
    {
        $this->_archive->close();
    }

    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < $this->_data_length && $offset >= 0) {
                     $this->_offset = $offset;
                     return TRUE;
                }
                break;
            case SEEK_CUR:
                if ($offset >= 0) {
                     $this->_offset += $offset;
                     return TRUE;
                }
                break;
            case SEEK_END:
                if ($this->_data_length + $offset >= 0) {
                     $this->_offset = $this->_data_length + $offset;
                     return TRUE;
                }
                break;
        }

        return FALSE;
    }

    public function mkdir($url, /* unused */ $mode, /* unused */ $options)
    {
        if (!$this->_parse($url, $archivepath, $entry)) {
            return FALSE;
        }
        if (!$this->_open($archivepath)) {
            return FALSE;
        }

        $ret = $this->_archive->addEmptyDir($entry);
        $this->_archive->close();

        return $ret;
    }

    public function rename($from, $to)
    {
        if (!$this->_parse($from, $fromarchivepath, $fromentry)) {
            return FALSE;
        }
        if (!$this->_parse($to, $toarchivepath, $toentry)) {
            return FALSE;
        }
        if ($fromarchivepath != $toarchivepath) {
            return FALSE;
        }
        if (!$this->_open($fromarchivepath)) {
            return FALSE;
        }
        $ret = $this->_archive->renameName($fromentry, $toentry);
        $this->_archive->close();

        return $ret;
    }

    protected function _rm($url)
    {
        if (!$this->_parse($url, $archivepath, $entry)) {
            return FALSE;
        }
        if (!$this->_open($archivepath)) {
            return FALSE;
        }

        $ret = $this->_archive->deleteName($entry);
        $this->_archive->close();

        return $ret;
    }

    public function rmdir($path, $options)
    {
        return $this->_rm($path);
    }

    public function unlink($path)
    {
        return $this->_rm($path);
    }
}
