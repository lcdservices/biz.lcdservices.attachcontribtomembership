<?php

require_once 'attachcontribtomembership.civix.php';
use CRM_LCD_Attachcontribtomembership_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_LCD_Attachcontribtomembership_Form_AttachMembership extends CRM_Core_Form {

  public $_contributionId;
  public $_contactId;
  public $_mpId;

  /**
   * check permissions
   */
  public function preProcess() {
    if (!CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::UPDATE)) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }
    parent::preProcess();
  }

  public function buildQuickForm() {
    //Civi::log()->debug(__METHOD__, ['this' => $this]);

    Civi::resources()->addScriptFile(E::LONG_NAME, 'js/AttachMembership.js');

    $this->_contributionId = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    //set contact_id from submit values (validation step), else retrieve from contribution record
    if (!empty($this->_submitValues['contact_id'])) {
      $this->_contactId = $this->_submitValues['contact_id'];
    }
    else {
      $this->_contactId = civicrm_api3('contribution', 'getvalue', [
        'id' => $this->_contributionId,
        'return' => 'contact_id',
      ]);
    }

    $this->addEntityRef('contact_id', ts('Select Contact'));
    $this->setDefaults(['contact_id' => $this->_contactId]);
    $descriptions = [];

    //get current contact name.
    $this->assign('currentContactName', CRM_Contact_BAO_Contact::displayName($this->_contactId));

    $membershipList = self::buildMemList();
    $this->add('select', 'membership_id', ts('Membership'), $membershipList, TRUE);
    $this->add('hidden', 'contribution_id', $this->_contributionId, ['id' => 'contribution_id']);

    //determine if there is an existing membership_payment
    try {
      $memPmt = civicrm_api3('membership_payment', 'get', [
        'contribution_id' => $this->_contributionId,
      ]);

      if (!empty($memPmt['count'])) {
        $options = [
          1 => 'Replace existing connection',
          2 => 'Create additional connection',
        ];
        $this->addRadio('existing_mp_process', ts('Attachment behavior'),
          $options, NULL, '<br />', TRUE);
        $descriptions['existing_mp_process'] = 'This contribution is already attached to a membership record. Decide how you want to handle this new connection.';
      }
    }
    catch (CiviCRM_API3_Exception $e) {}

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    $this->assign('descriptions', $descriptions);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ],
    ]);

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    //Civi::log()->debug(__METHOD__, ['values' => $values]);

    //process
    $result = $this->attachToMembership($values);

    if ($result) {
      CRM_Core_Session::setStatus(ts('Contribution attached to membership successfully.'), ts('Attached'), 'success');
    }
    else {
      CRM_Core_Session::setStatus(ts('Unable to attach contribution to membership.'), ts('Error'), 'error');
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  function attachToMembership($params) {
    try {
      $mpParams = [
        'membership_id' => $params['membership_id'],
        'contribution_id' => $params['contribution_id'],
      ];

      if (!empty($params['existing_mp_process'])) {
        switch ($params['existing_mp_process']) {
          case 1:
            //delete existing MP records tied to this contribution
            //we are deleting rather than retrieving and updating the ID because there may be multiple MP records
            CRM_Core_DAO::executeQuery("
              DELETE FROM civicrm_membership_payment
              WHERE contribution_id = %1
            ", [
              1 => [$params['contribution_id'], 'Positive']
            ]);

            break;

          case 2:
            //do nothing; create new MP
            break;

          default:
            return FALSE;
        }
      }

      $pp = civicrm_api3('membership_payment', 'create', $mpParams);

      $this->resolveLineItems($params['membership_id']);

      if (empty($pp['is_error'])) {
        $subject = "Contribution #{$params['contribution_id']} Attached to Membership";
        $details = "Contribution #{$params['contribution_id']} was attached to membership #{$params['membership_id']}.";

        $activityTypeID = CRM_Core_OptionGroup::getValue('activity_type',
          'contribution_attached_to_membership',
          'name'
        );

        $activityParams = [
          'activity_type_id' => $activityTypeID,
          'activity_date_time' => date('YmdHis'),
          'subject' => $subject,
          'details' => $details,
          'status_id' => 2,
        ];

        $activityParams['source_contact_id'] = CRM_Core_Session::getLoggedInContactID();
        $activityParams['target_contact_id'][] = $params['contact_id'];

        civicrm_api3('activity', 'create', $activityParams);

        return TRUE;
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      Civi::log()->debug(__FUNCTION__, ['$e' => $e]);
    }

    return FALSE;
  }

  function buildMemList($cid = NULL) {
    try {
      if (empty($cid)) {
        $cid = $this->_contactId;
      }
      $membershipList = civicrm_api3('Actm', 'buildmembershiplist', ['contact_id' => $cid]);
    }
    catch (CiviCRM_API3_Exception $e) {}

    return $membershipList['values'] ?? [];
  }

  function resolveLineItems($memId) {
    if (empty($memId)) {
      return;
    }

    try {
      $mp = civicrm_api3('MembershipPayment', 'get', [
        'membership_id' => $memId,
        'options' => ['limit' => 0],
      ]);
      //Civi::log()->debug(__METHOD__, ['$mp' => $mp]);

      //update line item entity_id to new membership id
      foreach ($mp['values'] as $pmt) {
        $lines = civicrm_api3('LineItem', 'get', [
          'contribution_id' => $pmt['contribution_id'],
          'entity_table' => 'civicrm_membership',
          'options' => ['limit' => 0],
          'return' => ['contribution_id.contribution_status_id'],
        ]);
        //Civi::log()->debug(__METHOD__, ['$lines' => $lines]);

        foreach ($lines['values'] as $line) {
          $params = [
            'id' => $line['id'],
            'entity_id' => $memId,
          ];

          //if canceled, set qty to 0
          if ($line['contribution_id.contribution_status_id'] == 3) {
            $params['qty'] = 0;
            $params['line_total'] = 0;
          }

          civicrm_api3('LineItem', 'create', $params);
        }
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      Civi::log()->debug(__METHOD__, ['e' => $e]);
    }
  }
}
