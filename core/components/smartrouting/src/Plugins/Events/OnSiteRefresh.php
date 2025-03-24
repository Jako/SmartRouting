<?php
/**
 * @package smartrouting
 * @subpackage plugin
 */

namespace TreehillStudio\SmartRouting\Plugins\Events;

use TreehillStudio\SmartRouting\Plugins\Plugin;
use xPDO;

class OnSiteRefresh extends Plugin
{
    public function process()
    {
        $contexts = $this->smartrouting->buildContextArray();
        $this->modx->cacheManager->set($this->smartrouting->getOption('cacheKey'), $contexts, 0, $this->smartrouting->getOption('cacheOptions'));
        $this->modx->log(xPDO::LOG_LEVEL_INFO, $this->modx->lexicon('smartrouting.refresh_cache', [
            'packagename' => $this->smartrouting->packageName
        ]));
    }
}
