<?php

namespace Drupal\od_bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Plugin\Preprocess\PreprocessBase;

/**
 * Pre-processes variables for the "views_view_table" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("views_view_table")
 */
class ViewsViewTable extends PreprocessBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, $hook, array $info) {

    // Language Handling.
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $language_prefix = \Drupal::config('language.negotiation')->get('url.prefixes');
    $variables['language'] = $language;
    $variables['language_prefix'] = $language_prefix[$language];

    parent::preprocess($variables, $hook, $info);
  }

}
