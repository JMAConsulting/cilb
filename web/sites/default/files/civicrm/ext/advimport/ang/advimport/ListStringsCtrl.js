(function(angular, $, _) {
  angular.module('advimport').config(function($routeProvider) {
      $routeProvider.when('/advimport/:id/:status?', {
        controller: 'AdvimportDataReviewCtrl',
        controllerAs: '$ctrl',
        templateUrl: '~/advimport/ListStringsCtrl.html',

        // If you need to look up data when opening the page, list it out under "resolve".
        resolve: {
          advimportDetails: function(crmApi4, $route) {
            return crmApi4('Advimport', 'get', {where: [["id", "=", $route.current.params.id]]}, 0);
          },
          fields: function(crmApi, $route) {
            return crmApi('AdvimportRow', 'field', {id: $route.current.params.id, 'options': {'language': CRM.config.locale }});
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('advimport').controller('AdvimportDataReviewCtrl', function($scope, $routeParams, $filter, crmApi, crmApi4, crmStatus, crmUiHelp, advimportDetails, fields) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('advimport');

    // See: templates/CRM/advimport/ListStringsCtrl.hlp
    var hs = $scope.hs = crmUiHelp({file: 'CRM/advimport/ListStringsCtrl'});

    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var $ctrl = this;

    // These things are used in the .html file
    $ctrl.crmExportUrl = '';

    $ctrl.advimportDetails = advimportDetails;

    // @todo Should be ctrl?
    $scope.fields = fields;

    $ctrl.advimport_status = '';
    $ctrl.filter_field = '';
    $ctrl.filter_value = '';

    // Fields that support bulk update
    $ctrl.bulkupdate_field_options = {};
    $ctrl.bulkupdate_possible = false;
    $ctrl.bulkupdate_fields = {};
    $ctrl.bulkupdate_field = '';
    $ctrl.bulkupdate_value = '';

    $.each(fields.values, function(index, value) {
      if (typeof value.bulk_update != 'undefined' && value.bulk_update) {
        $ctrl.bulkupdate_possible = true;
        $ctrl.bulkupdate_fields[value.key] = value;
        $ctrl.bulkupdate_field_options[value.key] = value.options || [];
      }
    });

    // Hide the download button by default
    $('#crm-advimport-download').hide();

    /**
     * Search callback.
     */
    $scope.search = function search() {
      var params = {};

      // Default to displaying 10 rows, and keep the old setting if we are reloading data
      var pageLength = $('select[name=crm-advimport-searchresults-table_length]').val() || 10;

      if (! $scope.advimport_id) {
        return;
      }

      params.id = $scope.advimport_id;

      if ($ctrl.advimport_status) {
        params.status = $ctrl.advimport_status;
      }

      if ($ctrl.filter_field && $ctrl.filter_value) {
        params.filter_field = $ctrl.filter_field;
        params.filter_value = $ctrl.filter_value;
      }

      crmApi('Advimport', 'geterrors', params).then(function(result) {
        var columns = [];

        if (result.values.length === 0) {
          CRM.alert(ts('No results matched the selected filters.'), '', 'info');
          return;
        }

        $.each(result.values[0], function(index, value) {
          columns.push({
            title: (typeof fields.values[index] != 'undefined' ? fields.values[index].label : index),
            name: index,
            data: index
          });
        });

        $("#crm-advimport-searchresults").addClass('blockOverlay');

        // Columns might change between reloads, so destroy the table completely.
        if ($.fn.dataTable.isDataTable('#crm-advimport-searchresults-table')) {
          $('#crm-advimport-searchresults-table').DataTable().destroy();
          $('#crm-advimport-searchresults-table').empty();
        }

        $('#crm-advimport-searchresults-table').DataTable({
          data: result.values,
          columns: columns,
          processing: true,
          pageLength: pageLength,
          fnDrawCallback: function(settings) {
            // FIXME Not very efficient way of enabling inline-edit on the table, but proof of concept.. one hopes.
            $('#crm-advimport-searchresults-table td:nth-child(n+4):not(".crm-advimport-searchresults-processed")').each(function() {
              var $this = $(this);
              var entity_type = 'Advimport';
              var row_id = $this.parent().find('td:nth-child(1)').text();

              var field_id = columns[$this.index()].name;
              var id = $scope.advimport_id + '-' + row_id;
              var html = $this.html();

              if (typeof fields.values[field_id] != 'undefined' && fields.values[field_id].html_type == 'img') {
                // If it's readonly, then we display using the URL as-is, because it's already formatted
                if (fields.values[field_id].readonly) {
                  if (html) {
                    $this.html('<img style="max-width: 150px; max-height: 150px;" src="' + html + '">');
                  }
                }
                else {
                  if (html) {
                    // The image_URL is already formatted, but if a file was already uploaded in advimport, then it's just the filename
                    html = CRM.url('civicrm/plupload/view', 'photo=' + html);
                    html = '<img style="max-width: 150px; max-height: 150px;" src="' + html + '">';
                  }
                  // For now, only support upload, use a separate column to show the old value
                  $this.html(html + '<div id="crm-plupload-' + row_id + '-filelist" data-entity="AdvimportRow" data-id="' + id + '" data-field="' + field_id + '"></div>'
                    + '<button class="btn btn-secondary crm-plupload" id="crm-plupload-' + row_id + '" href="javascript:;">' + ts('Browse...') + '</button>');
                }
              }
              else if (fields.values[field_id].readonly) {
                // Do not enable inline edit
              }
              else if (Object.keys(fields.values[field_id].options).length > 0) {
                $this.html('<div class="crm-entity" data-entity="AdvimportRow" data-id="' + id + '"><div class="crm-editable" data-action="create" data-type="select" data-field="' + field_id + '" data-options=\'' + _.escape(JSON.stringify(fields.values[field_id].options)) + '\'>' + _.escape(html) + '</div></div>');
              }
              else {
                $this.html('<div class="crm-entity" data-entity="AdvimportRow" data-id="' + id + '"><div class="crm-editable" data-action="create" data-type="text" data-field="' + field_id + '">' + _.escape(html) + '</div></div>');
              }

              // Otherwise when paging back/forth in the results, we will process
              // the same elements over and over. Find a better way?
              $this.closest('td').addClass('crm-advimport-searchresults-processed');
            });

            $('#crm-advimport-searchresults-table td:nth-child(1):not(".crm-advimport-searchresults-processed")').each(function() {
              var $this = $(this);
              var entity_type = 'Advimport';
              var row_id = $this.parent().find('td:nth-child(1)').text();

              var $th = $this.closest('table').find('th').eq($this.index());
              var field_id = $th.text();
              var id = $scope.advimport_id + '-' + row_id;

              var html = $this.html();
              $this.html('<a class="crm-advimport-row-id" data-id="' + id + '">' + _.escape(html) + '</div>');

              // Otherwise when paging back/forth in the results, we will process
              // the same elements over and over. Find a better way?
              $this.closest('td').addClass('crm-advimport-searchresults-processed');
            });

            $('a.crm-advimport-row-id').on('click', function(event) {
              var $this = $(this);
              event.preventDefault();

              var id = $this.data('id');

              CRM.api3('AdvimportRow', 'import', {
                id: id
              }).done(function(result) {
                var alert_type = 'success';
                var alert_title = ts('Success');
                var cell_text = '';

                if (result.is_error) {
                  alert_type = 'error';
                  alert_title = ts('Error');
                  cell_text = result.error_message;
                }
                else {
                  cell_text = (result.messages ? result.messages.join('<br>') : '');
                  $this.closest('tr').find('td:nth-child(4)').text(result.entity_table);
                  $this.closest('tr').find('td:nth-child(5)').text(result.entity_id);

                  if (result.status != 1) {
                    alert_type = 'warning';
                    alert_title = ts('Warning');
                  }
                }

                CRM.alert(cell_text, alert_title, alert_type);
                $this.closest('tr').find('td:nth-child(3)').text(cell_text);
              });
            });

            $('#crm-advimport-searchresults-table').trigger('crmLoad');

            // I guess this could listen for crmLoad, but this seems more reliable
            if (CRM.advimportPluploadInit != 'undefined') {
              CRM.advimportPluploadInit();
            }
          }

        });

        $('#crm-advimport-searchresults').removeClass('blockOverlay');

        // For now, we do not allow to export from Batch Update
        // because it would be really confusing for users.
        if (advimportDetails.filename != 'Batch Update') {
          $ctrl.crmExportUrl = CRM.url('civicrm/advimport/export', 'id=' + $scope.advimport_id + '&import_status=' + $ctrl.advimport_status);
        }
      });
    };

    /**
     * Bulk update callback.
     */
    $scope.bulkupdate = function bulkupdate() {
      CRM.api3('AdvimportRow', 'bulkupdate', {
        id: $scope.advimport_id,
        field: $ctrl.bulkupdate_field,
        value: $ctrl.bulkupdate_value
      }).done(function(result) {
        $scope.search();

        if (parseInt(result.count) > 0) {
          Swal.fire({
            icon: 'success',
            title: ts('Update complete'),
            html: ts('%1 rows updated.', {1: result.count}) + '<br><br>' + ts('Reminder: To process the actual update, you still need to process all items.'),
          })
        }
        else {
          Swal.fire({
            icon: 'warning',
            title: ts('Nothing to update'),
            text: ts('Either all rows are already of the specified value, or it was not possible to update the values.')
          })
        }
      });
    };

    /**
     * "Process all items" button callback.
     */
    $scope.runimport = function runimport($event) {
      $event.stopPropagation();
      $event.preventDefault();

      // A bit of a hack: We call the advimport quickform screen, and that will
      // output a 'Start Import' button, which allows to do a POST with a valid
      // QuickForm session. Hence not displaying a confirm button.
      var options = {
        options: {no: ts("Cancel")},
        url: CRM.url('civicrm/advimport?reset=1&aid=' + $scope.advimport_id)
      };

      CRM.confirm(options);
    };

    /**
     * New row button callback.
     */
    $scope.newrow = function newrow($event) {
      $event.stopPropagation();
      $event.preventDefault();

      var options = {
        options: {no: ts("Cancel"), yes: ts("Add")},
        url: CRM.url('civicrm/advimport/newrow?reset=1&aid=' + $scope.advimport_id),
      };

      CRM.confirm(options)
        .on('crmConfirm:yes', function() {
          // Validate required fields
          // Ideally should use crmValidate, but could not get it to work on these modals
          // Probably should use CRM.loadForm not CRM.confirm.
          var valid = true;

          $('input.required,select.required', this).each(function() {
            if (!$(this).val()) {
              valid = false;
              $(this).addClass('error');
            }
          });

          if (!valid) {
            CRM.alert(ts('Please fill in all the required fields.'), ts('Error'), 'error');
            return valid;
          }

          // Post the data to create the row
          var data = {};

          $('input,select', this).each(function() {
            var id = $(this).attr('id');

            data[id] = $(this).val();
          });

          data['id'] = $routeParams.id;

          CRM.api3('AdvimportRow', 'create', data).done(function(result) {
            console.log('AdvimportRow.create result', result);
            $scope.search();
          });
        });
    };

    var has_url_params = false;

    if (typeof $routeParams.id != 'undefined') {
      $scope.advimport_id = $routeParams.id;
      has_url_params = true;
    }

    if (typeof $routeParams.status != 'undefined') {
      $ctrl.advimport_status = $routeParams.status;
      has_url_params = true;
    }

    if (has_url_params) {
      $scope.search();
    }
  });

})(angular, CRM.$, CRM._);
