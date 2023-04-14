<?php

function _civicrm_api3_actm_buildmembershiplist_spec(&$spec) {
  $spec['contact_id'] = [
    'title' => 'Contact ID',
    'api.required' => TRUE,
  ];
}

/**
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_actm_buildmembershiplist($params) {
  $memberships = $membershipList = [];
  try {
    if (!empty($params['contact_id'])) {
      $memberships = civicrm_api3('membership', 'get', ['contact_id' => $params['contact_id']]);
    }
  }
  catch (CiviCRM_API3_Exception $e) {}

  //get settings
  $settings = CRM_Utils_Array::explodePadded(Civi::settings()->get('actm_displayfields'));
  //Civi::log()->debug(__FUNCTION__, ['settings' => $settings]);

  foreach ($memberships['values'] as $membership) {
    $selectParts = [
      $membership['membership_name'],
    ];

    foreach ($settings as $setting) {
      if (empty($membership[$setting])) {
        continue;
      }

      switch ($setting) {
        case 'start_date':
        case 'end_date':
          $selectParts[] = date('m/d/Y', strtotime($membership[$setting]));
          break;
        case 'status_id':
          $selectParts[] = CRM_Member_PseudoConstant::membershipStatus($membership[$setting]);
          break;
        default:
          $selectParts[] = $membership[$setting];
      }
    }

    $membershipList[$membership['id']] = implode(' :: ', $selectParts);
  }

  return civicrm_api3_create_success(
    $membershipList,
    $params,
    'actm',
    'buildmembershiplist'
  );
}
