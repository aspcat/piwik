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

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Visualization;
use Piwik\View;

/**
 * A special DataTable visualization for the Live.getLastVisitsDetails API method.
 */
class VisitorLog extends Visualization
{
    const ID = 'Piwik\Plugins\Live\VisitorLog';
    const TEMPLATE_FILE = "@Live/_dataTableViz_visitorLog.twig";

    public function beforeLoadDataTable()
    {
        $this->requestConfig->addPropertiesThatShouldBeAvailableClientSide(array(
            'filter_limit',
            'filter_offset',
            'filter_sort_column',
            'filter_sort_order',
        ));

        $this->requestConfig->filter_sort_column = 'idVisit';
        $this->requestConfig->filter_sort_order  = 'asc';
        $this->requestConfig->filter_limit       = 20;
        $this->requestConfig->disable_generic_filters = true;
    }

    /**
     * Configure visualization.
     */
    public function beforeRender()
    {
        $this->config->datatable_js_type = 'VisitorLog';
        $this->config->enable_sort       = false;
        $this->config->show_search       = false;
        $this->config->show_exclude_low_population = false;
        $this->config->show_offset_information     = false;
        $this->config->show_all_views_icons        = false;
        $this->config->show_table_all_columns      = false;
        $this->config->show_export_as_rss_feed     = false;

        $this->config->documentation = Piwik::translate('Live_VisitorLogDocumentation', array('<br />', '<br />'));
        $this->config->custom_parameters = array(
            // set a very high row count so that the next link in the footer of the data table is always shown
            'totalRows'         => 10000000,

            'filterEcommerce'   => Common::getRequestVar('filterEcommerce', 0, 'int'),
            'pageUrlNotDefined' => Piwik::translate('General_NotDefined', Piwik::translate('Actions_ColumnPageURL'))
        );

        $this->config->footer_icons = array(
            array(
                'class'   => 'tableAllColumnsSwitch',
                'buttons' => array(
                    array(
                        'id'    => '\Piwik\Plugins\Live\VisitorLog',
                        'title' => Piwik::translate('Live_LinkVisitorLog'),
                        'icon'  => 'plugins/Zeitgeist/images/table.png'
                    )
                )
            )
        );
    }
}