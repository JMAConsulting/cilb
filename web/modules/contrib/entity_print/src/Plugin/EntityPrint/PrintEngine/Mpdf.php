<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

use Drupal\Core\Form\FormStateInterface;
use Mpdf\Mpdf as MpdfLib;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_print\Plugin\ExportTypeInterface;
use Mpdf\Output\Destination;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Entity Print plugin for the mPDF library.
 *
 * @PrintEngine(
 *   id = "mpdf",
 *   label = @Translation("Mpdf"),
 *   export_type = "pdf"
 * )
 */
class Mpdf extends PdfEngineBase implements ContainerFactoryPluginInterface {

  const PORTRAIT = 'P';
  const LANDSCAPE = 'L';

  /**
   * The Mpdf instance.
   *
   * @var \Mpdf\Mpdf
   */
  protected $mpdf;

  /**
   * Keep track of HTML pages as they're added.
   *
   * @var string
   */
  protected $html = '';

  /**
   * Keep track of whether we've rendered or not.
   *
   * @var bool
   */
  protected $hasRendered;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeInterface $export_type) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $export_type);

    $format = $this->configuration['default_paper_size'];
    if ($this->configuration['custom_paper_size']) {
      $format = [
        $this->configuration['custom_paper_width'],
        $this->configuration['custom_paper_height'],
      ];
    }

    $config = [
      'mode' => $this->configuration['mode'] ?? '+aCJK',
      'format' => $this->configuration['format'] ?? $format,
      'autoScriptToLang' => $this->configuration['autoScriptToLang'] ?? true,
      'autoLangToFont' => $this->configuration['autoLangToFont'] ?? true,
      'tempDir' => \Drupal::service('file_system')->getTempDirectory(),
      'default_font_size' => $this->configuration['default_font_size'] ?? 0,
      'default_font' => $this->configuration['default_font'] ?? '',
    ];

    $config += $this->configuration;

    $this->mpdf = new MpdfLib($config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type')->createInstance($plugin_definition['export_type']),
      $container->get('request_stack')->getCurrentRequest()
    );
  }
  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Paper Orientation'),
      '#options' => [
        static::PORTRAIT => $this->t('Portrait'),
        static::LANDSCAPE => $this->t('Landscape'),
      ],
      '#description' => $this->t('The paper orientation one of Landscape or Portrait'),
      '#default_value' => $this->configuration['orientation'],
      '#weight' => -9,
    ];

    $form['custom_paper_size'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Custom Paper Size'),
      '#description' => $this->t('Use a custom paper size'),
      '#default_value' => $this->configuration['custom_paper_size'],
    ];

    $form['custom_paper_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Paper Width'),
      '#description' => $this->t('The paper width in mm'),
      '#default_value' => $this->configuration['custom_paper_width'],
      '#min' => 0,
      '#step' => 1,
      '#states' => [
        'visible' => [
          ':input[name="mpdf[custom_paper_size]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['custom_paper_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Paper Height'),
      '#description' => $this->t('The paper height in mm'),
      '#default_value' => $this->configuration['custom_paper_height'],
      '#min' => 0,
      '#step' => 1,
      '#states' => [
        'visible' => [
          ':input[name="mpdf[custom_paper_size]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['default_paper_size']['#states'] = [
      'disabled' => [
        ':input[name="mpdf[custom_paper_size]"]' => ['checked' => TRUE],
      ],
    ];

    $form['margins'] = [
      '#type' => 'details',
      '#title' => $this->t('Margins'),
      '#open' => FALSE,
      'margin_left' => [
        '#type' => 'number',
        '#title' => $this->t('Left'),
        '#default_value' => $this->configuration['margin_left'],
        '#min' => 0,
        '#step' => 1,
      ],
      'margin_right' => [
        '#type' => 'number',
        '#title' => $this->t('Right'),
        '#default_value' => $this->configuration['margin_right'],
        '#min' => 0,
        '#step' => 1,
      ],
      'margin_top' => [
        '#type' => 'number',
        '#title' => $this->t('Top'),
        '#default_value' => $this->configuration['margin_top'],
        '#min' => 0,
        '#step' => 1,
      ],
      'margin_bottom' => [
        '#type' => 'number',
        '#title' => $this->t('Bottom'),
        '#default_value' => $this->configuration['margin_bottom'],
        '#min' => 0,
        '#step' => 1,
      ],
      'margin_header' => [
        '#type' => 'number',
        '#title' => $this->t('Header'),
        '#default_value' => $this->configuration['margin_header'],
        '#min' => 0,
        '#step' => 1,
      ],
      'margin_footer' => [
        '#type' => 'number',
        '#title' => $this->t('Footer'),
        '#default_value' => $this->configuration['margin_footer'],
        '#min' => 0,
        '#step' => 1,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'orientation' => static::PORTRAIT,
      'default_paper_size' => 'LETTER',
      'custom_paper_size' => FALSE,
      'custom_paper_width' => 0,
      'custom_paper_height' => 0,
      'margin_left' => 15,
      'margin_right' => 15,
      'margin_top' => 16,
      'margin_bottom' => 16,
      'margin_header' => 9,
      'margin_footer' => 9,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions() {
    return t('Please install with: @command', ['@command' => 'composer require "mpdf/mpdf:^8.0"']);
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    $this->html .= (string) $content;
    $this->mpdf->WriteHTML($this->html);
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    $this->mpdf->Output($filename, $force_download ? Destination::DOWNLOAD : Destination::INLINE);
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return $this->mpdf->Output('', 'S');
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return class_exists('Mpdf\Mpdf') && !drupal_valid_test_ua();
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {
    return $this->mpdf;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaperSizes() {
    return $formats = [
      '4A0' => '4A0',
      '2A0' => '2A0',
      'A0' => 'A0',
      'A1' => 'A1',
      'A2' => 'A2',
      'A3' => 'A3',
      'A4' => 'A4',
      'A5' => 'A5',
      'A6' => 'A6',
      'A7' => 'A7',
      'A8' => 'A8',
      'A9' => 'A9',
      'A10' => 'A10',
      'B0' => 'B0',
      'B1' => 'B1',
      'B2' => 'B2',
      'B3' => 'B3',
      'B4' => 'B4',
      'B5' => 'B5',
      'B6' => 'B6',
      'B7' => 'B7',
      'B8' => 'B8',
      'B9' => 'B9',
      'B10' => 'B10',
      'C0' => 'C0',
      'C1' => 'C1',
      'C2' => 'C2',
      'C3' => 'C3',
      'C4' => 'C4',
      'C5' => 'C5',
      'C6' => 'C6',
      'C7' => 'C7',
      'C8' => 'C8',
      'C9' => 'C9',
      'C10' => 'C10',
      'RA0' => 'RA0',
      'RA1' => 'RA1',
      'RA2' => 'RA2',
      'RA3' => 'RA3',
      'RA4' => 'RA4',
      'SRA0' => 'SRA0',
      'SRA1' => 'SRA1',
      'SRA2' => 'SRA2',
      'SRA3' => 'SRA3',
      'SRA4' => 'SRA4',
      'LETTER' => 'LETTER',
      'LEGAL' => 'LEGAL',
      'LEDGER' => 'LEDGER',
      'TABLOID' => 'TABLOID',
      'EXECUTIVE' => 'EXECUTIVE',
      'FOLIO' => 'FOLIO',
      'B' => 'B', // 'B' format paperback size 128x198mm
      'A' => 'A', // 'A' format paperback size 111x178mm
      'DEMY' => 'DEMY', // 'Demy' format paperback size 135x216mm
      'ROYAL' => 'ROYAL', // 'Royal' format paperback size 153x234mm
    ];
  }
}
