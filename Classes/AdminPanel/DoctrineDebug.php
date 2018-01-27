<?php
declare(strict_types=1);

namespace Psychomieze\Unicorns\AdminPanel;

use Doctrine\DBAL\Logging\DebugStack;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPoolGetConnectionByNameHookInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\View\AdminPanelViewHookInterface;

class DoctrineDebug
    implements AdminPanelViewHookInterface, ConnectionPoolGetConnectionByNameHookInterface,
               SingletonInterface
{
    protected $debugStack;

    public function __construct()
    {
        $this->debugStack = new DebugStack();
    }

    public function modifyConnection(Connection $connection)
    {
        if ($this->isAdminModuleOpen('doctrineDebug')) {
            // only add logger if admin panel doctrine debug is opened
            $connection->getConfiguration()->setSQLLogger($this->debugStack);
        }
    }

    /**
     * Extend the adminPanel
     *
     * @param string $moduleContent Content of the admin panel
     * @param \TYPO3\CMS\Frontend\View\AdminPanelView $obj The adminPanel object
     * @return string Returns content of admin panel
     */
    public function extendAdminPanel($moduleContent, \TYPO3\CMS\Frontend\View\AdminPanelView $obj)
    {
        $content = '';
        $this->getLanguageService()->includeLLFile('EXT:unicorns/Resources/Language/locallang.xlf');
        if ($this->isAdminModuleOpen('doctrineDebug')) {
            $groupedQueries = $this->getQueries();

            $view = new StandaloneView();
            $view->setTemplatePathAndFilename(
                'typo3conf/ext/unicorns/Resources/Templates/List.html'
            );
            $view->assign('queries', $groupedQueries);
            $content = $view->render();
        }

        return $this->getModule('doctrineDebug', $content);

    }

    /**
     * @return array
     */
    protected function getQueries(): array
    {
        $queries = $this->debugStack->queries;
        $groupedQueries = [];
        foreach ($queries as $query) {
            $identifier = sha1($query['sql']);
            $time = $groupedQueries[$identifier]['time'] ?? 0;
            $count = $groupedQueries[$identifier]['count'] ?? 0;
            $groupedQueries[$identifier] = [
                'sql' => $query['sql'],
                'time' => $time + $query['executionMS'],
                'count' => $count + 1,
            ];
        }
        uasort(
            $groupedQueries,
            function ($a, $b) {
                return $b['count'] <=> $a['count'];
            }
        );
        return $groupedQueries;
    }

    // everything below this point is copy paste from AdminPanelView

    /**
     * @param string $key
     * @param string $content
     *
     * @return string
     */
    protected function getModule($key, $content)
    {
        $output = [];

        if ($this->getBackendUser()->uc['TSFE_adminConfig']['display_top'] && $this->isAdminModuleEnabled($key)) {
            $output[] = '<div class="typo3-adminPanel-section typo3-adminPanel-section-' .
                        ($this->isAdminModuleOpen($key) ? 'open' : 'closed') .
                        '">';
            $output[] = '  <div class="typo3-adminPanel-section-title">';
            $output[] = '    ' . $this->linkSectionHeader($key, $this->extGetLL($key));
            $output[] = '  </div>';
            if ($this->isAdminModuleOpen($key)) {
                $output[] = '<div class="typo3-adminPanel-section-body">';
                $output[] = '  ' . $content;
                $output[] = '</div>';
            }
            $output[] = '</div>';
        }

        return implode('', $output);
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Backend\FrontendBackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Wraps a string in a link which will open/close a certain part of the Admin Panel
     *
     * @param string $sectionSuffix The code for the display_ label/key
     * @param string $sectionTitle Title (in HTML-format)
     * @param string $className The classname for the <a> tag
     * @return string $className Linked input string
     * @see extGetHead()
     */
    public function linkSectionHeader($sectionSuffix, $sectionTitle, $className = '')
    {
        $onclick = 'document.TSFE_ADMIN_PANEL_FORM[' .
                   GeneralUtility::quoteJSvalue('TSFE_ADMIN_PANEL[display_' . $sectionSuffix . ']') .
                   '].value=' .
                   ($this->getBackendUser()->uc['TSFE_adminConfig']['display_' . $sectionSuffix] ? '0' : '1') .
                   ';document.TSFE_ADMIN_PANEL_FORM.submit();return false;';

        $output = [];
        $output[] = '<span class="typo3-adminPanel-section-title-identifier"></span>';
        $output[] = '<a href="javascript:void(0)" onclick="' . htmlspecialchars($onclick) . '">';
        $output[] = '  ' . $sectionTitle;
        $output[] = '</a>';
        $output[] = '<input type="hidden" name="TSFE_ADMIN_PANEL[display_' .
                    $sectionSuffix .
                    ']" value="' .
                    $this->isAdminModuleOpen($sectionSuffix) .
                    '" />';

        return implode('', $output);
    }

    /**
     * Returns TRUE if admin panel module is open
     *
     * @param string $key Module key
     * @return bool TRUE, if the admin panel is open for the specified admin panel module key.
     */
    public function isAdminModuleOpen($key)
    {
        return $this->getBackendUser()->uc['TSFE_adminConfig']['display_top'] &&
               $this->getBackendUser()->uc['TSFE_adminConfig']['display_' . $key];
    }

    /**
     * Checks if an Admin Panel section ("module") is available for the user. If so, TRUE is returned.
     *
     * @param string $key The module key, eg. "edit", "preview", "info" etc.
     * @return bool
     */
    public function isAdminModuleEnabled($key)
    {
        $result = false;
        // Returns TRUE if the module checked is "preview" and the forcePreview flag is set.
        if ($key === 'preview' && $this->ext_forcePreview) {
            $result = true;
        } elseif (!empty($this->getBackendUser()->extAdminConfig['enable.']['all'])) {
            $result = true;
        } elseif (!empty($this->getBackendUser()->extAdminConfig['enable.'][$key])) {
            $result = true;
        }
        return $result;
    }

    /**
     * Translate given key
     *
     * @param string $key Key for a label in the $LOCAL_LANG array of "sysext/lang/Resources/Private/Language/locallang_tsfe.xlf
     * @param bool $convertWithHtmlspecialchars If TRUE the language-label will be sent through htmlspecialchars
     * @return string The value for the $key
     */
    protected function extGetLL($key, $convertWithHtmlspecialchars = true)
    {
        $labelStr = $this->getLanguageService()->getLL($key);
        if ($convertWithHtmlspecialchars) {
            $labelStr = htmlspecialchars($labelStr);
        }
        return $labelStr;
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}