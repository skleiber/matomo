/*!
 * Matomo - free/libre analytics platform
 *
 * transitions screenshot tests
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("Transitions", function () {
    this.timeout(0);

    var generalParams = 'idSite=1&period=year&date=2012-08-09',
        urlBase = 'module=CoreHome&action=index&' + generalParams
        ;

    it('should load the transitions popup correctly for the page titles report', async function() {
        await page.goto("?" + urlBase + "#?" + generalParams + "&category=General_Actions&subcategory=Actions_SubmenuPageTitles");

        await (await page.jQuery('div.dataTable tbody tr:eq(2)')).hover();
        await (await page.jQuery('a.actionTransitions:visible')).hover(); // necessary to get popover to display
        await (await page.jQuery('a.actionTransitions:visible')).click();

        await page.waitForNetworkIdle();

        expect(await page.screenshotSelector('.ui-dialog')).to.matchImage('transitions_popup_titles');
    });

    it('should load the transitions popup correctly for the page urls report', async function() {
        await page.goto("?" + urlBase + "#?" + generalParams + "&category=General_Actions&subcategory=General_Pages&"
                    + "popover=RowAction$3ATransitions$3Aurl$3Ahttp$3A$2F$2Fpiwik.net$2Fdocs$2Fmanage-websites$2F");
        await page.hover('.Transitions_CurveTextRight');

        expect(await page.screenshotSelector('.ui-dialog')).to.matchImage('transitions_popup_urls');
    });
});