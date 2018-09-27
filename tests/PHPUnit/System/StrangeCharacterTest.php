<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Tests\System;

use Piwik\Tests\Fixtures\OneVisitorTwoVisits;
use Piwik\Tests\Fixtures\StrangeCharactersInTrackingData;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

class StrangeCharacterTest extends SystemTestCase
{
    /**
     * @var StrangeCharactersInTrackingData
     */
    public static $fixture;

    /**
     * @dataProvider getApiForTesting
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        $idSite = self::$fixture->idSite;
        $dateTime = self::$fixture->dateTime;

        return [
            array('all', array('idSite' => $idSite,
                'date' => $dateTime,
                'periods' => ['week'],
                'otherRequestParameters' => array(
                    'hideColumns' => OneVisitorTwoVisits::getValueForHideColumns(),
                ),
            )),

            // processed report
            array('API.getProcessedReport', array(
                'idSite'                 => $idSite, 'date' => $dateTime,
                'periods'                => 'week', 'apiModule' => 'Actions',
                'apiAction'              => 'getPageUrls',
                'testSuffix' => 'pageUrlsProcessed',
            )),
            array('API.getProcessedReport', array(
                'idSite'                 => $idSite, 'date' => $dateTime,
                'periods'                => 'week', 'apiModule' => 'Actions',
                'apiAction'              => 'getPageTitles',
                'testSuffix' => 'pageTitlesProcessed',
            )),

            // flat
            array(
                'Referrers.getWebsites',
                array(
                    'testSuffix' => 'flat',
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'periods' => ['week'],
                    'otherRequestParameters' => array(
                        'flat'     => '1',
                        'expanded' => '0'
                    )
                ),
            ),
            array(
                'Actions.getPageUrls',
                array(
                    'testSuffix' => 'flat',
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'period'                 => 'week',
                    'otherRequestParameters' => array(
                        'flat'     => '1',
                        'expanded' => '0'
                    )
                ),
            ),
            array(
                'Actions.getPageUrls',
                array(
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'period'                 => 'week',
                    'testSuffix'             => 'flat_withAggregate',
                    'otherRequestParameters' => array(
                        'flat'                   => '1',
                        'include_aggregate_rows' => '1',
                        'expanded'               => '0'
                    )
                ),
            ),
            array('CustomVariables.getCustomVariables', array(
                'idSite'                 => $idSite,
                'date'                   => $dateTime,
                'periods' => ['week'],
                'testSuffix' => 'flat',
                'otherRequestParameters' => array(
                    'date'                   => '2012-01-25,2012-03-04',
                    'flat'                   => '1',
                    'include_aggregate_rows' => '1',
                    'expanded'               => '0'
                )
            )),

            // row evolution
            array('API.getRowEvolution',
                array(
                    'testSuffix'             => 'rowEvolution_referrers',
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'otherRequestParameters' => array(
                        'date'      => '2012-01-25,2012-03-04',
                        'period'    => 'day',
                        'apiModule' => 'Referrers',
                        'apiAction' => 'getWebsites',
                        'label'     => urlencode('motherrussia.org'),
                        'expanded'  => 0,
                    ),
                ),
            ),
            array('API.getRowEvolution',
                array(
                    'testSuffix'             => 'rowEvolution_keywords',
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'otherRequestParameters' => array(
                        'date'      => '2012-01-25,2012-03-04',
                        'period'    => 'day',
                        'apiModule' => 'Referrers',
                        'apiAction' => 'getKeywords',
                        'label'     => urlencode('keyword ' . StrangeCharactersInTrackingData::ODD_CHARACTERS),
                        'expanded'  => 0,
                    ),
                ),
            ),
            array('API.getRowEvolution',
                array(
                    'testSuffix'             => 'rowEvolution_pageUrls',
                    'idSite'                 => $idSite,
                    'date'                   => $dateTime,
                    'otherRequestParameters' => array(
                        'date'      => '2012-01-25,2012-03-04',
                        'period'    => 'day',
                        'apiModule' => 'Actions',
                        'apiAction' => 'getPageUrls',
                        'label'     => urlencode('sub' . StrangeCharactersInTrackingData::ODD_URL_CHARACTER_STRING . 'dir').'>'.urlencode('/page.html'),
                        'expanded'  => 0,
                    ),
                ),
            ),
        ];
    }

    // TODO: test w/ forced urldecode()

    public function testSegmentUrl()
    {
        // TODO
    }
}

StrangeCharacterTest::$fixture = new StrangeCharactersInTrackingData();
