CRM.$(function($) {
  $(document).ready(function() {
    $('#contact_id').change(function() {
      CRM.api3('Actm', 'buildmembershiplist', {
        "contact_id": $(this).val()
      }).then(function(result) {
        //console.log('result: ', result);

        $('#membership_id')
          .find('option')
          .remove()
          .end();

        $.each(result.values, function(value, label) {
          $('#membership_id')
            .append($(document.createElement('option')).prop({
              value: value,
              text: label
            }));
        });
      }, function(error) {
        //oops
      });
    })
  });
});
