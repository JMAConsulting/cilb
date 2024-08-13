<?php

namespace Drupal\cilb_chargeback_links\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cilb chargeback links Handler Plugin.
 *
 * @WebformHandler(
 *   id = "chargeback_links",
 *   label = @Translation("Chargeback Links"),
 *   category = @Translation("CRM"),
 *   description = @Translation("Saves the necessary data for a chargeback link into the participant record"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class ChargebackHandler extends WebformHandlerBase {

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->civicrm = $container->get('civicrm');
    $instance->database = \Drupal::database();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->civicrm->initialize();
    $civicrm_submission_data = $this->database->query("SELECT civicrm_data FROM {webform_civicrm_submissions} WHERE sid = :sid", [
      ':sid' => $webform_submission->id(),
    ]);
    if ($civicrm_submission_data) {
      while ($row = $civicrm_submission_data->fetchAssoc()) {
        $url = $webform_submission->toUrl()->toString();

        $data = unserialize($row['civicrm_data']);
        $participant_id = $data['participant'][1]['id'] ?? NULL;
        if ($participant_id) {
          \Civi\Api4\Participant::update(FALSE)
            ->addWhere('id','=', $participant_id)
            ->addValue('Participant_Webform.Url', $url)
            ->execute();
        }
      }
    }
  }
}