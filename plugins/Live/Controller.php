<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Live
 */
namespace Piwik\Plugins\Live;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Config;
use Piwik\MetricsFormatter;
use Piwik\Piwik;
use Piwik\Plugins\Goals\API as APIGoals;
use Piwik\Url;
use Piwik\View;
use Piwik\ViewDataTable\Factory;

/**
 * @package Live
 */
class Controller extends \Piwik\Plugin\Controller
{
    const SIMPLE_VISIT_COUNT_WIDGET_LAST_MINUTES_CONFIG_KEY = 'live_widget_visitor_count_last_minutes';

    function index($fetch = false)
    {
        return $this->widget($fetch);
    }

    public function widget($fetch = false)
    {
        $view = new View('@Live/index');
        $view->idSite = $this->idSite;
        $view = $this->setCounters($view);
        $view->liveRefreshAfterMs = (int)Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;
        $view->visitors = $this->getLastVisitsStart($fetchPlease = true);
        $view->liveTokenAuth = Piwik::getCurrentUserTokenAuth();
        return $this->render($view, $fetch);
    }

    public function getSimpleLastVisitCount($fetch = false)
    {
        $lastMinutes = Config::getInstance()->General[self::SIMPLE_VISIT_COUNT_WIDGET_LAST_MINUTES_CONFIG_KEY];

        $lastNData = Request::processRequest('Live.getCounters', array('lastMinutes' => $lastMinutes));

        $view = new View('@Live/getSimpleLastVisitCount');
        $view->lastMinutes = $lastMinutes;
        $view->visitors = MetricsFormatter::getPrettyNumber($lastNData[0]['visitors']);
        $view->visits = MetricsFormatter::getPrettyNumber($lastNData[0]['visits']);
        $view->actions = MetricsFormatter::getPrettyNumber($lastNData[0]['actions']);
        $view->refreshAfterXSecs = Config::getInstance()->General['live_widget_refresh_after_seconds'];
        $view->translations = array(
            'one_visitor' => Piwik::translate('Live_NbVisitor'),
            'visitors'    => Piwik::translate('Live_NbVisitors'),
            'one_visit'   => Piwik::translate('General_OneVisit'),
            'visits'      => Piwik::translate('General_NVisits'),
            'one_action'  => Piwik::translate('General_OneAction'),
            'actions'     => Piwik::translate('VisitsSummary_NbActionsDescription'),
            'one_minute'  => Piwik::translate('General_OneMinute'),
            'minutes'     => Piwik::translate('General_NMinutes')
        );
        return $this->render($view, $fetch);
    }

    public function ajaxTotalVisitors($fetch = false)
    {
        $view = new View('@Live/ajaxTotalVisitors');
        $view = $this->setCounters($view);
        $view->idSite = $this->idSite;
        return $this->render($view, $fetch);
    }

    private function render(View $view, $fetch)
    {
        $rendered = $view->render();
        if ($fetch) {
            return $rendered;
        }
        echo $rendered;
    }

    public function indexVisitorLog()
    {
        $view = new View('@Live/indexVisitorLog.twig');
        $view->filterEcommerce = Common::getRequestVar('filterEcommerce', 0, 'int');
        $view->visitorLog = $this->getLastVisitsDetails($fetch = true);
        echo $view->render();
    }

    public function getLastVisitsDetails($fetch = false)
    {
        return Factory::renderReport($this->pluginName, __FUNCTION__, $fetch);
    }

    /**
     * @deprecated
     */
    public function getVisitorLog($fetch = false)
    {
        return $this->getLastVisitsDetails($fetch);
    }

    public function getLastVisitsStart($fetch = false)
    {
        // hack, ensure we load today's visits by default
        $_GET['date'] = 'today';
        $_GET['period'] = 'day';
        $view = new View('@Live/getLastVisitsStart');
        $view->idSite = $this->idSite;

        $api = new Request("method=Live.getLastVisitsDetails&idSite={$this->idSite}&filter_limit=10&format=php&serialize=0&disable_generic_filters=1");
        $visitors = $api->process();
        $view->visitors = $visitors;

        return $this->render($view, $fetch);
    }

    private function setCounters($view)
    {
        $segment = Request::getRawSegmentFromRequest();
        $last30min = API::getInstance()->getCounters($this->idSite, $lastMinutes = 30, $segment);
        $last30min = $last30min[0];
        $today = API::getInstance()->getCounters($this->idSite, $lastMinutes = 24 * 60, $segment);
        $today = $today[0];
        $view->visitorsCountHalfHour = $last30min['visits'];
        $view->visitorsCountToday = $today['visits'];
        $view->pisHalfhour = $last30min['actions'];
        $view->pisToday = $today['actions'];
        return $view;
    }

    /**
     * Echo's HTML for visitor profile popup.
     */
    public function getVisitorProfilePopup()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');

        $view = new View('@Live/getVisitorProfilePopup.twig');
        $view->idSite = $idSite;
        $view->goals = APIGoals::getInstance()->getGoals($idSite);
        $view->visitorData = Request::processRequest('Live.getVisitorProfile', array('checkForLatLong' => true));
        $view->exportLink = $this->getVisitorProfileExportLink();

        if (Common::getRequestVar('showMap', 1) == 1
            && $view->visitorData['hasLatLong']
            && \Piwik\Plugin\Manager::getInstance()->isPluginLoaded('UserCountryMap')
        ) {
            $view->userCountryMapUrl = $this->getUserCountryMapUrlForVisitorProfile();
        }

        $this->setWidgetizedVisitorProfileUrl($view);

        echo $view->render();
    }

    public function getSingleVisitSummary()
    {
        $view = new View('@Live/getSingleVisitSummary.twig');
        $visits = Request::processRequest('Live.getLastVisitsDetails', array(
                                                                            'segment' => 'visitId==' . Common::getRequestVar('visitId'),
                                                                            'period'  => false,
                                                                            'date'    => false
                                                                       ));
        $view->visitData = $visits->getFirstRow()->getColumns();
        $view->visitReferralSummary = API::getReferrerSummaryForVisit($visits->getFirstRow());
        $view->showLocation = true;
        $this->setWidgetizedVisitorProfileUrl($view);
        $view->exportLink = $this->getVisitorProfileExportLink();
        echo $view->render();
    }

    public function getVisitList()
    {
        $nextVisits = Request::processRequest('Live.getLastVisitsDetails', array(
                                                                                'segment'                 => self::getSegmentWithVisitorId(),
                                                                                'filter_limit'            => API::VISITOR_PROFILE_MAX_VISITS_TO_SHOW,
                                                                                'disable_generic_filters' => 1,
                                                                                'period'                  => false,
                                                                                'date'                    => false
                                                                           ));

        if (empty($nextVisits)) {
            return;
        }

        $view = new View('@Live/getVisitList.twig');
        $view->idSite = Common::getRequestVar('idSite', null, 'int');
        $view->startCounter = Common::getRequestVar('filter_offset', 0, 'int') + 1;
        $view->visits = $nextVisits;
        echo $view->render();
    }

    private function getVisitorProfileExportLink()
    {
        return Url::getCurrentQueryStringWithParametersModified(array(
                                                                     'module'   => 'API',
                                                                     'action'   => 'index',
                                                                     'method'   => 'Live.getVisitorProfile',
                                                                     'format'   => 'XML',
                                                                     'expanded' => 1
                                                                ));
    }

    private function setWidgetizedVisitorProfileUrl($view)
    {
        if (\Piwik\Plugin\Manager::getInstance()->isPluginLoaded('Widgetize')) {
            $view->widgetizedLink = Url::getCurrentQueryStringWithParametersModified(array(
                                                                                          'module'            => 'Widgetize',
                                                                                          'action'            => 'iframe',
                                                                                          'moduleToWidgetize' => 'Live',
                                                                                          'actionToWidgetize' => 'getVisitorProfilePopup'
                                                                                     ));
        }
    }

    private function getUserCountryMapUrlForVisitorProfile()
    {
        $params = array(
            'module'             => 'UserCountryMap',
            'action'             => 'realtimeMap',
            'segment'            => self::getSegmentWithVisitorId(),
            'visitorId'          => false,
            'changeVisitAlpha'   => 0,
            'removeOldVisits'    => 0,
            'realtimeWindow'     => 'false',
            'showFooterMessage'  => 0,
            'showDateTime'       => 0,
            'doNotRefreshVisits' => 1
        );
        return Url::getCurrentQueryStringWithParametersModified($params);
    }

    private static function getSegmentWithVisitorId()
    {
        static $cached = null;
        if ($cached === null) {
            $segment = Request::getRawSegmentFromRequest();
            if (!empty($segment)) {
                $segment = urldecode($segment) . ';';
            }

            $idVisitor = Common::getRequestVar('visitorId', false);
            if ($idVisitor === false) {
                $idVisitor = Request::processRequest('Live.getMostRecentVisitorId');
            }

            $cached = $segment . 'visitorId==' . $idVisitor;
        }
        return $cached;
    }
}