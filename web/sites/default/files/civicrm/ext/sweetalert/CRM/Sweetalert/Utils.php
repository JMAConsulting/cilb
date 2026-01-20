<?php

class CRM_Sweetalert_Utils {

  public static function setStatus($text, $title = '', $type = 'alert') {
    $session = CRM_Core_Session::singleton();
    $messages = $session->get('sweetalert_messages');
    if (!is_array($messages)) {
      $messages = [];
    }
    $messages[] = [
      'text' => $text,
      'title' => $title,
      'type' => $type,
    ];
    $session->set('sweetalert_messages', $messages);
  }

  public static function getStatus() {
    $session = CRM_Core_Session::singleton();
    $messages = $session->get('sweetalert_messages');
    $session->set('sweetalert_messages', []);
    return $messages;
  }

}
