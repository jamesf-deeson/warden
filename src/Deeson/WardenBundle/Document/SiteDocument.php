<?php

namespace Deeson\WardenBundle\Document;

use Deeson\WardenDrupalBundle\Document\ModuleDocument;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(
 *     collection="sites"
 * )
 */
class SiteDocument extends BaseDocument {

  /**
   * @Mongodb\Field(type="string")
   */
  protected $name;

  /**
   * @Mongodb\Boolean
   */
  protected $isNew = TRUE;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $url;

  /**
   * @Mongodb\Hash
   */
  protected $coreVersion;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $wardenToken;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $wardenEncryptToken;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $authUser;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $authPass;

  /**
   * @Mongodb\Boolean
   */
  protected $hasCriticalIssue;

  /**
   * @Mongodb\Hash
   */
  protected $additionalIssues;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $lastSuccessfulRequest;

  /**
   * @Mongodb\Field(type="string")
   */
  protected $siteType;

  /**
   * @return mixed
   */
  public function getName() {
    return (empty($this->name)) ? '[Site Name]' : $this->name;
  }

  /**
   * @param mixed $name
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * @return string
   */
  public function getWardenToken() {
    return $this->wardenToken;
  }

  /**
   * @param string $wardenToken
   */
  public function setWardenToken($wardenToken) {
    $this->wardenToken = $wardenToken;
  }

  /**
   * @return mixed
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * @param mixed $url
   */
  public function setUrl($url) {
    $this->url = $url;
  }

  /**
   * @return boolean
   */
  public function getHasCriticalIssue() {
    return $this->hasCriticalIssue;
  }

  /**
   * @param boolean $hasCriticalIssue
   */
  public function setHasCriticalIssue($hasCriticalIssue) {
    $this->hasCriticalIssue = $hasCriticalIssue;
  }

  /**
   * @return mixed
   */
  public function getCoreVersion() {
    return (empty($this->coreVersion['current'])) ? '0' : $this->coreVersion['current'];
  }

  /**
   * @param mixed $coreVersion
   */
  public function setCoreVersion($coreVersion) {
    // @todo how to get this?
    $majorRelease = ModuleDocument::getMajorVersion($coreVersion);
    if (!isset($this->coreVersion)) {
      $this->coreVersion = array();
    }
    /*$this->coreVersion = array_merge(array(
      'release' => $majorRelease,
      'current' => $coreVersion,
    ));*/
    $this->coreVersion['release'] = $majorRelease;
    $this->coreVersion['current'] = $coreVersion;
  }

  /**
   * @return mixed
   */
  public function getCoreReleaseVersion() {
    return (empty($this->coreVersion['release'])) ? '0' : $this->coreVersion['release'];
  }

  /**
   * @return mixed
   */
  public function getLatestCoreVersion() {
    return (empty($this->coreVersion['latest'])) ? '0' : $this->coreVersion['latest'];
  }

  /**
   * @param mixed $latestVersion
   * @param boolean $isSecurity
   */
  public function setLatestCoreVersion($latestVersion, $isSecurity = FALSE) {
    /*$this->coreVersion += array(
      'latest' => $latestVersion,
      'isSecurity' => $isSecurity,
    );*/
    $this->coreVersion['latest'] = $latestVersion;
    $this->coreVersion['isSecurity'] = $isSecurity;
  }

  /**
   * @return boolean
   */
  public function getIsSecurityCoreVersion() {
    return (empty($this->coreVersion['isSecurity'])) ? FALSE : $this->coreVersion['isSecurity'];
  }

  /**
   * @return mixed
   */
  public function getIsNew() {
    return $this->isNew;
  }

  /**
   * @param boolean $isNew
   */
  public function setIsNew($isNew) {
    $this->isNew = $isNew;
  }

  /**
   * @return mixed
   */
  public function getAuthPass() {
    return !empty($this->authPass) ? $this->authPass : NULL;
  }

  /**
   * @param mixed $authPass
   */
  public function setAuthPass($authPass) {
    $this->authPass = $authPass;
  }

  /**
   * @return mixed
   */
  public function getAuthUser() {
    return !empty($this->authUser) ? $this->authUser : NULL;
  }

  /**
   * @param mixed $authUser
   */
  public function setAuthUser($authUser) {
    $this->authUser = $authUser;
  }

  /**
   * @return mixed
   */
  public function getAdditionalIssues() {
    return !empty($this->additionalIssues) ? $this->additionalIssues : array();
  }

  /**
   * @param mixed $additionalIssues
   */
  public function setAdditionalIssues($additionalIssues) {
    // @todo format of these issues??
    $this->additionalIssues = array_merge($this->getAdditionalIssues(), $additionalIssues);
  }

  /**
   * Compare the current core version with the latest core version.
   *
   * @return bool
   *   TRUE if the current core version is less than the latest core version.
   */
  public function hasOlderCoreVersion() {
    return $this->getCoreVersion() < $this->getLatestCoreVersion();
  }

  /**
   * @return mixed
   */
  public function getLastSuccessfulRequest() {
    return !empty($this->lastSuccessfulRequest) ? $this->lastSuccessfulRequest : 'No request completed yet';
  }

  /**
   * Set the last successful datetime.
   */
  public function setLastSuccessfulRequest() {
    $this->lastSuccessfulRequest = date('d/m/Y H:i:s');
  }

}
