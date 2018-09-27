<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\BaseFilter;
use Piwik\Date;

/**
 * Sanitizes DataTable labels as an extra precaution. Called internally by Piwik.
 *
 */
class SafeDecodeLabel extends BaseFilter
{
    /**
     * On this day we changed the default behavior of this filter. Before it called `urldecode()` on the label
     * which would turn '+' signs, for instance, into spaces. Now we don't do this, but there may be very old
     * archived data that has URL encoded labels. So for reports archived before this date, we decode the URL.
     */
    const DATE_OF_URLDECODE_CHANGE = '2018-09-25';

    private $columnToDecode;

    /**
     * @param DataTable $table
     */
    public function __construct($table)
    {
        parent::__construct($table);
        $this->columnToDecode = 'label';
    }

    public static function shouldUrlDecodeValue(DataTable $table)
    {
        $archivedDate = $table->getMetadata(DataTable::ARCHIVED_DATE_METADATA_NAME);
        if (empty($archivedDate)) {
            return false;
        }
        $archivedDate = Date::factory($archivedDate);

        $dateOfChange = Date::factory(self::DATE_OF_URLDECODE_CHANGE);

        $shouldDecode = $archivedDate->isEarlier($dateOfChange);
        return $shouldDecode;
    }

    /**
     * Decodes the given value
     *
     * @param string $value
     * @return mixed|string
     */
    public static function decodeLabelSafe($value, $shouldUrlDecode = true)
    {
        if (empty($value)) {
            return $value;
        }

        $raw = $shouldUrlDecode ? urldecode($shouldUrlDecode) : $value;
        if ($shouldUrlDecode) {
            $raw = urldecode($value);
        }

        $value = htmlspecialchars_decode($raw, ENT_QUOTES);

        // ENT_IGNORE so that if utf8 string has some errors, we simply discard invalid code unit sequences
        $style = ENT_QUOTES | ENT_IGNORE;

        // See changes in 5.4: http://nikic.github.com/2012/01/28/htmlspecialchars-improvements-in-PHP-5-4.html
        // Note: at some point we should change ENT_IGNORE to ENT_SUBSTITUTE
        $value = htmlspecialchars($value, $style, 'UTF-8');

        return $value;
    }

    /**
     * Decodes all columns of the given data table
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        $shouldUrlDecode = self::shouldUrlDecodeValue($table);

        foreach ($table->getRows() as $row) {
            $value = $row->getColumn($this->columnToDecode);
            if ($value !== false) {
                $value = self::decodeLabelSafe($value, $shouldUrlDecode);
                $row->setColumn($this->columnToDecode, $value);

                $this->filterSubTable($row);
            }
        }
    }
}
