<?php

require_once 'attachcontribtomembership.civix.php';
use CRM_Attachcontribtomembership_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function attachcontribtomembership_civicrm_config(&$config) {
  _attachcontribtomembership_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function attachcontribtomembership_civicrm_xmlMenu(&$files) {
  _attachcontribtomembership_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function attachcontribtomembership_civicrm_install() {
  _attachcontribtomembership_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function attachcontribtomembership_civicrm_postInstall() {
  _attachcontribtomembership_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function attachcontribtomembership_civicrm_uninstall() {
  _attachcontribtomembership_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function attachcontribtomembership_civicrm_enable() {
  _attachcontribtomembership_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function attachcontribtomembership_civicrm_disable() {
  _attachcontribtomembership_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function attachcontribtomembership_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _attachcontribtomembership_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function attachcontribtomembership_civicrm_managed(&$entities) {
  _attachcontribtomembership_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function attachcontribtomembership_civicrm_caseTypes(&$caseTypes) {
  _attachcontribtomembership_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function attachcontribtomembership_civicrm_angularModules(&$angularModules) {
  _attachcontribtomembership_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function attachcontribtomembership_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _attachcontribtomembership_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function attachcontribtomembership_civicrm_entityTypes(&$entityTypes) {
  _attachcontribtomembership_civix_civicrm_entityTypes($entityTypes);
}

function attachcontribtomembership_civicrm_searchColumns($objectName, &$headers, &$rows, &$selector) {
  /*Civi::log()->debug(__FUNCTION__, [
    'objectName' => $objectName,
    '$headers' => $headers,
    '$rows' => $rows,
    //'$selector' => $selector,
  ]);*/

  if ($objectName == 'contribution') {
    foreach ($rows as &$row) {
      //if currently attached to a participant, skip
      $eventConnExists = CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(id)
        FROM civicrm_participant_payment
        WHERE contribution_id = %1
      ", [
        1 => [$row['contribution_id'], 'Positive'],
      ]);

      if ($eventConnExists) {
        continue;
      }

      //retrieve membership connection if it exists
      $memConnId = CRM_Core_DAO::singleValueQuery("
        SELECT id
        FROM civicrm_membership_payment
        WHERE contribution_id = %1
        LIMIT 1
      ", [
        1 => [$row['contribution_id'], 'Positive'],
      ]);

      $actionLabel = 'Attach';
      $memParam = '';

      if ($memConnId) {
        $actionLabel = 'Move';
        $memParam = "&mpid={$memConnId}";
      }

      //action column is either a series of links, or a series of links plus a subset
      //unordered list (more button) -- all of which is enclosed in a span
      //we want to inject our option at the end, regardless, so we look for the existence
      //of a <ul> tag and adjust our injection accordingly
      $url = CRM_Utils_System::url('civicrm/attachtomem', "reset=1&id={$row['contribution_id']}{$memParam}");
      $urlLink = "<a href='{$url}' class='action-item crm-hover-button medium-popup move-contrib'>{$actionLabel} to Membership</a>";

      if (strpos($row['action'], '</ul>') !== FALSE) {
        $row['action'] = str_replace('</ul>', '<li>'.$urlLink.'</li></ul>', $row['action']);
      }
      else {
        //if there is no more... link, let's create one
        $more = "
          <span class='btn-slide crm-hover-button'>more
            <ul class='panel' style='display: none;'>
              <li>{$urlLink}</li>
            </ul>
          </span>
        ";
        $row['action'] = str_replace('</span>', '</span>'.$more, $row['action']);
      }
    }
  }
}
