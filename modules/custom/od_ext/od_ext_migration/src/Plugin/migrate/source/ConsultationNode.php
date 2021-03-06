<?php

namespace Drupal\od_ext_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for consultation content.
 *
 * @MigrateSource(
 *   id = "consultation_node"
 * )
 */
class ConsultationNode extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('node', 'n')
      ->fields('n',
      [
        'nid',
        'vid',
        'language',
        'title',
        'uid',
        'created',
        'changed',
        'status',
        'comment',
      ])
      ->condition('n.type', 'consultation');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'vid' => $this->t('Revision ID'),
      'language' => $this->t('Language'),
      'title' => $this->t('Title'),
      'uid' => $this->t('User ID'),
      'body' => $this->t('Body'),
      'created' => $this->t('Created'),
      'changed' => $this->t('Changed'),
      'comment' => $this->t('Comment'),
      'status' => $this->t('Status'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    // Translation support.
    if (!empty($row->getSourceProperty('translations'))) {
      $row->setSourceProperty('language', 'fr');
    }

    // Title Field.
    $title = $this->select('field_data_title_field', 'db')
      ->fields('db', ['title_field_value'])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('revision_id', $row->getSourceProperty('vid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->condition('bundle', 'consultation')
      ->execute()
      ->fetchCol();

    // Body.
    $body = $this->select('field_data_body', 'db')
      ->fields('db', ['body_value'])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('revision_id', $row->getSourceProperty('vid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->condition('bundle', 'consultation')
      ->execute()
      ->fetchCol();

    // Start Date.
    $date_start = $this->select('field_data_field_start_date', 'df')
      ->fields('df', ['field_start_date_value'])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('revision_id', $row->getSourceProperty('vid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->condition('bundle', 'consultation')
      ->execute()
      ->fetchCol();

    // End Date.
    $date_end = $this->select('field_data_field_end_date', 'df')
      ->fields('df', ['field_end_date_value'])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('revision_id', $row->getSourceProperty('vid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->condition('bundle', 'consultation')
      ->execute()
      ->fetchCol();

    // Consultation Status.
    $consult_status = $this->select('field_data_field_consultation_status', 'df')
      ->fields('df', ['field_consultation_status_tid'])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('revision_id', $row->getSourceProperty('vid'))
      ->condition('language', 'und')
      ->condition('bundle', 'consultation')
      ->execute()
      ->fetchCol();

    // Node Idea.
    $node_idea = $this->select('field_data_field_consultation', 'df')
      ->fields('df', ['entity_id'])
      ->condition('field_consultation_target_id', $row->getSourceProperty('nid'))
      ->condition('bundle', 'idea')
      ->execute()
      ->fetchAllAssoc('entity_id');

    // URL alias.
    $alias = $this->select('url_alias', 'db')
      ->fields('db', ['alias'])
      ->condition('source', 'node/' . $row->getSourceProperty('nid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->execute()
      ->fetchCol();

    // Metatags.
    $metatags = $this->select('metatag', 'df')
      ->fields('df', [
        'data',
      ])
      ->condition('entity_id', $row->getSourceProperty('nid'))
      ->condition('language', $row->getSourceProperty('language'))
      ->condition('entity_type', 'node')
      ->execute()
      ->fetchAssoc();
    $tmp = unserialize($metatags['data']);
    $metatags = [
      'title' => isset($tmp['title']['value']) ? $tmp['title']['value'] : '[current-page:title] | [site:name]',
      'description' => isset($tmp['description']['value']) ? $tmp['description']['value'] : '[node:summary]',
      'keywords' => isset($tmp['keywords']['value']) ? $tmp['keywords']['value'] : '',
    ];

    if (!empty($title[0])) {
      $row->setSourceProperty('title', $title[0]);
    }
    elseif (!empty($row->getSourceProperty('translations'))) {
      return FALSE;
    }
    $row->setSourceProperty('body', $body[0]);
    $row->setSourceProperty('consult_status', $consult_status[0]);
    $row->setSourceProperty('date_start', $date_start[0]);
    $row->setSourceProperty('date_end', $date_end[0]);
    $row->setSourceProperty('node_idea', $node_idea);
    $row->setSourceProperty('metatags', serialize($metatags));

    if (!empty($alias)) {
      $row->setSourceProperty('alias', '/' . end($alias));
    }

    return parent::prepareRow($row);
  }

}
