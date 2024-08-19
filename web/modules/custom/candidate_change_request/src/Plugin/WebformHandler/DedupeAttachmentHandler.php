<?php
namespace Drupal\candidate_change_request\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Clin Add to Waitlist Handler Plugin.
 *
 * @WebformHandler(
 *   id = "dedupe_attachments",
 *   label = @Translation("Dedupe Attachments"),
 *   category = @Translation("CRM"),
 *   description = @Translation("Deletes Drupal attachments, leaving only the CiviCRM files"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class DedupeAttachmentHandler extends WebformHandlerBase {

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

  public function postsave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->civicrm->initialize();
    $webform_submission_data = $webform_submission->getData();
    if (!empty($webform_submission_data['civicrm_1_activity_1_activityupload_file_1'])) {
      $fid = $webform_submission_data['civicrm_1_activity_1_activityupload_file_1'];
      if (!empty($fid)) {
        $file = File::load($fid);
        $file->delete();
      }
    }
  }
}