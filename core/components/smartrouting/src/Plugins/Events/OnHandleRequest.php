<?php
/**
 * @package smartrouting
 * @subpackage plugin
 */

namespace TreehillStudio\SmartRouting\Plugins\Events;

use modResource;
use TreehillStudio\SmartRouting\Plugins\Plugin;

class OnHandleRequest extends Plugin
{
    private $debugInfo = [];

    /**
     * Initialize the plugin event.
     * @return bool
     */
    public function init()
    {
        if ($this->modx->context->get('key') == 'mgr' ||
            (defined('MODX_API_MODE') && MODX_API_MODE) ||
            (defined('MODX_CONNECTOR_INCLUDED') && MODX_CONNECTOR_INCLUDED)
        ) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed|void
     */
    public function process()
    {
        $contexts = $this->modx->cacheManager->get($this->smartrouting->getOption('cacheKey'), $this->smartrouting->getOption('cacheOptions'));
        if (empty($contexts)) {
            $contexts = $this->smartrouting->buildContextArray();
            $this->modx->cacheManager->set($this->smartrouting->getOption('cacheKey'), $contexts, 0, $this->smartrouting->getOption('cacheOptions'));
        }

        if (!empty($contexts)) {
            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($this->smartrouting->getOption('include_www')) {
                $httpHost = str_replace('www.', '', $httpHost);
            }

            // When the web context uses a base_url setting that differs from '/' calling
            // $modx->getOption('base_url', null, MODX_BASE_URL); can't be used, because it
            // uses the context settings from the already initialized context (web, in index.php).
            $baseUrlSetting = $this->modx->getObject('modSystemSetting', 'base_url');
            $modxBaseUrl = ($baseUrlSetting) ? $baseUrlSetting->get('value') : MODX_BASE_URL;

            $requestUrl = $_REQUEST[$this->modx->getOption('request_param_alias', null, 'q')] ?? '';
            $requestBaseUrl = str_replace('//', '/', $modxBaseUrl . $requestUrl);

            // find matching hosts
            $matches = [];
            $matchedContexts = $contexts['_hosts'][$httpHost] ?? '';
            foreach ((array)$matchedContexts as $matchedContext) {
                // add only contexts that contain a not empty base_url context setting
                if (!empty($contexts[$matchedContext]['base_url'])) {
                    $strpos = strpos(rtrim($requestBaseUrl, '/') . '/', $contexts[$matchedContext]['base_url']);
                    if ($strpos === 0) {
                        // the longest (and first) base_url context setting will win the matches
                        $matches[strlen($contexts[$matchedContext]['base_url'])] = $matchedContext;
                        $requestBaseUrl = (rtrim($requestBaseUrl, '/') . '/' == $contexts[$matchedContext]['base_url']) ? rtrim($requestBaseUrl, '/') . '/' : $requestBaseUrl;
                    }
                }
            }

            $smartroutingDebug = $this->smartrouting->getBooleanOption('smartrouting-debug', $_REQUEST, false);

            // modify request for the matched context
            if (!empty($matches)) {
                $cSettings = $contexts[$matches[max(array_keys($matches))]];
                $cKey = $matches[max(array_keys($matches))];

                // if site_status is disabled, use the context of the site_unavailable_page, but not when the user has the view_offline permission
                $siteStatus = $this->modx->getOption('site_status', $cSettings, $this->modx->getOption('site_status'));
                if (!$siteStatus && !$this->modx->hasPermission('view_offline')) {
                    $siteUnavailablePage = $this->modx->getOption('site_unavailable_page', $cSettings, $this->modx->getOption('site_unavailable_page'));
                    if ($siteUnavailablePage) {
                        /** @var modResource $siteUnavailableResource */
                        $siteUnavailableResource = $this->modx->getObject('modResource', $siteUnavailablePage);
                        if ($siteUnavailableResource) {
                            $cKey = $siteUnavailableResource->get('context_key');
                            $cSettings = $contexts[$cKey];
                        }
                    }
                }

                // do we need to switch the context?
                if ($this->modx->context->get('key') != $cKey) {
                    $this->switchContext($cKey);
                }

                // remove base_url from request query
                if (isset($cSettings['base_url']) && $cSettings['base_url'] != $modxBaseUrl) {
                    $newRequestUrl = str_replace($cSettings['base_url'], '', $requestBaseUrl);
                    $_REQUEST[$this->modx->getOption('request_param_alias', null, 'q')] = $newRequestUrl;
                }
            } elseif (!$smartroutingDebug || $this->smartrouting->getOption('allow_debug_info')) {
                // if no match found
                if ($this->smartrouting->getOption('show_no_match_error')) {
                    $this->modx->sendErrorPage();
                } else {
                    $this->modx->switchContext($this->smartrouting->getOption('default_context'));
                }
            }

            // output debug info
            if ($smartroutingDebug && $this->smartrouting->getOption('allow_debug_info')) {
                $this->debug('MODX context map', print_r($contexts, true));
                $this->debug('Requested URL', $_REQUEST[$this->modx->getOption('request_param_alias', null, 'q')]);
                $this->debug('Requested URL with base_url', $requestBaseUrl);
                $this->debug('Matched context(s) (Array key defines match quality)', print_r($matches, true));
                if ((!empty($matches)) && !$siteStatus) {
                    $this->debug('Site Status', 'false');
                    if ($siteUnavailablePage) {
                        $this->debug('Site Unavailable Page', $siteUnavailablePage . ' (' . $cKey . ')');
                    }
                }
                $this->debug('Request will go to context', (!empty($matches)) ? $cKey : '');
                $this->debug('Modified request URL', $newRequestUrl ?? '');
                $this->debug('The used cultureKey', $this->modx->cultureKey);
                @session_write_close();
                die('<pre>' . implode("\n\n\n", $this->debugInfo) . '</pre>');
            }
        }
    }

    /**
     * @param $section
     * @param $message
     * @return void
     */
    private function debug($section, $message)
    {
        $this->debugInfo[] = "## $section: \n\n$message";
    }

    /**
     * Switch the context, set the cultureKey and the locale
     *
     * @param $contextKey
     * @return void
     */
    private function switchContext($contextKey)
    {
        $this->modx->switchContext($contextKey);

        // get culture key from REQUEST, SESSION or system setting
        $cultureKey = $this->modx->getOption('cultureKey', null, 'en');
        $cultureKey = $this->modx->getOption('cultureKey', $_SESSION, $cultureKey, true);
        $cultureKey = $this->modx->getOption('cultureKey', $_REQUEST, $cultureKey, true);
        $cultureKey = $this->modx->getOption('cultureKey', $_COOKIE, $cultureKey, true);
        $this->modx->cultureKey = $cultureKey;
        $this->modx->setOption('cultureKey', $cultureKey);

        // set locale since $this->modx->_initCulture is called before OnHandleRequest
        if ($this->modx->getOption('setlocale', null, true)) {
            $locale = setlocale(LC_ALL, '0');
            setlocale(LC_ALL, $this->modx->getOption('locale', null, $locale, true));
        }
    }
}
