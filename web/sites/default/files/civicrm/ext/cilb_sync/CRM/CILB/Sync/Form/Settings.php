<?php

use CRM_CILB_Sync_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_CILB_Sync_Form_Settings extends CRM_Admin_Form_Setting {
    protected $_settings = [
        'sftp_pearson_url' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_pearson_url_port' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_pearson_user' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_pearson_password' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_pearson_home_dir' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        
        'sftp_cilb_url' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_cilb_url_port' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_cilb_user' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_cilb_password' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
        'sftp_cilb_home_dir' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
    ];

    public function buildQuickForm() {

        $this->assign('pearson_fields', $this->getGroupSettings('pearson'));
        $this->assign('cilb_fields', $this->getGroupSettings('cilb'));

        parent::buildQuickForm();
    }

    private function getGroupSettings($groupName) {
        $group = [];
        foreach($this->_settings as $key => $value) {
            if (stripos($key, 'sftp_'.$groupName.'_') !== false) {
                $group[$key] = $value;
            }
        }
        return $group;
    }

  /**
   * Override the default postProcess hook so that we can save an encrypted version of the password
   */
  public function postProcess() {
    $params = $this->controller->exportValues($this->_name);
    
    if (isset($params['sftp_pearson_password'])) {
      // If the password was set, we encrypt it before processing
      $encryptedSecret = \Civi::service('crypto.token')->encrypt($params['sftp_pearson_password'], 'CRED');
      $params['sftp_pearson_password'] = $encryptedSecret;
    }

    if (isset($params['sftp_cilb_password'])) {
      // If the password was set, we encrypt it before processing
      $encryptedSecret = \Civi::service('crypto.token')->encrypt($params['sftp_cilb_password'], 'CRED');
      $params['sftp_cilb_password'] = $encryptedSecret;
    }

    // Call the normal processing function
    self::commonProcess($params);
  }
  
  /**
   * Set default values for the form.
   */
  public function setDefaultValues() {
    parent::setDefaultValues();

    // Default ports
    if (empty($this->_defaults['sftp_pearson_url_port'])) {
        $this->_defaults['sftp_pearson_url_port'] = 22;
    }
    if (empty($this->_defaults['sftp_cilb_url_port'])) {
        $this->_defaults['sftp_cilb_url_port'] = 22;
    }
    

    $pearsonPwd = Civi::settings()->get('sftp_pearson_password');
    $cilbPwd = Civi::settings()->get('sftp_cilb_password');
    if (!empty($pearsonPwd)) {
        try {
            $this->_defaults['sftp_pearson_password'] = \Civi::service('crypto.token')->decrypt($this->_defaults['sftp_pearson_password']);
        }
        catch (Exception $e) {
            Civi::log()->error($e->getMessage());
            CRM_Core_Session::setStatus(ts('Unable to retrieve the encrypted password. Please check your configured encryption keys. The error message is: %1', [1 => $e->getMessage()]), ts("Encryption key error"), "error");
        }
    }
    if (!empty($cilbPwd)) {
        try {
            $this->_defaults['sftp_cilb_password'] = \Civi::service('crypto.token')->decrypt($this->_defaults['sftp_cilb_password']);
        }
        catch (Exception $e) {
            Civi::log()->error($e->getMessage());
            CRM_Core_Session::setStatus(ts('Unable to retrieve the encrypted password. Please check your configured encryption keys. The error message is: %1', [1 => $e->getMessage()]), ts("Encryption key error"), "error");
        }
    }

    return $this->_defaults;
  }
}

