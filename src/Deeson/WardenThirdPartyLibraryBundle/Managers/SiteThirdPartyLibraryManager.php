<?php

namespace Deeson\WardenThirdPartyLibraryBundle\Managers;

use Deeson\WardenBundle\Managers\BaseManager;
use Deeson\WardenBundle\Managers\SiteManager;
use Deeson\WardenThirdPartyLibraryBundle\Document\SiteThirdPartyLibraryDocument;
use Monolog\Logger;

class SiteThirdPartyLibraryManager extends BaseManager {

  /**
   * @var SiteManager
   */
  protected $siteManager;

  public function __construct($doctrine, Logger $logger) {
    parent::__construct($doctrine, $logger);
  }

  /**
   * @return string
   *   The type of this manager.
   *   e.g. 'LibraryDocument'
   */
  public function getType() {
    return 'SiteThirdPartyLibraryDocument';
  }

  /**
   * Create a new empty type of the object.
   *
   * @return SiteThirdPartyLibraryDocument
   */
  public function makeNewItem() {
    return new SiteThirdPartyLibraryDocument();
  }

  /**
   * @param \Deeson\WardenBundle\Document\SiteDocument $site
   *
   * @return null|object
   */
  public function findBySiteId($site) {
    return $this->getRepository()->findOneBy(array('siteId' => $site));
  }

  /**
   * The Mongodb repository name.
   *
   * @return string
   */
  protected function getRepositoryName() {
    return 'DeesonWardenThirdPartyLibraryBundle:' . $this->getType();
  }
}
