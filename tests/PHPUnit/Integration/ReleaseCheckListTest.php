<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Integration;

use Exception;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Ini\IniReader;
use Piwik\Plugin\Manager;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Piwik\Tracker;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @group Core
 * @group ReleaseCheckListTest
 */
class ReleaseCheckListTest extends \PHPUnit_Framework_TestCase
{
    private $globalConfig;

    const MINIMUM_PHP_VERSION = '5.5.9';

    public function setUp()
    {
        $iniReader = new IniReader();
        $this->globalConfig = $iniReader->readFile(PIWIK_PATH_TEST_TO_ROOT . '/config/global.ini.php');

        parent::setUp();
    }

    public function test_woff2_isUpToDate() {
        $allowed_time_difference = 60 * 60 * 24; #seconds

        print "git log -1 " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff\n";
        print shell_exec("git log -1 " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff")."\n";

        print "git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff2\n";
        print shell_exec("git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff2")."\n";
        $woff_last_change = strtotime(shell_exec("git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff"));
        $woff2_last_change = strtotime(shell_exec("git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/matomo.woff2"));
        print $woff_last_change."\n";
        print $woff2_last_change."\n";
        $this->assertLessThan($allowed_time_difference, abs($woff_last_change - $woff2_last_change));

        $legacy_woff_last_change = strtotime(shell_exec("git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/piwik.woff"));
        $legacy_woff2_last_change = strtotime(shell_exec("git log -1 --format='%ad' " . PIWIK_DOCUMENT_ROOT . "/plugins/Morpheus/fonts/piwik.woff2"));
        $this->assertLessThan($allowed_time_difference, abs($legacy_woff_last_change - $legacy_woff2_last_change));
    }

    public function testTmpDirectoryContainsGitKeep()
    {
        $this->assertFileExists(PIWIK_DOCUMENT_ROOT . '/tmp/.gitkeep');
    }

    private function checkFilesAreInPngFormat($files)
    {
        $this->checkFilesAreInFormat($files, "png");
    }
    private function checkFilesAreInJpgFormat($files)
    {
        $this->checkFilesAreInFormat($files, "jpeg");
    }

    private function checkFilesAreInGifFormat($files)
    {
        $this->checkFilesAreInFormat($files, "gif");
    }

    private function checkFilesAreInFormat($files, $format)
    {
        $errors = array();
        foreach ($files as $file) {
            // skip files in these folders
            if (strpos($file, '/libs/') !== false) {
                continue;
            }

            $function = "imagecreatefrom" . $format;
            if (!function_exists($function)) {
                throw new \Exception("Unexpected error: $function function does not exist!");
            }

            $handle = @$function($file);
            if (empty($handle)) {
                $errors[] = $file;
            }
        }

        if (!empty($errors)) {
            $icons = implode(" ", $errors);
            $this->fail("$format format failed for following icons $icons \n");
        }
    }

    /**
     * @return bool
     */
    protected function isSkipPhpFileStartWithPhpBlock($file, $isIniFile)
    {
        $isIniFileInTests = strpos($file, "/tests/") !== false;
        $isTestResultFile = strpos($file, "/System/expected") !== false
            || strpos($file, "tests/resources/Updater/") !== false
            || strpos($file, "Twig/Tests/") !== false
            || strpos($file, "processed/") !== false
            || strpos($file, "/vendor/") !== false
            || (strpos($file, "tmp/") !== false && strpos($file, 'index.php') !== false);
        $isLib = strpos($file, "lib/xhprof") !== false || strpos($file, "phpunit/phpunit") !== false;

        return ($isIniFile && $isIniFileInTests) || $isTestResultFile || $isLib;
    }

    /**
     * @return bool
     */
    protected function isPathAddedToGit($pluginPath)
    {
        $gitOutput = shell_exec('git ls-files ' . $pluginPath . ' --error-unmatch 2>&1');
        $addedToGit = (strlen($gitOutput) > 0) && strpos($gitOutput, 'error: pathspec') === false;
        return $addedToGit;
    }


    /**
     * Tests that the Piwik files are not too big, to ensure the downloadable ZIP package is not too large
     */
    public function test_TotalPiwikFilesSize_isWithinReasonnableSize()
    {
        if(!SystemTestCase::isTravisCI()) {
            // Don't run the test on local dev machine, as we may have other files (not in GIT) that would fail this test
            $this->markTestSkipped("Skipped this test on local dev environment.");
        }
        $maximumTotalFilesizesExpectedInMb = 51;
        $minimumTotalFilesizesExpectedInMb = 38;
        $minimumExpectedFilesCount = 7000;

        $filesizes = $this->getAllFilesizes();
        $sumFilesizes = array_sum($filesizes);

        $filesOrderedBySize = $filesizes;
        arsort($filesOrderedBySize);

        $this->assertLessThan(
            $maximumTotalFilesizesExpectedInMb * 1024 * 1024,
            $sumFilesizes,
            sprintf("Sum of all files should be less than $maximumTotalFilesizesExpectedInMb Mb.
                    \nGot total file sizes of: %d Mb.
                    \nBiggest files: %s",
                $sumFilesizes / 1024 / 1024,
                var_export(array_slice($filesOrderedBySize, 0, 100, $preserveKeys = true), true)
            )
        );

        $this->assertGreaterThan($minimumExpectedFilesCount, count($filesizes), "Expected at least $minimumExpectedFilesCount files should be included in Piwik.");
        $this->assertGreaterThan($minimumTotalFilesizesExpectedInMb * 1024 * 1024, $sumFilesizes, "expected to have at least $minimumTotalFilesizesExpectedInMb Mb of files in Piwik codebase.");
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFileIncludedInFinalRelease($file)
    {
        if(is_dir($file)) {
            return false;
        }

        // in build-package.sh we have: `find ./ -iname 'tests' -type d -prune -exec rm -rf {} \;`
        if($this->isFileBelongToTests($file)) {
            return false;
        }
        if(strpos($file, PIWIK_INCLUDE_PATH . "/tmp/") !== false) {
            return false;
        }

        // ignore downloaded geoip files
        if(strpos($file, 'GeoIP') !== false && strpos($file, '.dat') !== false) {
            return false;
        }

        if($this->isFileIsAnIconButDoesNotBelongToDistribution($file)) {
            return false;
        }


        if($this->isPluginSubmoduleAndThereforeNotFoundInFinalRelease($file)) {
            return false;
        }

        if($this->isFileBelongToComposerDevelopmentPackage($file)) {
            return false;
        }

        if($this->isFileDeletedFromPackage($file)) {
            return false;
        }

        return true;
    }

    /**
     * Plugins Submodule in Piwik codebase are not there in the release package,
     * (the plugins are released on the Marketplace.)
     *
     * @param $file
     * @return bool
     */
    private function isPluginSubmoduleAndThereforeNotFoundInFinalRelease($file)
    {
        if(strpos($file, PIWIK_INCLUDE_PATH . "/plugins/") === false) {
            return false;
        }

        $pluginName = str_replace(PIWIK_INCLUDE_PATH . "/plugins/", "", $file);
        $pluginName = substr($pluginName, 0, strpos($pluginName, "/"));

        $this->assertNotEmpty($pluginName, "Detected an empty plugin name from path: $file ");

        $pluginManager = Manager::getInstance();
        $notInPackagedRelease = $pluginManager->isPluginOfficialAndNotBundledWithCore($pluginName);

        // test that the submodule check works
        if($pluginName == 'VisitorGenerator') {
            $this->assertTrue($notInPackagedRelease, "Expected isPluginOfficialAndNotBundledWithCore to return true for VisitorGenerator plugin");
        }
        return $notInPackagedRelease;
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFileBelongToComposerDevelopmentPackage($file)
    {
        $composerDependencyDevOnly = $this->getComposerRequireDevPackages();

        return $this->isFilePathFoundInArray($file, $composerDependencyDevOnly);
    }

    /**
     * @return array
     */
    private function getComposerRequireDevPackages()
    {
        $composerJson = $this->getComposerJsonAsArray();
        $composerDependencyDevOnly = array_keys($composerJson["require-dev"]);
        return $composerDependencyDevOnly;
    }

    /**
     * return true if $file is found within any sub-string in $filesToMatchAgainst,
     *
     * @param $file
     * @param $filesToMatchAgainst array
     * @return bool
     */
    private function isFilePathFoundInArray($file, $filesToMatchAgainst)
    {
        foreach ($filesToMatchAgainst as $devPackageName) {
            if (strpos($file, $devPackageName) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFileDeletedFromPackage($file)
    {
        $filesDeletedFromPackage = array(
            // Should stay synchronised with: https://github.com/piwik/piwik-package/blob/master/scripts/build-package.sh#L104-L116
            'composer.phar',
            'vendor/twig/twig/test/',
            'vendor/twig/twig/doc/',
            'vendor/symfony/console/Symfony/Component/Console/Resources/bin',
            'vendor/doctrine/cache/.git',
            'vendor/tecnickcom/tcpdf/examples',
            'vendor/tecnickcom/tcpdf/CHANGELOG.TXT',
            'vendor/php-di/php-di/benchmarks/',
            'vendor/guzzle/guzzle/docs/',
            'vendor/geoip2/geoip2/.gitmodules',
            'vendor/geoip2/geoip2/.php_cs',
            'vendor/maxmind-db/reader/ext/',
            'vendor/maxmind-db/reader/autoload.php',
            'vendor/maxmind-db/reader/CHANGELOG.md',
            'vendor/maxmind/web-service-common/dev-bin/',
            'vendor/maxmind/web-service-common/CHANGELOG.md',

            // deleted fonts folders
            'vendor/tecnickcom/tcpdf/fonts/ae_fonts_2.0',
            'vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-2.33',
            'vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-2.34',
            'vendor/tecnickcom/tcpdf/fonts/freefont-20100919',
            'vendor/tecnickcom/tcpdf/fonts/freefont-20120503',

            // In the package script, there is a trailing * so any font matching will be deleted
            'vendor/tecnickcom/tcpdf/fonts/freemon',
            'vendor/tecnickcom/tcpdf/fonts/cid',
            'vendor/tecnickcom/tcpdf/fonts/courier',
            'vendor/tecnickcom/tcpdf/fonts/aefurat',
            'vendor/tecnickcom/tcpdf/fonts/dejavusansb',
            'vendor/tecnickcom/tcpdf/fonts/dejavusansi',
            'vendor/tecnickcom/tcpdf/fonts/dejavusansmono',
            'vendor/tecnickcom/tcpdf/fonts/dejavusanscondensed',
            'vendor/tecnickcom/tcpdf/fonts/dejavusansextralight',
            'vendor/tecnickcom/tcpdf/fonts/dejavuserif',
            'vendor/tecnickcom/tcpdf/fonts/freesansi',
            'vendor/tecnickcom/tcpdf/fonts/freesansb',
            'vendor/tecnickcom/tcpdf/fonts/freeserifb',
            'vendor/tecnickcom/tcpdf/fonts/freeserifi',
            'vendor/tecnickcom/tcpdf/fonts/pdf',
            'vendor/tecnickcom/tcpdf/fonts/times',
            'vendor/tecnickcom/tcpdf/fonts/uni2cid',
        );

        return $this->isFilePathFoundInArray($file, $filesDeletedFromPackage);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getAllFilesizes()
    {
        $files = Filesystem::globr(PIWIK_INCLUDE_PATH, '*');

        $filesizes = array();
        foreach ($files as $file) {

            if (!$this->isFileIncludedInFinalRelease($file)) {
                continue;
            }

            $filesize = filesize($file);

            if ($filesize === false) {
                throw new Exception("Error getting filesize for file: $file");
            }
            $filesizes[$file] = $filesize;
        }
        return $filesizes;
    }

    /**
     * @param $files
     * @throws Exception
     */
    protected function checkFilesDoNotHaveWeirdSpaces($files)
    {
        $weirdSpace = ' ';
        $this->assertEquals('c2a0', bin2hex($weirdSpace), "Checking that this test file was not tampered with");
        $this->assertEquals('20', bin2hex(' '), "Checking that this test file was not tampered with");

        $errors = array();
        $countFileChecked = 0;
        foreach ($files as $file) {

            if($this->isFileBelongToTests($file)) {
                continue;
            }

            if(strpos($file, 'vendor/php-di/php-di/website/') !== false) {
                continue;
            }

            $content = file_get_contents($file);
            $posWeirdSpace = strpos($content, $weirdSpace);
            if ($posWeirdSpace !== false) {
                $around = substr($content, $posWeirdSpace - 20, 40);
                $around = trim($around);
                $errors[] = "File $file contains an unusual space character, please remove it from here: ...$around...";
            }

            $countFileChecked++;
        }
        $this->assertTrue($countFileChecked > 42, "expected to test at least 100 files, but tested only " . $countFileChecked);

        if (!empty($errors)) {
            throw new Exception(implode(",\n\n ", $errors));
        }
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFileBelongToTests($file)
    {
        return stripos($file, "/tests/") !== false || stripos($file, "/phantomjs/") !== false;
    }

    /**
     * @return mixed
     */
    private function getComposerJsonAsArray()
    {
        $composer = file_get_contents(PIWIK_INCLUDE_PATH . '/composer.json');
        $composerJson = json_decode($composer, $assoc = true);
        return $composerJson;
    }

    /**
     * ignore icon source files as they are large, but not included in the final package
     *
     */
    private function isFileIsAnIconButDoesNotBelongToDistribution($file)
    {
        return preg_match('~Morpheus/icons/(?!dist)~', $file);
    }

}
