<?php

namespace Drupal\cilb_admin_dashboard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminDashboardSettingsForm extends ConfigFormBase {

  const SETTINGS = 'cilb_admin_dashboard.settings';

  public function getFormId() {
    return 'cilb_admin_dashboard_settings_form';
  }

  protected function getEditableConfigNames() {
    return [self::SETTINGS];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);
    $links = $config->get('links') ?: [];

    $form['links'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Admin Dashboard Links'),
      '#description' => $this->t('One link per line. Format: <code>Title|URL</code>. URL can be internal (starting with /) or external (http...).'),
      '#default_value' => $this->linksToString($links),
      '#rows' => 10,
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $links_text = $form_state->getValue('links');
    $lines = array_filter(array_map('trim', explode("\n", $links_text)));
    foreach ($lines as $line) {
      if (strpos($line, '|') === FALSE) {
        $form_state->setErrorByName('links', $this->t('Each line must be in the format: Title|URL'));
      }
      else {
        list($title, $url) = explode('|', $line, 2);
        if (empty(trim($title)) || empty(trim($url))) {
          $form_state->setErrorByName('links', $this->t('Both title and URL are required in each line.'));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $links_text = $form_state->getValue('links');
    $lines = array_filter(array_map('trim', explode("\n", $links_text)));
    $links = [];
    foreach ($lines as $line) {
      list($title, $url) = explode('|', $line, 2);
      $links[] = [
        'title' => trim($title),
        'url' => trim($url),
      ];
    }

    $this->configFactory->getEditable(self::SETTINGS)
      ->set('links', $links)
      ->save();

    parent::submitForm($form, $form_state);
  }

  private function linksToString(array $links): string {
    $lines = [];
    foreach ($links as $link) {
      $lines[] = $link['title'] . '|' . $link['url'];
    }
    return implode("\n", $lines);
  }

}
