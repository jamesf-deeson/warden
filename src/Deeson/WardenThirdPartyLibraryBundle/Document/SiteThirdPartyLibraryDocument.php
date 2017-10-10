<?php

namespace Deeson\WardenThirdPartyLibraryBundle\Document;

use Deeson\WardenBundle\Document\BaseDocument;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(
 *     collection="siteThirdPartyLibrary"
 * )
 */
class SiteThirdPartyLibraryDocument extends BaseDocument {

  /**
   * @Mongodb\Field(type="string")
   */
  protected $siteId;

  /**
   * @Mongodb\Hash
   */
  protected $libraries;

  /**
   * @return mixed
   */
  public function getSiteId() {
    return $this->siteId;
  }

  /**
   * @param mixed $site
   */
  public function setSiteId($site) {
    $this->siteId = $site;
  }

  /**
   * Get the site third party libraries.
   *
   * @return mixed
   */
  public function getLibraries() {
    return (!empty($this->libraries)) ? $this->libraries : array();
  }

  /**
   * Set the current third party libraries for the site.
   *
   * @param array $libraryData
   *   List of third party library data to add to the site.
   */
  public function setLibraries($libraryData) {
    $libraryList = array();
    foreach ($libraryData as $type => $typeData) {
      foreach ($typeData as $name => $version) {
        $libraryList[$type][] = array(
          'name' => $name,
          'version' => $version,
        );
      }
      ksort($libraryList[$type]);
    }
    ksort($libraryList);
    $this->libraries = $libraryList;
  }

}
