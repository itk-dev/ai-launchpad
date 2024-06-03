<?php

namespace Drupal\chat\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines block to display chat interface.
 */
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
   * @param Drupal\llm_services\Plugin\LLModelProviderManager $providerManager
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
      '#provider_name' => $this->configuration['provider_name'],
      '#models' => $this->configuration['models'],
      '#system_prompt' => $this->configuration['system_prompt'],
      '#temperature' => $this->configuration['temperature'],
      '#top_k' => $this->configuration['top_k'],
      '#top_p' => $this->configuration['top_p'],
      '#attached' => [
        'library' => [
          'chat/chat',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'provider_name' => 'ollama',
      'models' => [],
      'system_prompt' => 'Use the following pieces of context to answer the users question. If you don\'t know the answer, just say that you don\'t know, don\'t try to make up an answer.',
      'temperature' => 0.8,
      'top_k' => 40,
      'top_p' => 0.9,
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
      '#type' => 'details',
      '#title' => $this->t('Chat provider configuration'),
      '#open' => TRUE,
    ];

    $form['chat']['provider_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Model provider'),
      '#description' => $this->t('Select the provider of models. Note if changed please save and edit this block once more to update model list below.'),
      '#options' => $options,
      '#default_value' => $this->configuration['provider_name'],
      '#required' => TRUE,
    ];

    $provider = $this->providerManager->createInstance($this->configuration['provider_name']);
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
      '#required' => TRUE,
    ];

    $form['chat']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#description' => $this->t('System message to instruct the llm have to behave.'),
      '#default_value' => $this->configuration['system_prompt'],
      '#required' => TRUE,
    ];

    $form['tune'] = [
      '#type' => 'details',
      '#title' => $this->t('Fine-tune the chat'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['tune']['temperature'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Temperature'),
      '#description' => $this->t('The temperature of the model. Increasing the temperature will make the model answer more creatively.'),
      '#default_value' => $this->configuration['temperature'],
    ];

    $form['tune']['top_k'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top k'),
      '#description' => $this->t('Reduces the probability of generating nonsense. A higher value (e.g. 100) will give more diverse answers.'),
      '#default_value' => $this->configuration['top_k'],
    ];

    $form['tune']['top_p'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top p'),
      '#description' => $this->t('A higher value (e.g., 0.95) will lead to more diverse text, while a lower value (e.g., 0.5) will generate more focused and conservative text.'),
      '#default_value' => $this->configuration['top_p'],
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
    $this->configuration['provider_name'] = $values['chat']['provider_name'];
    $this->configuration['models'] = $values['chat']['models'];
    $this->configuration['system_prompt'] = $values['chat']['system_prompt'];
    $this->configuration['temperature'] = $values['tune']['temperature'];
    $this->configuration['top_k'] = $values['tune']['top_k'];
    $this->configuration['top_p'] = $values['tune']['top_p'];
  }

}
