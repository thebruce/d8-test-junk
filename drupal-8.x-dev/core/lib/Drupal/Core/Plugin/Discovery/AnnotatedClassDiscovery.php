<?php

/**
 * @file
 * Definition of Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery.
 */

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\AnnotatedClassDiscovery as ComponentAnnotatedClassDiscovery;

/**
 * Defines a discovery mechanism to find annotated plugins in PSR-0 namespaces.
 */
class AnnotatedClassDiscovery extends ComponentAnnotatedClassDiscovery {

  /**
   * The module name that defines the plugin type.
   *
   * @var string
   */
  protected $owner;

  /**
   * The plugin type, for example filter.
   *
   * @var string
   */
  protected $type;

  /**
   * An object containing the namespaces to look for plugin implementations.
   *
   * @var \Traversable
   */
  protected $rootNamespacesIterator;

  /**
   * Constructs an AnnotatedClassDiscovery object.
   *
   * @param string $subdir
   *   The plugin's subdirectory, for example views/filter.
   * @param \Traversable $root_namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,
   *   \Plugin\$subdir will be appended to each namespace.
   * @param array $annotation_namespaces
   *   (optional) The namespaces of classes that can be used as annotations.
   *   Defaults to an empty array.
   * @param string $plugin_definition_annotation_name
   *   (optional) The name of the annotation that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Annotation\Plugin'.
   */
  function __construct($subdir, \Traversable $root_namespaces, $annotation_namespaces = array(), $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin') {
    $this->subdir = str_replace('/', '\\', $subdir);
    $this->rootNamespacesIterator = $root_namespaces;
    $annotation_namespaces += array(
      'Drupal\Component\Annotation' => DRUPAL_ROOT . '/core/lib',
      'Drupal\Core\Annotation' => DRUPAL_ROOT . '/core/lib',
    );
    $plugin_namespaces = array();
    parent::__construct($plugin_namespaces, $annotation_namespaces, $plugin_definition_annotation_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    foreach ($definitions as &$definition) {
      // Extract the module name from the class namespace if it's not set.
      if (!isset($definition['provider'])) {
        $definition['provider'] = $this->getProviderFromNamespace($definition['class']);
      }
    }
    return $definitions;
  }

  /**
   * Extracts the provider name from a Drupal namespace.
   *
   * @param string $namespace
   *   The namespace to extract the provider from.
   *
   * @return string|null
   *   The matching provider name, or NULL otherwise.
   */
  protected function getProviderFromNamespace($namespace) {
    preg_match('|^Drupal\\\\(?<provider>[\w]+)\\\\|', $namespace, $matches);

    if (isset($matches['provider'])) {
      return $matches['provider'];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginNamespaces() {
    $plugin_namespaces = array();
    foreach ($this->rootNamespacesIterator as $namespace => $dir) {
      $plugin_namespaces["$namespace\\Plugin\\{$this->subdir}"] = array($dir);
    }

    return $plugin_namespaces;
  }

}
