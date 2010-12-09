<?php
/**
 * Encoding: UTF-8
 * Requirements:
 * - PHP 5.3, with extensions: spl, zip, pcre, iconv, mbstring
 * - SimpleTest (not bundled)
 **/

mb_internal_encoding('UTF-8');

if (PHP_SAPI != 'cli') {
    die("It is a very bad idea to run these tests with a non CLI sapi.");
}

if (strpos(PHP_OS, 'WIN') === 0) {
    set_include_path('C:/AMP/;.'); // To find external SimpleTest
    define('FS_ENCODING', 'CP1252');
    // Use slashes not backslashes and trailing slash required
    define('TMP_DIR', !empty($_SERVER['TMP']) ? str_replace('\\', '/', $_SERVER['TMP'] . '/') : 'C:/WINDOWS/Temp/');
    define('WIN', TRUE);
} else {
    set_include_path($_SERVER['HOME'] . ':.'); // To find external SimpleTest
    define('FS_ENCODING', 'UTF-8');
    define('TMP_DIR', !empty($_SERVER['TMP']) ? $_SERVER['TMP'] . '/' : '/tmp/'); // Trailing slash required
    define('WIN', FALSE);
}

chdir(TMP_DIR);

$inputs = array();
$inputs['RELATIVE_INPUT_DIR'] = 'premier/deuxième/troisième/'; // Trailing slash required
$inputs['ABSOLUTE_INPUT_DIR'] = TMP_DIR . $inputs['RELATIVE_INPUT_DIR'];
$outputs = array();
$outputs['RELATIVE_OUTPUT_DIR'] = 'où' . uniqid() . '/'; // Trailing slash required
$outputs['ABSOLUTE_OUTPUT_DIR'] = TMP_DIR . $outputs ['RELATIVE_OUTPUT_DIR'];

if (WIN) {
    foreach (array_keys($inputs) as $k) {
        $inputs['WIN_' . $k] = str_replace('/', '\\', $inputs[$k]);
    }
    foreach (array_keys($outputs) as $k) {
        $outputs['WIN_' . $k] = str_replace('/', '\\', $outputs[$k]);
    }
}

foreach (array_merge($inputs, $outputs) as $k => $v) {
    define($k, $v);
}

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');
require_once('simpletest/simpletest/colortext_reporter.php');

require_once(__DIR__ . '/../ImprovedZipArchive.php');

class TestOfImprovedZipArchive extends UnitTestCase {

    protected $archives = array();

    protected static function php2fs($string) {
        return iconv('UTF-8', FS_ENCODING, $string);
    }

    protected static function mkdir($path) {
        $path = self::php2fs($path);
        $parts = preg_split('#/|' . preg_quote(DIRECTORY_SEPARATOR) . '#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $base = (iconv_substr($path, 0, 1, FS_ENCODING) == '/' ? '/' : '');
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

    protected static function create_file($path, $content) {
        // TODO: add mkdir in case of inexistant parent directory?
        return file_put_contents(self::php2fs($path), $content);
    }

    protected function makePath($methodname) {
        $key = TMP_DIR . $methodname . '.zip';
        //$this->archives[$key] = NULL;
        return $key;
    }

    protected static function is_file($path) {
        return is_file(self::php2fs($path));
    }

    protected static function unlink($path) {
        return unlink(self::php2fs($path));
    }

    public function __construct() {
        parent::__construct();

        self::mkdir(ABSOLUTE_INPUT_DIR);
        self::create_file(ABSOLUTE_INPUT_DIR . '/quatrième.txt', uniqid());
        self::mkdir(ABSOLUTE_OUTPUT_DIR);
    }

    public function __destruct() {
        /*array_walk(
            array_keys($this->archives),
            'self::unlink'
        );*/
        // rm -fr IN_PATH + OUT_PATH
    }

    public function setUp() {
        //echo __FUNCTION__, PHP_EOL;
    }

    public function tearDown() {
        //echo __FUNCTION__, PHP_EOL;
    }

    public function testAddFile() {
        global $inputs;

        $archivepath = $this->makePath(__FUNCTION__);
        foreach ($inputs as $k => $v) {
            $zip = ImprovedZipArchive::create($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
            $this->assertTrue($zip->addFile($v . 'quatrième.txt'));
            $this->assertIdentical($zip->getNameIndex(0), $v . 'quatrième.txt', __FUNCTION__ . ' ' . $k . ': %s');
            $zip->close();
            $zip = NULL;
            self::unlink($archivepath);
        }
    }

    public function testExtractTo() {
        global $outputs;

        $archivepath = $this->makePath(__FUNCTION__);
        $zip = ImprovedZipArchive::create($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
        // On windows, absolute path gives an invalid path when extracting
        $this->assertTrue($zip->addFile(RELATIVE_INPUT_DIR . 'quatrième.txt'));
        $zip->close();
        $zip = NULL;
        $zip = ImprovedZipArchive::read($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
        foreach ($outputs as $k => $v) {
            $this->assertTrue($zip->extractTo($v));
            $this->assertTrue(self::is_file($v . (strpos($k, 'WIN_') === 0 ? WIN_RELATIVE_INPUT_DIR : RELATIVE_INPUT_DIR) . 'quatrième.txt'), __FUNCTION__ . ' ' . $k . ': %s'); // assertFileExists
        }
        $zip->close();
        $zip = NULL;
        self::unlink($archivepath);
    }

    public function testAddFromString() {
        $archivepath = $this->makePath(__FUNCTION__);
        $zip = ImprovedZipArchive::create($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
        $this->assertTrue($zip->addFromString('fuß.txt', uniqid()));
        $this->assertIdentical($zip->getNameIndex(0), 'fuß.txt');
        $zip->close();
        $zip = NULL;
        self::unlink($archivepath);
    }

    public function testAddEmptyDir() {
        $archivepath = $this->makePath(__FUNCTION__);
        $zip = ImprovedZipArchive::create($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
        $this->assertTrue($zip->addEmptyDir('encyclopædia', uniqid()));
        $this->assertIdentical($zip->getNameIndex(0), 'encyclopædia/');
        $zip->close();
        $zip = NULL;
        self::unlink($archivepath);
    }

    public function testAddGlob() {
        // TODO
    }

    public function testAddPattern() {
        // TODO
    }

    public function testStat() {
        $entry = 'Noël.txt';
        $archivepath = $this->makePath(__FUNCTION__);
        $zip = ImprovedZipArchive::create($archivepath, FS_ENCODING, 'UTF-8', 'CP850');
        $this->assertTrue($zip->addFromString($entry, uniqid()));
        $ret = $zip->statName($entry);
        $this->assertTrue(is_array($ret) && isset($ret['name']));
        $this->assertIdentical($ret['name'], $entry);

        $ret = $zip->statIndex(0);
        $this->assertTrue(is_array($ret) && isset($ret['name']));
        $this->assertIdentical($ret['name'], $entry);
        $zip->close();
        $zip = NULL;
        self::unlink($archivepath);
    }
}

$t = new TestOfImprovedZipArchive();
$t->run(WIN ? new TextReporter() : new ColorTextReporter());