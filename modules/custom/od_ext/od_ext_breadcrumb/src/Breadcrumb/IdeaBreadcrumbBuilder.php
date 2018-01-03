<?php

namespace Drupal\od_ext_breadcrumb\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\system\PathBasedBreadcrumbBuilder;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

class IdeaBreadcrumbBuilder extends PathBasedBreadcrumbBuilder {

  use StringTranslationTrait;

  /**
   * The router request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $context;

  /**
   * The menu link access service.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The dynamic router service.
   *
   * @var \Symfony\Component\Routing\Matcher\RequestMatcherInterface
   */
  protected $router;

  /**
   * The inbound path processor.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * Site config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The language manager de.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * The alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs the IdeaBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Routing\RequestContext $context
   *   The router request context.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The menu link access service.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The dynamic router service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Path\PathValidator $pathValidator
   *   The path validator.
   * @param \Drupal\Core\Path\AliasManager $alias_manager
   *   The alias manager.
   */
  public function __construct(
    RequestContext $context,
    AccessManagerInterface $access_manager,
    RequestMatcherInterface $router,
    InboundPathProcessorInterface $path_processor,
    ConfigFactoryInterface $config_factory,
    TitleResolverInterface $title_resolver,
    AccountInterface $current_user,
    CurrentPathStack $current_path,
    LanguageManagerInterface $language_manager,
    PathValidator $pathValidator,
    AliasManagerInterface $alias_manager) {
    $this->context = $context;
    $this->accessManager = $access_manager;
    $this->router = $router;
    $this->pathProcessor = $path_processor;
    $this->config = $config_factory->get('system.site');
    $this->titleResolver = $title_resolver;
    $this->currentUser = $current_user;
    $this->currentPath = $current_path;
    $this->languageManager = $language_manager;
    $this->pathValidator = $pathValidator;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $parameters = $route_match->getParameters()->all();

    // Content type determination.
    if (!empty($parameters['node']) &&
        is_object($parameters['node']) &&
        $parameters['node']->getType() == 'idea') {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $links = [];

    // General path-based breadcrumbs. Use the actual request path, prior to
    // resolving path aliases, so the breadcrumb can be defined by simply
    // creating a hierarchy of path aliases.
    $path = trim($this->context->getPathInfo(), '/');
    $path_elements = explode('/', $path);

    $links[] = Link::createFromRoute($this->t('Home'), '<front>');
    $breadcrumb->setLinks(array_reverse($links));

    $route = $route_match->getRouteObject();
    if ($route && !$route->getOption('_admin_route')) {
      $links = $breadcrumb->getLinks();
      if (!empty($links) && $links[0]->getText() == $this->t('Home')) {
        $url = 'https://www.canada.ca/en.html';
        if ($this->languageManager->getCurrentLanguage()->getId() == 'fr') {
          $url = 'https://www.canada.ca/fr.html';
        }
        $link = array_shift($links);
        $link->setUrl(Url::fromUri($url));

        $nid = $this->aliasManager->getPathByAlias('/open-dialogue', 'en');
        $open_dialogue = $this->pathValidator->getUrlIfValid($nid);
        $nid = $this->aliasManager->getPathByAlias('/consultations', 'en');
        $consultation = $this->pathValidator->getUrlIfValid($nid);
        if (!empty($open_dialogue) && !empty($consultation)) {
          $linkOpenGov = Link::createFromRoute($this->t('Open Government'), '<front>');
          $linkOpenDialogue = Link::createFromRoute($this->t('Open Dialogue'), $open_dialogue->getRouteName(), $open_dialogue->getRouteParameters());
          $linkConsultation = Link::createFromRoute($this->t('Consultations'), $consultation->getRouteName(), $consultation->getRouteParameters());
          $pathEnd = end($path_elements);
          if (!empty($pathEnd) && $pathEnd != 'consultations') {
            array_unshift($links, $link, $linkOpenGov, $linkOpenDialogue, $linkConsultation);
          }
          else {
            array_unshift($links, $link, $linkOpenGov, $linkOpenDialogue);
          }
        }
      }

      $breadcrumb = new Breadcrumb();
      $breadcrumb->addCacheContexts(['url.path.parent']);
      $breadcrumb->setLinks($links);
    }

    return $breadcrumb;
  }

}
