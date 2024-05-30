<?php

namespace Drupal\chat\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'chat_block',
  admin_label: new TranslatableMarkup('Chat integration block'),
  category: new TranslatableMarkup('LLM')
)]
class ChatBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new MyCustomBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param LLModelProviderManager $providerManager
   *   LLM provider manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LLModelProviderManager $providerManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.llm_services')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    return [
      '#theme' => 'chat_block',
      '#provider' => $this->configuration['providerName'],
      '#models' => $this->configuration['models'],
      '#attached' => [
        'library' => [
          'chat/chat'
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'providerName' => 'ollama',
      'models' => [],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\llm_services\Exceptions\CommunicationException
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $plugins = $this->providerManager->getDefinitions();
    $options = array_map(function ($plugin) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $plugin['title'];
      return $title->render();
    }, $plugins);

    $form['chat'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Chat provider configuration'),
    ];

    $form['chat']['providerName'] = [
      '#type' => 'select',
      '#title' => $this->t('Model provider'),
      '#description' => $this->t('Select the provider of models. Note if changed please save and edit this block once more to update model list below.'),
      '#options' => $options,
      '#default_value' => $this->configuration['providerName'],
    ];

    $provider = $this->providerManager->createInstance($this->configuration['providerName']);
    $models = $provider->listModels();
    $options = array_map(function ($model) {
      return sprintf('%s (%s)', $model['name'], $model['modified']);
    }, $models);
    ksort($options);

    $form['chat']['models'] = [
      '#type' => 'select',
      '#title' => $this->t('Models'),
      '#description' => $this->t('Select the models this chat should use'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['models'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->configuration['providerName'] = $values['chat']['providerName'];
    $this->configuration['models'] = $values['chat']['models'];
  }

}
