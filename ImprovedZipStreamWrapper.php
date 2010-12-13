<?php
class ImprovedZipStreamWrapper {

    protected $_archive; // The archive (as ImprovedZipArchive object)

    protected $_offset = 0; // Position of the internal pointer (in bytes)

    protected $_entry_index; // Index of the targetted entry in to the archive

    protected $_data; // Data of the targetted entry

    protected $_data_length; // Data length (in bytes)

    public static function register() {
        stream_wrapper_unregister('zip');
        stream_wrapper_register('zip', /*version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : */__CLASS__);
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($mode[0] != 'r') {
            return FALSE;
        }
        if (($fragment_pos = mb_strpos($path, '#')) === FALSE) {
            return FALSE;
        }
        $archivepath = mb_substr($path, strlen('zip://'), $fragment_pos - strlen('zip://'));
        $entry = mb_substr($path, $fragment_pos + 1);
        $this->_archive = call_user_func_array('ImprovedZipArchive::read', array_values(array_merge(array($archivepath, 'FS' => '', 'PHP' => '', 'ZIP' => ImprovedZipArchive::ENC_DEFAULT), stream_context_get_options($this->context))));
        if (($this->_entry_index = $this->_archive->locateName($entry)) === FALSE) {
            return FALSE;
        }
        if (($this->_data = $this->_archive->getFromIndex($this->_entry_index)) === FALSE) {
            return FALSE;
        }
        $this->_data_length = strlen($this->_data);

        return TRUE;
    }

    public function stream_stat() {
        return $this->_archive->statIndex($this->_offset);
    }

    public function stream_read($count) {
        $ret = substr($this->_data, $this->_offset, $count);
        $this->_offset += strlen($ret);
        return $ret;
    }

    public function stream_tell() {
        return $this->_offset;
    }

    public function stream_eof() {
        return $this->_offset >= $this->_data_length;
    }

    public function stream_close() {
        $this->_archive->close();
    }

    public function stream_seek($offset, $whence) {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < $this->_data_length && $offset >= 0) {
                     $this->_offset = $offset;
                     return TRUE;
                }
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
}
