<?php

namespace Drupal\islandora_install_profile_demo_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity reference ID' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_url_title",
 *   label = @Translation("Children Entity Count, Label."),
 *   description = @Translation("Children Entity Count, Label."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceCountFormatter extends EntityReferenceFormatterBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'label' => 'Items in Collection',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['separator'] = [
      '#title' => t('Text to appear next to the children\'s count'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('label'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->getSetting('label') ? 'Label : ' . $this->getSetting('label') : t('No label');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    global $base_url;
    $total_items =  count($this->getEntitiesToView($items, $langcode));
    $element[] = ['#markup' => "<span class='collection_children__total_count'>{$total_items}<span>" . ' ' . $this->getSetting('label')];
    return $element;
  }

}
