<?php

return [
  'actm_displayfields' => [
    'group' => 'actm',
    'name' => 'actm_displayfields',
    'title' => ts('Fields included in membership selection dropdown'),
    'type' => 'String',
    'default' => FALSE,
    'html_type' => 'checkboxes',
    'quick_form_type' => 'CheckBoxes',
    'options' => [
      'id' => 'Membership ID',
      'join_date' => 'Join Date',
      'start_date' => 'Start Date',
      'end_date' => 'End Date',
      'source' => 'Membership Source',
      'status_id' => 'Status',
    ],
    'serialize' => CRM_Core_DAO::SERIALIZE_SEPARATOR_BOOKEND,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Select fields in the membership records to be displayed in the selection dropdown. Membership type will always be included.'),
    'settings_pages' => ['actm' => ['weight' => 1]],
  ],
];
