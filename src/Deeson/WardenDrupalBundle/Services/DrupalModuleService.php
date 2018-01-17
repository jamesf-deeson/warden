<?php

namespace Deeson\WardenDrupalBundle\Services;

use Deeson\WardenDrupalBundle\Document\ModuleDocument;
use Deeson\WardenBundle\Document\SiteDocument;
use Deeson\WardenBundle\Managers\SiteManager;
use Deeson\WardenDrupalBundle\Document\SiteModuleDocument;
use Deeson\WardenDrupalBundle\Managers\ModuleManager;
use Deeson\WardenDrupalBundle\Managers\SiteModuleManager;
use Monolog\Logger;

class DrupalModuleService {

  /**
   * @var ModuleManager
   */
  protected $moduleManager;

  /**
   * @var Logger
   */
  protected $logger;

  /**
   * @var SiteManager
   */
  protected $siteManager;

  /**
   * @var SiteModuleManager
   */
  protected $siteModuleManager;

  /**
   * @param ModuleManager $moduleManager
   * @param SiteManager $siteManager
   * @param SiteModuleManager $siteModuleManager
   * @param Logger $logger
   */
  public function __construct(ModuleManager $moduleManager, SiteManager $siteManager, SiteModuleManager $siteModuleManager, Logger $logger) {
    $this->moduleManager = $moduleManager;
    $this->siteManager = $siteManager;
    $this->siteModuleManager = $siteModuleManager;
    $this->logger = $logger;
  }

  /**
   * Event: warden.cron
   *
   * Fired when cron is run to update the list of sites within each module.
   */
  public function onWardenCron() {
    $this->rebuildAllModuleSites();
  }

  /**
   * Remove all sites from each module.
   */
  public function rebuildAllModuleSites() {
    $this->removeAllModuleSites();
    $this->updateAllModuleSites();
    $this->removeUnusedModules();
  }

  /**
   * Removes all the sites referenced by all of the modules.
   */
  protected function removeAllModuleSites() {
    $modules = $this->moduleManager->getAllDocuments();
    foreach ($modules as $module) {
      /** @var ModuleDocument $module */
      $module->setSites(array());
      $this->moduleManager->updateDocument();
    }
  }

  /**
   * Updates each of the modules with their associated sites.
   */
  protected function updateAllModuleSites() {
    $sites = $this->siteManager->getAllDocuments();
    foreach ($sites as $site) {
      /** @var SiteDocument $site */
      print 'Updating site modules: ' . $site->getId() . ' - ' . $site->getUrl() . "\n";
      /** @var SiteModuleDocument $siteModule */
      $siteModule = $this->siteModuleManager->findBySiteId($site->getId());
      if (empty($siteModule)) {
        continue;
        /*$siteModule = $this->siteModuleManager->makeNewItem();
        $siteModule->setSiteId($site->getId());*/
      }
      $siteModule->updateModules($this->moduleManager, $site);
      $this->siteModuleManager->saveDocument($siteModule);
    }
  }

  /**
   * Removes modules that have no sites associated to them.
   */
  protected function removeUnusedModules() {
    $modules = $this->moduleManager->getUnusedModules();
    if (empty($modules)) {
      return;
    }

    foreach ($modules as $module) {
      /** @var ModuleDocument $module */
      $this->logger->addInfo('Remove module "' . $module->getName() . '" as it has no sites associated to it.');
      print "Removing module \"" . $module->getName() . "\" as it has no sites associated to it.\n";
      $this->moduleManager->deleteDocument($module->getId());
    }
  }

}
