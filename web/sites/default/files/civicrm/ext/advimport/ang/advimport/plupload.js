(function($, _, ts) {

  /**
   * Weird mutations from plupload examples
   * This is called from the advimport Data Review screen
   */
  CRM.advimportPluploadInit = function() {
    $('button.crm-plupload').each(function() {
      var el = $(this);
      var button = el.attr('id');

      var uploader = new plupload.Uploader({
        browse_button: button,
        // Does not work, or needs to be an input type=file?
        // drop_element: button + '-filelist',
        multi_selection: false,
        filters: [{
          title: 'Image files', // @todo translation?
          extensions: 'jpg,gif,png'
        }],
        url: CRM.url('civicrm/plupload')
      });

      uploader.init();

      uploader.bind('FilesAdded', function(up, files) {
        // For displaying a progress bar
        var html = '';
        plupload.each(files, function(file) {
          html += '<div id="' + file.id + '" style="background: #3737f4; height: 10px;"></div>';
        });
        document.getElementById(el.attr('id') + '-filelist').innerHTML += html;

        uploader.start();
      });
      uploader.bind('UploadProgress', function(up, file) {
        $('#' + file.id).css('width', file.percent + '%');
      });
      uploader.bind('FileUploaded', function(up, file, info) {
        var obj = JSON.parse(info.response);
        var el_id = el.attr('id');

        // Make the uploaded file visible
        $('#' + el_id + '-filelist').html('<img style="max-width: 150px; max-height: 150px;" src="' + CRM.url('civicrm/plupload/view', 'photo=' + obj.result.cleanFileName) + '" />');

        // Save it in the advimport staging table
        var row_id = $('#' + el_id + '-filelist').data('id');
        var field_id = $('#' + el_id + '-filelist').data('field');

        var params = {};
        params.id = row_id;
        params[field_id] = obj.result.cleanFileName;

        CRM.api3('AdvimportRow', 'create', params).done(function(result) {
          console.log('AdvimportRow.create result', result);
          CRM.status('OK'); // @todo ts
        });

      });
      uploader.bind('UploadComplete', function(up, file) {
        console.log('civiplupload - upload complete');
      });
      uploader.bind('Error', function(up, err) {
        CRM.alert('Error: #' + err.code + ': ' + err.message, '', 'error');
      });
    });
  };

})(CRM.$, CRM._, CRM.ts('advimport'));
