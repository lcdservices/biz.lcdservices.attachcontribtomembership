<?php

require_once 'CRM/Core/Form.php';

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
    $this->_contributionId = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $this->_contactId = civicrm_api3('contribution', 'getvalue', [
      'id' => $this->_contributionId,
      'return' => 'contact_id',
    ]);

    $this->_mpId = CRM_Utils_Request::retrieve('mpid', 'Positive', $this);
    if ($this->_mpId) {
      try {
        $membershipId = civicrm_api3('membership_payment', 'getvalue', [
          'id' => $this->_mpId,
          'return' => 'membership_id',
        ]);
        $membership = civicrm_api3('membership', 'getsingle', ['id' => $membershipId]);
        $this->assign('existingMembership', $membership['event_title']);
      }
      catch (CiviCRM_API3_Exception $e) {}
    }

    //get current contact name.
    $this->assign('currentContactName', CRM_Contact_BAO_Contact::displayName($this->_contactId));

    $membershipList = $memberships = [];
    try {
      $memberships = civicrm_api3('membership', 'get', ['contact_id' => $this->_contactId]);
    }
    catch (CiviCRM_API3_Exception $e) {}

    foreach ($memberships['values'] as $membership) {
      $memStartDate = date('m/d/Y', strtotime($membership['start_date']));
      $membershipList[$membership['id']] = $membership['membership_name'].' :: '.$memStartDate." ({$membership['id']})";
    }
    $this->add('select', 'membership_id', ts('Select Membership'), $membershipList, TRUE);

    $this->add('hidden', 'contribution_id', $this->_contributionId, ['id' => 'contribution_id']);
    $this->add('hidden', 'contact_id', $this->_contactId, ['id' => 'contact_id']);
    $this->add('hidden', 'existing_mp_id', $this->_mpId, ['id' => 'existing_mp_id']);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());

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
    //Civi::log()->debug('postProcess', array('values' => $values));

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

      if (!empty($params['existing_mp_id'])) {
        $mpParams['id'] = $params['existing_mp_id'];
      }

      $pp = civicrm_api3('membership_payment', 'create', $mpParams);

      if ($pp) {
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
}
