<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Tests\Fixtures;

use Piwik\Date;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\Goals\API as GoalsAPI;

class StrangeCharactersInTrackingData extends Fixture
{
    const ODD_URL_CHARACTER_STRING = '&=+@:';
    const ODD_CHARACTERS = '&=+@:?#';

    public $idSite = 1;
    public $dateTime = '2012-02-01 03:04:05';

    public function setUp()
    {
        parent::setUp();

        $this->addSites();
        $this->trackOddData();
    }

    private function addSites()
    {
        if (!self::siteCreated($this->idSite)) {
            Fixture::createWebsite('2012-01-01 00:00:00');
        }

        if (!self::goalExists($idSite = 1, $idGoal = 1)) {
            GoalsAPI::getInstance()->addGoal($this->idSite, 'special char match', 'url', self::ODD_CHARACTERS, 'contains');
        }
    }

    private function trackOddData()
    {
        $t = self::getTracker($this->idSite, $this->dateTime);
        $t->enableBulkTracking();

        // normal pageview
        $t->setUrl('http://strangerdanger.org/sub' . self::ODD_URL_CHARACTER_STRING . 'dir/page.html');
        $t->setUrlReferrer('http://motherrussia.org/needs/you/' . self::ODD_URL_CHARACTER_STRING . '/');
        $t->setCustomVariable(1, 'cv name ' . self::ODD_CHARACTERS, 'cv value ' . self::ODD_CHARACTERS);
        self::assertTrue($t->doTrackPageView('page ' . self::ODD_CHARACTERS . ' title'));

        // outlink
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.1)->getDatetime());
        self::assertTrue($t->doTrackAction('http://nekro.org/sub' . self::ODD_URL_CHARACTER_STRING . 'dir/instructions', 'link'));

        // download
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.2)->getDatetime());
        self::assertTrue($t->doTrackAction('http://nekro.org/sub' . self::ODD_URL_CHARACTER_STRING . 'dir/dose', 'download'));

        // content impression
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.3)->getDatetime());
        self::assertTrue($t->doTrackContentImpression('content ' . self::ODD_CHARACTERS . ' piece', 'content/' . self::ODD_CHARACTERS . '/path/to/file.jpg',
            'http://strangerdanger.org/' . self::ODD_URL_CHARACTER_STRING . '/up'));

        // content interaction
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.4)->getDatetime());
        self::assertTrue($t->doTrackContentInteraction('click', 'content ' . self::ODD_CHARACTERS . ' piece', 'content/' . self::ODD_CHARACTERS . '/path/to/file.jpg',
            'http://strangerdanger.org/' . self::ODD_URL_CHARACTER_STRING . '/down'));

        // pageview w/ product (and keyword referrer)
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(1.5)->getDatetime());
        $t->setUrlReferrer('http://google.com/search?q=' . urlencode('keyword ' . self::ODD_CHARACTERS));
        $t->setEcommerceView($sku = 'SKU VERY ' . self::ODD_CHARACTERS . ' nice indeed', $name = 'PRODUCT ' . self::ODD_CHARACTERS . ' name',
            $category = 'PROD CAT ' . self::ODD_CHARACTERS . ' abc', $price = 666);
        self::assertTrue($t->doTrackPageView('product ' . self::ODD_CHARACTERS . ' page'));

        // ecommerce product update
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(1.6)->getDatetime());
        $t->addEcommerceItem($sku = 'SKU VERY ' . self::ODD_CHARACTERS . ' nice indeed', $name = 'PRODUCT ' . self::ODD_CHARACTERS . ' name',
            $category = 'PROD CAT ' . self::ODD_CHARACTERS . ' abc', $price = 666, $quantity = 2);
        self::assertTrue($t->doTrackEcommerceCartUpdate($grandTotal = 2 * 666));

        // ecommerce order
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(1.7)->getDatetime());
        $t->addEcommerceItem($sku = 'ANOTHER ' . self::ODD_CHARACTERS . ' SKU HERE', $name = 'PRODUCT name BIS ' . self::ODD_CHARACTERS,
            $category = 'PROD CAT ' . self::ODD_CHARACTERS . ' abc', $price = 5, $quantity = 3);
        self::assertTrue($t->doTrackEcommerceOrder('myorder' . self::ODD_CHARACTERS, $grandTotal = 1111.11, $subTotal = 1000,
            $tax = 111, $shipping = 0.11, $discount = 666));

        // event
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(1.8)->getDatetime());
        self::assertTrue($t->doTrackEvent('event cat ' . self::ODD_CHARACTERS, 'action ' . self::ODD_CHARACTERS, 'name ' . self::ODD_CHARACTERS, 150));

        // site search
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(1.9)->getDatetime());
        self::assertTrue($t->doTrackSiteSearch('search ' . self::ODD_CHARACTERS . ' keyword', 'search ' . self::ODD_CHARACTERS . ' category'));

        // normal pageview w/ urlencoded URL
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addDay(1)->getDatetime());
        $t->setUserId('zav' . self::ODD_CHARACTERS);
        $t->setUrl(urlencode('http://strangerdanger.org/sub' . self::ODD_URL_CHARACTER_STRING . 'dir/page.html'));
        $t->setUrlReferrer(urlencode('http://motherrussia.org/needs/you/' . self::ODD_URL_CHARACTER_STRING . '/'));
        self::assertTrue($t->doTrackPageView('another page ' . self::ODD_CHARACTERS . ' title'));

        self::checkBulkTrackingResponse($t->doBulkTrack());
    }
}