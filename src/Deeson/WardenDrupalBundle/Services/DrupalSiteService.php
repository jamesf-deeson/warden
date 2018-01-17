<?php

namespace Deeson\WardenDrupalBundle\Services;

use Deeson\WardenBundle\Document\SiteDocument;
use Deeson\WardenBundle\Event\CronEvent;
use Deeson\WardenDrupalBundle\Document\ModuleDocument;
use Deeson\WardenBundle\Event\DashboardUpdateEvent;
use Deeson\WardenBundle\Event\SiteDeleteEvent;
use Deeson\WardenBundle\Event\SiteRefreshEvent;
use Deeson\WardenBundle\Event\SiteShowEvent;
use Deeson\WardenBundle\Event\SiteUpdateEvent;
use Deeson\WardenBundle\Event\WardenEvents;
use Deeson\WardenBundle\Exception\WardenRequestException;
use Deeson\WardenBundle\Services\SiteConnectionService;
use Deeson\WardenDrupalBundle\Managers\ModuleManager;
use Deeson\WardenDrupalBundle\Managers\SiteDrupalManager;
use Deeson\WardenDrupalBundle\Managers\SiteModuleManager;
use Deeson\WardenDrupalBundle\Document\SiteModuleDocument;
use Deeson\WardenDrupalBundle\Document\SiteDrupalDocument;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DrupalSiteService {

  /**
   * @var ModuleManager
   */
  protected $moduleManager;

  /**
   * @var SiteDrupalManager
   */
  protected $siteDrupalManager;

  /**
   * @var SiteModuleManager
   */
  protected $siteModuleManager;

  /**
   * @var SiteConnectionService
   */
  protected $siteConnectionService;

  /**
   * @var Logger
   */
  protected $logger;

  /**
   * @var EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * @param ModuleManager $moduleManager
   * @param SiteDrupalManager $siteDrupalManager
   * @param SiteModuleManager $siteModuleManager
   * @param SiteConnectionService $siteConnectionService
   * @param Logger $logger
   * @param EventDispatcherInterface $dispatcher
   */
  public function __construct(ModuleManager $moduleManager, SiteDrupalManager $siteDrupalManager, SiteModuleManager $siteModuleManager, SiteConnectionService $siteConnectionService, Logger $logger, EventDispatcherInterface $dispatcher) {
    $this->moduleManager = $moduleManager;
    $this->siteDrupalManager = $siteDrupalManager;
    $this->siteModuleManager = $siteModuleManager;
    $this->siteConnectionService = $siteConnectionService;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
  }

  /**
   * Get the site status URL.
   *
   * @param SiteDocument $site
   *   The site being updated
   *
   * @return mixed
   */
  protected function getSiteRequestUrl(SiteDocument $site) {
    return $site->getUrl() . '/admin/reports/warden';
  }

  /**
   * Determine if the given site data refers to a Drupal site.
   *
   * @param SiteDocument $site
   * @return bool
   */
  protected function isDrupalSite(SiteDocument $site) {
    // @TODO how to determine?
    $siteDrupal = $this->siteDrupalManager->findBySiteId($site->getId());
    return !empty($siteDrupal);
  }

  /**
   * Processes the data that has come back from the request.
   *
   * @param SiteDocument $site
   *   The site being updated
   * @param $data
   *   New data about the site.
   */
  public function processUpdate(SiteDocument $site, $data) {
    $moduleData = json_decode(json_encode($data->contrib), TRUE);
    if (!is_array($moduleData)) {
      $moduleData = array();
    }
    $this->moduleManager->addModules($moduleData);
    $site->setName($data->site_name);

    /** @var SiteDrupalDocument $siteDrupal */
    $siteDrupal = $this->siteDrupalManager->findBySiteId($site->getId());
    if (empty($siteDrupal)) {
      $siteDrupal = $this->siteDrupalManager->makeNewItem();
      // @todo move this to the makeNewItem method.
      $siteDrupal->setSiteId($site->getId());
      $this->siteDrupalManager->saveDocument($siteDrupal);
    }
    $siteDrupal->setCoreVersion($data->core->drupal->version);
    $this->siteDrupalManager->updateDocument();

    /** @var SiteModuleDocument $siteModule */
    $siteModule = $this->siteModuleManager->findBySiteId($site->getId());
    if (empty($siteModule)) {
      $siteModule = $this->siteModuleManager->makeNewItem();
      // @todo move this to the makeNewItem method.
      $siteModule->setSiteId($site->getId());
      $this->siteModuleManager->saveDocument($siteModule);
    }
    $siteModule->setModules($moduleData, TRUE);
    $this->siteModuleManager->updateDocument();

    $event = new DashboardUpdateEvent($site);
    $this->dispatcher->dispatch(WardenEvents::WARDEN_DASHBOARD_UPDATE, $event);
  }

  /**
   * Event: warden.cron
   *
   * Updates all the sites with their latest data into Warden.
   *
   * @param CronEvent $event
   */
  public function onWardenCron(CronEvent $event) {
    $sites = $event->getSites();
    foreach ($sites as $site) {
      /** @var SiteDocument $site */
      print 'Updating site: ' . $site->getId() . ' - ' . $site->getUrl() . "\n";
      $this->logger->addInfo('Updating site: ' . $site->getId() . ' - ' . $site->getUrl());

      try {
        $event = new SiteRefreshEvent($site);
        $this->dispatcher->dispatch(WardenEvents::WARDEN_SITE_REFRESH, $event);
      }
      catch (\Exception $e) {
        print 'General Error - Unable to retrieve data from the site: ' . $e->getMessage() . "\n";
        $this->logger->addError('General Error - Unable to retrieve data from the site: ' . $e->getMessage());
      }
    }
  }

  /**
   * Event: warden.site.refresh
   *
   * Fires when the Warden administrator requests for a site to be refreshed.
   *
   * @param SiteRefreshEvent $event
   *   Event detailing the site requesting a refresh.
   */
  public function onWardenSiteRefresh(SiteRefreshEvent $event) {
    $site = $event->getSite();
    if (!$this->isDrupalSite($site)) {
      return;
    }

    try {
      $this->logger->addInfo('This is the start of a Drupal Site Refresh Event: ' . $site->getUrl());
      $this->siteConnectionService->post($this->getSiteRequestUrl($site), $site);
      $event->addMessage('A Drupal site has been updated: ' . $site->getUrl());
      $this->logger->addInfo('This is the end of a Drupal Site Refresh Event: ' . $site->getUrl());
    }
    catch (WardenRequestException $e) {
      $event->addMessage($e->getMessage(), SiteRefreshEvent::WARNING);
    }
  }

  /**
   * Event: warden.site.update
   *
   * Fires when a site is updated. This will detect if the site is a Drupal site
   * and update the Drupal data accordingly.
   *
   * @param SiteUpdateEvent $event
   */
  public function onWardenSiteUpdate(SiteUpdateEvent $event) {
    if (!$this->isDrupalSite($event->getSite())) {
      return;
    }

    $this->logger->addInfo('This is the start of a Drupal Site Update Event: ' . $event->getSite()->getUrl());
    $this->processUpdate($event->getSite(), $event->getData());
    $this->logger->addInfo('This is the end of a Drupal Site Update Event: ' . $event->getSite()->getUrl());
  }

  /**
   * Event: warden.site.show
   *
   * Fires when a site page is viewed.
   *
   * @param SiteShowEvent $event
   */
  public function onWardenSiteShow(SiteShowEvent $event) {
    $site = $event->getSite();
    if (!$this->isDrupalSite($site)) {
      return;
    }

    $this->logger->addInfo('This is the start of a Drupal show site event: ' . $site->getUrl());

    // Check if Drupal core requires a security update.
    /** @var SiteDrupalDocument $siteDrupal */
    $siteDrupal = $this->siteDrupalManager->findBySiteId($site->getId());
    if ($siteDrupal->hasOlderCoreVersion() && $siteDrupal->getIsSecurityCoreVersion()) {
      $event->addTemplate('DeesonWardenDrupalBundle:Sites:securityUpdateRequired.html.twig');
    }

    $event->addTemplate('DeesonWardenDrupalBundle:Sites:siteDetails.html.twig');
    $event->addParam('coreVersion', $siteDrupal->getCoreVersion());
    $event->addParam('latestCoreVersion', $siteDrupal->getLatestCoreVersion());

    // Check if there are any Drupal modules that require updates.
    /** @var SiteModuleDocument $siteModule */
    $siteModule = $this->siteModuleManager->findBySiteId($site->getId());
    if (!empty($siteModule)) {
      $modulesRequiringUpdates = $siteModule->getModulesRequiringUpdates();
      $event->addParam('siteModule', $siteModule);
      if (!empty($modulesRequiringUpdates)) {
        $event->addTabTemplate('modules', 'DeesonWardenDrupalBundle:Sites:moduleUpdates.html.twig');
        $event->addParam('modulesRequiringUpdates', $modulesRequiringUpdates);
      }

      // List the Drupal modules that used on the site.
      $event->addTabTemplate('modules', 'DeesonWardenDrupalBundle:Sites:modules.html.twig');
      $event->addParam('modules', $siteModule->getModules());
    }

    $this->logger->addInfo('This is the end of a Drupal show site event: ' . $site->getUrl());
  }

  /**
   * Event: warden.site.delete
   *
   * Fires when a site is deleted.
   *
   * @param SiteDeleteEvent $event
   */
  public function onWardenSiteDelete(SiteDeleteEvent $event) {
    $site = $event->getSite();
    if (!$this->isDrupalSite($site)) {
      return;
    }

    // @todo do we need to do this anymore as the module data gets rebuilt?

    /** @var SiteModuleDocument $siteModule */
    $siteModule = $this->siteModuleManager->findBySiteId($site->getId());
    foreach ($siteModule->getModules() as $siteModule) {
      /** @var ModuleDocument $module */
      $module = $this->moduleManager->findByProjectName($siteModule['name']);
      if (empty($module)) {
        print('Error getting module [' . $siteModule['name'] . ']');
        continue;
      }
      $module->removeSite($site->getId());
      $this->moduleManager->updateDocument();
    }
  }

  /**
   * Get the current micro time.
   *
   * @return float
   */
  protected function getMicroTimeFloat() {
    list($microSeconds, $seconds) = explode(' ', microtime());
    return ((float) $microSeconds + (float) $seconds);
  }
}
