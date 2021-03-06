<?php

/**
 * @file
 * Contains Drupal\rdf\Tests\UserAttributesTest.
 */

namespace Drupal\rdf\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the RDFa markup of Users.
 */
class UserAttributesTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('rdf');

  public static function getInfo() {
    return array(
      'name' => 'RDFa markup for users',
      'description' => 'Tests the RDFa markup of users.',
      'group' => 'RDF',
    );
  }

  public function setUp() {
    parent::setUp();
    rdf_get_mapping('user', 'user')
      ->setBundleMapping(array(
        'types' => array('sioc:UserAccount'),
      ))
      ->setFieldMapping('name', array(
        'properties' => array('foaf:name'),
      ))
      ->save();
  }

  /**
   * Tests if default mapping for user is being used.
   *
   * Creates a random user and ensures the default mapping for the user is
   * being used.
   */
  function testUserAttributesInMarkup() {
    // Creates two users, one with access to user profiles.
    $user1 = $this->drupalCreateUser(array('access user profiles'));
    $user2 = $this->drupalCreateUser();
    $this->drupalLogin($user1);

    $account_uri = url('user/' . $user2->id(), array('absolute' => TRUE));
    $person_uri = url('user/' . $user2->id(), array('fragment' => 'me', 'absolute' => TRUE));

    // Parses the user profile page where the default bundle mapping for user
    // should be used.
    $parser = new \EasyRdf_Parser_Rdfa();
    $graph = new \EasyRdf_Graph();
    $base_uri = url('<front>', array('absolute' => TRUE));
    $parser->parse($graph, $this->drupalGet('user/' . $user2->id()), 'rdfa', $base_uri);

    // Inspects RDF graph output.
    // User type.
    $expected_value = array(
      'type' => 'uri',
      'value' => 'http://rdfs.org/sioc/ns#UserAccount',
    );
    $this->assertTrue($graph->hasProperty($account_uri, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $expected_value), 'User type found in RDF output (sioc:UserAccount).');
    // User name.
    $expected_value = array(
      'type' => 'literal',
      'value' => $user2->name,
    );
    $this->assertTrue($graph->hasProperty($account_uri, 'http://xmlns.com/foaf/0.1/name', $expected_value), 'User name found in RDF output (foaf:name).');

    // User 2 creates a node.
    $this->drupalLogin($user2);
    $node = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $this->drupalLogin($user1);

    // Parses the user profile page where the default bundle mapping for user
    // should be used.
    $parser = new \EasyRdf_Parser_Rdfa();
    $graph = new \EasyRdf_Graph();
    $base_uri = url('<front>', array('absolute' => TRUE));
    $parser->parse($graph, $this->drupalGet('node/' . $node->nid), 'rdfa', $base_uri);

    // Ensures the default bundle mapping for user is used on the Authored By
    // information on the node.
    // User type.
    $expected_value = array(
      'type' => 'uri',
      'value' => 'http://rdfs.org/sioc/ns#UserAccount',
    );
    $this->assertTrue($graph->hasProperty($account_uri, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $expected_value), 'User type found in RDF output (sioc:UserAccount).');
    // User name.
    $expected_value = array(
      'type' => 'literal',
      'value' => $user2->name,
    );
    $this->assertTrue($graph->hasProperty($account_uri, 'http://xmlns.com/foaf/0.1/name', $expected_value), 'User name found in RDF output (foaf:name).');
  }
}
