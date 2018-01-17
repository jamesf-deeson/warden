<?php

namespace Deeson\WardenDrupalBundle\Managers;

use Deeson\WardenDrupalBundle\Document\SiteDrupalDocument;

class SiteDrupalManager extends DrupalBaseManager {

  /**
   * @return string
   *   The type of this manager.
   */
  public function getType() {
    return 'SiteDrupalDocument';
  }

  /**
   * Create a new empty type of the object.
   *
   * @return SiteDrupalDocument
   */
  public function makeNewItem() {
    return new SiteDrupalDocument();
  }

  /**
   * @param \Deeson\WardenBundle\Document\SiteDocument $site
   *
   * @return null|object
   */
  public function findBySiteId($site) {
    return $this->getRepository()->findOneBy(array('siteId' => new \MongoId($site)));
  }
}
