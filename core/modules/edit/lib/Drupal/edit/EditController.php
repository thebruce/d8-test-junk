<?php

/**
 * @file
 * Contains of \Drupal\edit\EditController.
 */

namespace Drupal\edit;

use Drupal;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\edit\Ajax\FieldFormCommand;
use Drupal\edit\Ajax\FieldFormSavedCommand;
use Drupal\edit\Ajax\FieldFormValidationErrorsCommand;
use Drupal\edit\Ajax\EntitySavedCommand;
use Drupal\edit\Ajax\MetadataCommand;
use Drupal\user\TempStoreFactory;

/**
 * Returns responses for Edit module routes.
 */
class EditController extends ContainerAware {

  /**
   * Stores the tempstore factory.
   *
   * @var \Drupal\user\TempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a new EditController.
   *
   * @param \Drupal\user\TempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(TempStoreFactory $temp_store_factory = NULL) {
    $this->tempStoreFactory = $temp_store_factory ?: Drupal::service('user.tempstore');
  }

  /**
   * Returns the metadata for a set of fields.
   *
   * Given a list of field edit IDs as POST parameters, run access checks on the
   * entity and field level to determine whether the current user may edit them.
   * Also retrieves other metadata.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function metadata(Request $request) {
    $fields = $request->request->get('fields');
    if (!isset($fields)) {
      throw new NotFoundHttpException();
    }
    $entities = $request->request->get('entities');

    $metadataGenerator = $this->container->get('edit.metadata.generator');

    $metadata = array();
    foreach ($fields as $field) {
      list($entity_type, $entity_id, $field_name, $langcode, $view_mode) = explode('/', $field);

      // Load the entity.
      if (!$entity_type || !entity_get_info($entity_type)) {
        throw new NotFoundHttpException();
      }
      $entity = entity_load($entity_type, $entity_id);
      if (!$entity) {
        throw new NotFoundHttpException();
      }

      // Validate the field name and language.
      if (!$field_name || !($instance = field_info_instance($entity->entityType(), $field_name, $entity->bundle()))) {
        throw new NotFoundHttpException();
      }
      if (!$langcode || (field_valid_language($langcode) !== $langcode)) {
        throw new NotFoundHttpException();
      }

      // If the entity information for this field is requested, include it.
      $entity_id = $entity->entityType() . '/' . $entity_id;
      if (is_array($entities) && in_array($entity_id, $entities) && !isset($metadata[$entity_id])) {
        $metadata[$entity_id] = $metadataGenerator->generateEntity($entity, $langcode);
      }

      $metadata[$field] = $metadataGenerator->generateField($entity, $instance, $langcode, $view_mode);
    }

    return new JsonResponse($metadata);
  }

  /**
   * Returns AJAX commands to load in-place editors' attachments.
   *
   * Given a list of in-place editor IDs as POST parameters, render AJAX
   * commands to load those in-place editors.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function attachments(Request $request) {
    $response = new AjaxResponse();
    $editors = $request->request->get('editors');
    if (!isset($editors)) {
      throw new NotFoundHttpException();
    }

    $editorSelector = $this->container->get('edit.editor.selector');
    $elements['#attached'] = $editorSelector->getEditorAttachments($editors);
    drupal_process_attached($elements);

    return $response;
  }

  /**
   * Returns a single field edit form as an Ajax response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   * @param string $field_name
   *   The name of the field that is being edited.
   * @param string $langcode
   *   The name of the language for which the field is being edited.
   * @param string $view_mode_id
   *   The view mode the field should be rerendered in.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function fieldForm(EntityInterface $entity, $field_name, $langcode, $view_mode_id, Request $request) {
    $response = new AjaxResponse();

    // Replace entity with tempstore copy if available and not resetting, init
    // tempstore copy otherwise.
    $tempstore_entity = $this->tempStoreFactory->get('edit')->get($entity->uuid());
    if ($tempstore_entity && !(isset($_POST['reset']) && $_POST['reset'] === 'true')) {
      $entity = $tempstore_entity;
    }
    else {
      $this->tempStoreFactory->get('edit')->set($entity->uuid(), $entity);
    }

    $form_state = array(
      'langcode' => $langcode,
      'no_redirect' => TRUE,
      'build_info' => array('args' => array($entity, $field_name, $this->tempStoreFactory)),
    );
    $form = drupal_build_form('edit_field_form', $form_state);

    if (!empty($form_state['executed'])) {
      // The form submission saved the entity in tempstore. Return the
      // updated view of the field from the tempstore copy.
      $entity = $this->tempStoreFactory->get('edit')->get($entity->uuid());
      // @todo Remove when http://drupal.org/node/1346214 is complete.
      $entity = $entity->getBCEntity();
      $output = field_view_field($entity, $field_name, $view_mode_id, $langcode);

      $response->addCommand(new FieldFormSavedCommand(drupal_render($output)));
    }
    else {
      $response->addCommand(new FieldFormCommand(drupal_render($form)));

      $errors = form_get_errors();
      if (count($errors)) {
        $status_messages = array(
          '#theme' => 'status_messages'
        );
        $response->addCommand(new FieldFormValidationErrorsCommand(drupal_render($status_messages)));
      }
    }

    // When working with a hidden form, we don't want any CSS or JS to be loaded.
    if ($request->request->get('nocssjs') === 'true') {
      drupal_static_reset('drupal_add_css');
      drupal_static_reset('drupal_add_js');
    }

    return $response;
  }

  /**
   * Saves an entity into the database, from TempStore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   */
  public function entitySave(EntityInterface $entity) {
    // Take the entity from tempstore and save in entity storage. fieldForm()
    // ensures that the tempstore copy exists ahead.
    $tempstore = $this->tempStoreFactory->get('edit');
    $tempstore->get($entity->uuid())->save();
    $tempstore->delete($entity->uuid());

    // Return information about the entity that allows a front end application
    // to identify it.
    $output = array(
      'entity_type' => $entity->entityType(),
      'entity_id' => $entity->id()
    );

    // Respond to client that the entity was saved properly.
    $response = new AjaxResponse();
    $response->addCommand(new EntitySavedCommand($output));
    return $response;
  }

}
