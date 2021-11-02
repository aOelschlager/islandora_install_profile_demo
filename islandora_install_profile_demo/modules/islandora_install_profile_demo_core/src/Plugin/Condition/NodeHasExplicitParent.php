<?php

namespace Drupal\islandora_install_profile_demo_core\Plugin\Condition;

use Drupal\Core\Condition\Annotation\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_embed\Plugin\EmbedType\Entity;
use Drupal\islandora\IslandoraUtils;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a condition to detect node's parent.
 *
 * @Condition(
 *   id = "node_has_implicit_parent",
 *   label = @Translation("Node has parent (implicit)"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE, label = @Translation("Node")),
 *   }
 * )
 */
class NodeHasExplicitParent extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Islandora utils.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;


  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\islandora\IslandoraUtils $utils
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IslandoraUtils $utils,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->utils = $utils;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'parent_reference_field' => 'field_member_of',
      'model_uri' => NULL,
      'logic' => 'and'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('islandora.utils'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    $node_fields = array_keys($field_map['node']);
    $options = array_combine($node_fields, $node_fields);
    $uri_default = [];
    $model_default = [];

    $defaults = ['uri' => "uri_default", 'model_uri' => 'model_default'];
    foreach ($defaults as $field => $result_variable) {
      if (isset($this->configuration[$field]) && !empty($this->configuration[$field])) {
        $uris = explode(',', $this->configuration[$field]);
        foreach ($uris as $uri) {
          ${$result_variable}[] = $this->utils->getTermForUri($uri);
        }
      }
    }


    $form['parent_reference_field'] = [
      '#type' => 'select',
      '#title' => t('Field that contains reference to parents'),
      '#options' => $options,
      '#default_value' => $this->configuration['parent_reference_field'],
      '#required' => FALSE,
      '#description' => t("Machine name of field that contains references to parent node."),
    ];

    $form['term_resource_type'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Parent taxonomy term(s) reference with URI (resource type)'),
      '#description' => $this->t('Only terms that have external URIs/URLs will appear here.'),
      '#tags' => TRUE,
      '#default_value' => $uri_default,
      '#target_type' => 'taxonomy_term',
      '#required' => FALSE,
      '#selection_handler' => 'islandora:external_uri',
      '#selection_settings' => [
        'target_bundles' => array('resource_types'),
      ],

    ];


    $form['term_model'] = array(
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Parent taxonomy term(s) reference with URI (model)'),
      '#default_value' => $model_default,
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'islandora:external_uri',
      '#selection_settings' => [
        'target_bundles' => array('islandora_models'),
      ],
      '#required' => FALSE,
      '#tags' => TRUE,
    );

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Set URI for term if possible.
    $defaults = ['uri' => "term_resource_type", 'model_uri' => 'term_model'];
    foreach ($defaults as $field => $result_variable) {
      $this->configuration[$field] = NULL;
      $value = $form_state->getValue($result_variable);
      $uris = [];
      if (!empty($value)) {
        foreach ($value as $target) {
          $tid = $target['target_id'];
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
          $uri = $this->utils->getUriForTerm($term);
          if ($uri) {
            $uris[] = $uri;
          }
        }
        if (!empty($uris)) {
          $this->configuration[$field] = implode(',', $uris);
        }
      }
    }
    $this->configuration['parent_reference_field'] = $form_state->getValue('parent_reference_field');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *  @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function evaluate() {
     $entity = $this->getContextValue('node');
    if ((empty($this->configuration['uri']) && !$this->isNegated()) || (empty($this->configuration['model_uri']) && !$this->isNegated())) {
      return TRUE;
    }

    // All checks failed. Stop.
    if (!$entity) {
      return FALSE;
    }

    return $this->evaluateEntity($entity);
  }

  /**
   * Evaluates if an entity has the specified node as its parent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to evaluate.
   *
   * @return bool
   *   TRUE if entity references the specified parent.
   */
  protected function evaluateEntity(EntityInterface $entity) {
    foreach ($entity->referencedEntities() as $referenced_entity) {
      if ($entity->getEntityTypeID() == 'node' && $referenced_entity->getEntityTypeId() == 'node') {

        $parent_reference_field = $this->configuration['parent_reference_field'];
        $field = $entity->get($parent_reference_field);
        if (!$field->isEmpty()) {
          $id = $field->getValue();
          // Evaluate if NID is a valid Islandora Object
          $entity_object  = Node::load($id[0]['target_id']);
          // If the NID is valid and is an islandora object

          $field_names = $this->utils->getUriFieldNamesForTerms();
          $terms = array_filter($entity_object->referencedEntities(), function ($entity) use ($field_names) {
            if ($entity->getEntityTypeId() != 'taxonomy_term') {
              return FALSE;
            }

            foreach ($field_names as $field_name) {
              if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
                return TRUE;
              }
            }
            return FALSE;
          });

          // Get their URIs.
          $haystack = array_map(function ($term) {
            return $this->utils->getUriForTerm($term);
          },
            $terms
          );

          if (empty($haystack)) {
            return FALSE;
          }
          $needles = explode(',', $this->configuration['uri']);
          // TRUE if every needle is in the haystack.
            if (count(array_intersect($needles, $haystack)) > 0) {
              foreach ($referenced_entity->referencedEntities() as $_referenced_entity) {
                // If configuration['tid'] is an array with multiple terms, check all
                // tids in the array against the term.
                if(is_array($this->configuration['model_uri'])) {
                  if ($_referenced_entity->getEntityTypeId() == 'taxonomy_term' && in_array($this->utils->getUriForTerm($_referenced_entity), array_column($this->configuration['model_uri'], 'target_id'))) {
                    return $entity_object && $entity_object->bundle() === 'islandora_object';
                  }
                }
                else {
                  if ($_referenced_entity->getEntityTypeId() == 'taxonomy_term' && $this->utils->getUriForTerm($_referenced_entity) == $this->configuration['model_uri']) {
                    return $entity_object && $entity_object->bundle() === 'islandora_object';
                  }
                }
              }
            }
            return FALSE;
          }
        }
      }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (!empty($this->configuration['negate'])) {
      return $this->t('The node does not have node @nid as its parent.', ['@nid' => $this->configuration['parent_nid']]);
    }
    else {
      return $this->t('The node has node @nid as its parent.', ['@nid' => $this->configuration['parent_nid']]);
    }
  }

}
