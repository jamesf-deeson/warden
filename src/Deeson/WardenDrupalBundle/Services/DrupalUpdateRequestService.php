<?php

namespace Deeson\WardenDrupalBundle\Services;

use Deeson\WardenBundle\Client\RequestHandlerException;
use Deeson\WardenBundle\Client\RequestHandlerInterface;
use Deeson\WardenDrupalBundle\Document\ModuleDocument;
use Deeson\WardenBundle\Document\SiteDocument;
use Deeson\WardenBundle\Managers\SiteManager;
use Deeson\WardenDrupalBundle\Managers\ModuleManager;
use Deeson\WardenDrupalBundle\Managers\SiteModuleManager;
use Deeson\WardenDrupalBundle\Document\SiteModuleDocument;
use Monolog\Logger;

class DrupalUpdateRequestService {

  /**
   * The name of the module.
   *
   * @var string
   */
  protected $moduleRequestName;

  /**
   * The version of the module.
   *
   * @var string
   */
  protected $moduleRequestVersion;

  /**
   * @var string
   */
  protected $moduleName;

  /**
   * @var array
   */
  protected $moduleVersions;

  /**
   * @var string
   */
  protected $projectStatus;

  /**
   * @var ModuleManager
   */
  protected $moduleManager;

  /**
   * @var Logger
   */
  protected $logger;

  /**
   * @var RequestHandlerInterface
   */
  protected $client;

  /**
   * @var SiteManager
   */
  protected $siteManager;

  /**
   * @var SiteModuleManager
   */
  protected $siteModuleManager;

  /**
   * @var array
   */
  protected $drupalAllModuleVersions = array();

  /**
   * @var array
   */
  protected $moduleLatestVersion = array();

  /**
   * @var array
   */
  protected $majorVersions = array();

  /**
   * @param RequestHandlerInterface $client
   * @param SiteManager $siteManager
   * @param ModuleManager $moduleManager
   * @param SiteModuleManager $siteModuleManager
   * @param Logger $logger
   */
  public function __construct(RequestHandlerInterface $client, SiteManager $siteManager, ModuleManager $moduleManager, SiteModuleManager $siteModuleManager, Logger $logger) {
    $this->client = $client;
    $this->siteManager = $siteManager;
    $this->moduleManager = $moduleManager;
    $this->siteModuleManager = $siteModuleManager;
    $this->logger = $logger;
  }

  /**
   * @param string $moduleVersion
   */
  public function setModuleRequestVersion($moduleVersion) {
    $this->moduleRequestVersion = $moduleVersion . '.x';
  }

  /**
   * @param string $name
   */
  public function setModuleRequestName($name) {
    $this->moduleRequestName = $name;
  }

  /**
   * @return mixed
   */
  public function getModuleName() {
    return $this->moduleName;
  }

  /**
   * @return array
   */
  public function getModuleVersions() {
    return $this->moduleVersions;
  }

  /**
   * @return string
   */
  public function getProjectStatus() {
    return $this->projectStatus;
  }

  /**
   * {@InheritDoc}
   */
  public function processRequest() {
    $this->client->setTimeout(30);

    try {
      //$startTime = $this->getMicrotimeFloat();

      $url = $this->getRequestUrl();

      /** @var \Symfony\Component\BrowserKit\Response $response */
      $response = $this->client->get($url);
      // @todo check request header, if not 200 throw exception.
      /*if ($response->getStatus() >= 200 && $response->getStatus() < 300) {
      }*/
      $requestData = $response->getContent();

      //$endTime = $this->getMicrotimeFloat();
      //$this->requestTime = $endTime - $startTime;

      $this->processRequestData($requestData);
    }
    catch (RequestHandlerException $e) {
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Processes the data that has come back from the request.
   *
   * @param $requestData
   *   Data that has come back from the request.
   * @throws \Exception
   */
  public function processRequestData($requestData) {
    $requestXmlObject = simplexml_load_string($requestData);

    if (!isset($requestXmlObject->title)) {
      throw new \Exception('Error getting data for module: ' . $this->moduleRequestName);
    }

    $projectStatus = (string) $requestXmlObject->project_status;

    $recommendedMajorVersion = 0;
    $supportedMajorVersions = array();
    if (isset($requestXmlObject->supported_majors)) {
      $recommendedMajorVersion = (string) $requestXmlObject->recommended_major;
      $supportedMajor = (string) $requestXmlObject->supported_majors;
      $supportedMajorVersions = explode(',', $supportedMajor);
    }

    $latestReleaseVersions = array();
    foreach ($requestXmlObject->releases->release as $release) {
      if (count($supportedMajorVersions) > 0) {
        // Check if this major version is in the list of supported versions.
        if (in_array($release->version_major, $supportedMajorVersions)) {
          $key = array_search($release->version_major, $supportedMajorVersions);
          $latestReleaseVersions[$supportedMajorVersions[$key]][] = $release;

          // Get the version information for this release version.
          //$versionInfo = ModuleDocument::getVersionInfo($release->version);
          // If the version info extra data is set than this must be a non-stable
          // release (alpha, beta, rc, bug etc).
          /*if (!is_null($versionInfo['extra'])) {
            continue;
          }*/
        }
      }
    }

    $versions = array();
    foreach ($latestReleaseVersions as $releaseMajorVersion => $releaseVersions) {
      foreach ($releaseVersions as $release) {
        $isSecurityRelease = FALSE;
        if (isset($release->terms)) {
          foreach ($release->terms->term as $term) {
            if (strtolower($term->value) == 'security update') {
              $isSecurityRelease = TRUE;
            }
          }
        }

        $versionType = (isset($release->version_major) && $release->version_major == $recommendedMajorVersion) ?
          ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED :
          ModuleDocument::MODULE_VERSION_TYPE_OTHER;

        $versions[$versionType][] = array(
          'version' => isset($release->version) ? (string) $release->version : 0,
          'isSecurity' => $isSecurityRelease,
        );
      }
    }

    $this->projectStatus = $projectStatus;
    $this->moduleVersions = $versions;
    $this->moduleName = (string) $requestXmlObject->title;
  }

  /**
   * @return mixed
   */
  protected function getRequestUrl() {
    return 'http://updates.drupal.org/release-history/' . $this->moduleRequestName . '/' . $this->moduleRequestVersion;
  }

  /**
   * Get the microtime.
   *
   * @return float
   */
  protected function getMicrotimeFloat() {
    list($usec, $sec) = explode(' ', microtime());
    return ((float)$usec + (float)$sec);
  }

  /**
   * Event: Triggered on cron runs.
   */
  public function onWardenCron() {
    $this->updateAllDrupalModules();
  }

  /**
   * Update the Drupal Core and Modules with the latest versions.
   *
   * @param bool $updateNewSitesOnly
   *   Only update modules on sites marked as new.
   */
  public function updateAllDrupalModules($updateNewSitesOnly = FALSE) {
    $this->logger->addInfo('*** Starting Drupal Update Request Service ***');

    $this->majorVersions = $this->siteManager->getAllMajorVersionReleases();

    $this->updateContribModules();
    $this->updateCoreAndSitesModules($updateNewSitesOnly);

    $this->logger->addInfo('*** FINISHED Drupal Update Request Service ***');
  }

  /**
   * Get the contrib module version information.
   */
  protected function updateContribModules() {
    foreach ($this->majorVersions as $version) {
      $modules = $this->moduleManager->getAllByVersion($version);

      /** @var ModuleDocument $module */
      foreach ($modules as $module) {
        $this->logger->addInfo('Updating - ' . $module->getProjectName() . ' for version: ' . $version);

        try {
          $this->processDrupalUpdateData($module->getProjectName(), $version);
        }
        catch (\Exception $e) {
          $this->logger->addWarning(' - Unable to update module version [' . $version . ']: ' . $e->getMessage());
          continue;
        }

        $this->drupalAllModuleVersions[$version][$module->getProjectName()] = $drupalModuleVersions = $this->moduleVersions;
        $moduleVersions = array();
        // Get the recommended module version.
        if (isset($drupalModuleVersions[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED])) {
          $moduleRecommendedLatestVersion = $drupalModuleVersions[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED][0];
          $moduleVersions[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED] = $moduleRecommendedLatestVersion;
          $this->moduleLatestVersion[$version][$module->getProjectName()][ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED] = $moduleRecommendedLatestVersion;
        }
        // Get the other module version.
        if (isset($drupalModuleVersions[ModuleDocument::MODULE_VERSION_TYPE_OTHER])) {
          $moduleOtherLatestVersion = $drupalModuleVersions[ModuleDocument::MODULE_VERSION_TYPE_OTHER][0];
          $moduleVersions[ModuleDocument::MODULE_VERSION_TYPE_OTHER] = $moduleOtherLatestVersion;
          $this->moduleLatestVersion[$version][$module->getProjectName()][ModuleDocument::MODULE_VERSION_TYPE_OTHER] = $moduleOtherLatestVersion;
        }

        $module->setName($this->getModuleName());
        $module->setIsNew(FALSE);
        $module->setLatestVersion($version, $moduleVersions);
        $module->setProjectStatus($this->projectStatus);
        $this->moduleManager->updateDocument();
      }
    }
  }

  /**
   * Get Core version information.
   *
   * @param $updateNewSitesOnly
   */
  protected function updateCoreAndSitesModules($updateNewSitesOnly) {
    foreach ($this->majorVersions as $version) {
      // Update the core after the modules to update the versions of the modules
      // for a site.
      $this->logger->addInfo('Updating - Drupal version: ' . $version);

      try {
        $this->processDrupalUpdateData('drupal', $version);
      }
      catch (\Exception $e) {
        $this->logger->addWarning(' - Unable to update drupal version [' . $version . ']: ' . $e->getMessage());
        continue;
      }

      $newOnly = ($updateNewSitesOnly) ? array('isNew' => TRUE) : array();
      $sites = $this->siteManager->getDocumentsBy(array_merge(array('coreVersion.release' => $version), $newOnly));

      // Update the sites for the major version with the latest core & module version information.
      $coreVersions = isset($this->moduleVersions[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED]) ? $this->moduleVersions[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED] : NULL;

      // Check if this is an array and skip on if not.
      if (!is_array($coreVersions)) {
        continue;
      }

      $this->updateSitesByVersion($sites, $version, $coreVersions);
    }
  }

  /**
   * Gets the latest information on a module from Drupal.org.
   *
   * @param string $moduleName
   * @param int $version
   *
   * @throws \Exception
   */
  protected function processDrupalUpdateData($moduleName, $version) {
    $this->setModuleRequestName($moduleName);
    $this->setModuleRequestVersion($version);
    $this->processRequest();

    $this->moduleVersions = $this->getModuleVersions();
    $this->projectStatus = $this->getProjectStatus();
  }

  /**
   * Update the module information for each site for a particular version.
   *
   * @param array $sites
   *   Array of SiteDocument objects.
   * @param string $version
   * @param array $coreVersions
   */
  protected function updateSitesByVersion($sites, $version, $coreVersions) {
    /** @var SiteDocument $site */
    foreach ($sites as $site) {
      $this->logger->addInfo('Updating site: ' . $site->getId() . ' for version ' . $version . ' - ' . $site->getUrl());

      if ($site->getCoreReleaseVersion() != $version) {
        continue;
      }

      /** @var SiteModuleDocument $siteModule */
      $siteModule = $this->siteModuleManager->findBySiteId($site->getId());
      if (empty($siteModule)) {
        // @todo should we do this or just have a null object/ not update the modules?
        $siteModule = $this->siteModuleManager->makeNewItem();
        $siteModule->setSiteId($site->getId());
      }
      if (isset($this->moduleLatestVersion[$version])) {
        $siteModule->setModulesLatestVersion($this->moduleLatestVersion[$version]);
      }
      $this->siteModuleManager->saveDocument($siteModule);

      // Check for if the core version is out of date and requires a security update.
      $coreNeedsSecurityUpdate = $this->siteHasCoreSecurityUpdate($coreVersions, $site->getCoreVersion());
      $hasCriticalIssue = $this->updateSiteModules($version, $siteModule);
      if ($coreNeedsSecurityUpdate) {
        $hasCriticalIssue = TRUE;
      }

      $site->setLatestCoreVersion($coreVersions[0]['version'], $coreNeedsSecurityUpdate);
      $site->setIsNew(FALSE);
      $site->setHasCriticalIssue($hasCriticalIssue);
      $this->siteManager->updateDocument();
    }
  }

  /**
   * Updates the module data for each site.
   *
   * @param string $version
   * @param SiteModuleDocument $siteModule
   *
   * @return bool
   */
  protected function updateSiteModules($version, SiteModuleDocument $siteModule) {
    // Check all the site modules to see if any of them are out of date and need a security update.
    $siteHasSecurityIssues = FALSE;
    foreach ($siteModule->getModules() as $module) {
      if (!isset($module['latestVersion'])) {
        continue;
      }
      if (is_null($module['version'])) {
        continue;
      }
      if (ModuleDocument::isLatestVersion($module)) {
        continue;
      }

      // Check to see if this site's modules require a security update.
      $hasSecurityIssue = $this->moduleHasSecurityUpdate($module, $version, $siteModule);
      if ($hasSecurityIssue) {
        $siteHasSecurityIssues = TRUE;
      }
    }

    return $siteHasSecurityIssues;
  }

  /**
   * Determines if there is a security release for the core versions.
   *
   * @param array $versions
   *   The array of core versions that are supported.
   * @param string $currentVersion
   *   The current core version.
   *
   * @return bool
   *   TRUE if there is a security release, otherwise false.
   */
  protected function siteHasCoreSecurityUpdate($versions, $currentVersion) {
    $hasSecurityRelease = FALSE;
    foreach ($versions as $version) {
      if ($version['version'] == $currentVersion) {
        break;
      }

      if ($version['isSecurity']) {
        $hasSecurityRelease = TRUE;
      }
    }
    return $hasSecurityRelease;
  }

  /**
   * Determines if there is a security release for a module.
   *
   * @param array $module
   * @param string $version
   * @param SiteModuleDocument $siteModule
   *   The SiteDocument object to be updated.
   *
   * @return bool
   */
  protected function moduleHasSecurityUpdate($module, $version, SiteModuleDocument &$siteModule) {
    // If a site module is a dev version, then force it to have no security update.
    if (ModuleDocument::isDevRelease($module['version'])) {
      $drupalModule['isSecurity'] = FALSE;
      $siteModule->updateModule($module['name'], $drupalModule);
      $this->siteModuleManager->saveDocument($siteModule);
      return FALSE;
    }

    $hasSecurityRelease = FALSE;
    $siteModuleVersionInfo = ModuleDocument::getVersionInfo($module['version']);
    if (!isset($this->drupalAllModuleVersions[$version][$module['name']])) {
      print "Error: No module version found for {$module['name']} in version: $version\n";
      return FALSE;
    }
    $moduleVersionInfo = $this->drupalAllModuleVersions[$version][$module['name']];

    $versionType = NULL;
    if (isset($moduleVersionInfo[ModuleDocument::MODULE_VERSION_TYPE_OTHER])) {
      $drupalModuleOtherVersionInfo = ModuleDocument::getVersionInfo($moduleVersionInfo[ModuleDocument::MODULE_VERSION_TYPE_OTHER][0]['version']);
      $versionType = $drupalModuleOtherVersionInfo['minor'] == $siteModuleVersionInfo['minor'] ? ModuleDocument::MODULE_VERSION_TYPE_OTHER : NULL;
    }
    if (isset($moduleVersionInfo[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED]) && is_null($versionType)) {
      $drupalModuleRecommendedVersionInfo = ModuleDocument::getVersionInfo($moduleVersionInfo[ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED][0]['version']);
      $versionType = $drupalModuleRecommendedVersionInfo['minor'] >= $siteModuleVersionInfo['minor'] ? ModuleDocument::MODULE_VERSION_TYPE_RECOMMENDED : NULL;
    }

    if (!is_null($versionType)) {
      foreach ($moduleVersionInfo[$versionType] as $drupalModule) {
        if ($drupalModule['version'] == $module['version']) {
          break;
        }

        if ($drupalModule['isSecurity']) {
          unset($drupalModule['version']);
          $siteModule->updateModule($module['name'], $drupalModule);
          $this->siteModuleManager->saveDocument($siteModule);
          $hasSecurityRelease = TRUE;
        }
      }
    }

    return $hasSecurityRelease;
  }

}
