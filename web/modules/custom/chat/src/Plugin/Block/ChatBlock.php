<?php

namespace Drupal\chat\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\llm_services\Exceptions\CommunicationException;
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

  use MessengerTrait;

  /**
   * Special char used to encode/decode model names.
   *
   * @var string
   */
  private string $modelEncoderChar = '¤';

  /**
   * Constructs a new MyCustomBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\llm_services\Plugin\LLModelProviderManager $providerManager
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
    $streamUrl = Url::fromRoute('chat.stream');
    $resetUrl = Url::fromRoute('chat.reset');

    return [
      '#theme' => 'chat',
      '#ui' => [
        'id' => $this->configuration['ui']['id'],
        'buttons' => $this->configuration['ui']['buttons'],
        'preferred' => $this->configuration['ui']['preferred'],
        'models' => $this->decodeModelNames($this->configuration['models']),
      ],
      '#attached' => [
        'library' => [
          'chat/chat',
        ],
        'drupalSettings' => [
          'chat' => [
            'id' => $this->configuration['ui']['id'],
            'callback_path' => $streamUrl->toString(),
            'reset_path' => $resetUrl->toString(),
            'provider_name' => $this->configuration['provider_name'],
            'system_prompt' => $this->configuration['system_prompt'],
            'temperature' => $this->configuration['temperature'],
            'top_k' => $this->configuration['top_k'],
            'top_p' => $this->configuration['top_p'],
            'context_expire' => $this->configuration['context_expire'],
            'context_length' => $this->configuration['context_length'],
            'waiter_svg' => \Drupal::service('extension.list.module')->getPath('chat') . '/svg/wait.svg',
            'ui' => [
              'parse_markdown' => $this->configuration['ui']['parse_markdown'],
            ],
          ],
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
      'temperature' => 0.2,
      'top_k' => 40,
      'top_p' => 0.5,
      'context_expire' => 3600,
      'context_length' => 10,
      'ui' => [
        'id' => 'jsChat',
        'buttons' => FALSE,
        'preferred' => '',
        'parse_markdown' => FALSE,
      ],
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
    $providers = array_map(function ($plugin) {
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
      '#options' => $providers,
      '#default_value' => $this->configuration['provider_name'],
      '#required' => TRUE,
    ];

    $models = $this->getModelsFromProvider($this->configuration['provider_name']);
    $form['chat']['models'] = [
      '#type' => 'select',
      '#title' => $this->t('Models'),
      '#description' => $this->t('Select the models this chat should use'),
      '#options' => $models,
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['models'],
      '#required' => FALSE,
    ];

    $form['chat']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#description' => $this->t('System message to instruct the llm have to behave.'),
      '#default_value' => $this->configuration['system_prompt'],
      '#required' => TRUE,
    ];

    $form['ui'] = [
      '#type' => 'details',
      '#title' => $this->t('User interface tweaks'),
      '#open' => TRUE,
    ];

    $form['ui']['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat ID'),
      '#description' => $this->t('If inserting more that one chat block. Set unique ID for the chat window here.'),
      '#default_value' => $this->configuration['ui']['id'],
    ];

    $form['ui']['preferred'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred model'),
      '#description' => $this->t('The default pre-selected/preferred model'),
      '#options' => $models,
      '#default_value' => $this->configuration['ui']['preferred'],
      '#required' => FALSE,
    ];

    $form['ui']['buttons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable minimize/close buttons'),
      '#description' => $this->t('If checked, minimize buttons will be enabled.'),
      '#default_value' => $this->configuration['ui']['buttons'],
    ];

    $form['ui']['parse_markdown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable parsing of markdown'),
      '#description' => $this->t('If checked, markdown returned from the LLM will be parsed (or tried to be parsed).'),
      '#default_value' => $this->configuration['ui']['parse_markdown'],
    ];

    $form['tune'] = [
      '#type' => 'details',
      '#title' => $this->t('Fine-tune the chat'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    // Drupal has in their smartness removed #step, hence no support for
    // floating numbers in their number form type.
    $form['tune']['temperature'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Temperature'),
      '#description' => $this->t('The temperature of the model. Increasing the temperature will make the model answer more creatively.'),
      '#default_value' => $this->configuration['temperature'],
    ];

    $form['tune']['top_k'] = [
      '#type' => 'number',
      '#title' => $this->t('Top k'),
      '#description' => $this->t('Reduces the probability of generating nonsense. A higher value (e.g. 100) will give more diverse answers.'),
      '#default_value' => $this->configuration['top_k'],
      '#min' => 0,
    ];

    $form['tune']['top_p'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top p'),
      '#description' => $this->t('A higher value (e.g., 0.95) will lead to more diverse text, while a lower value (e.g., 0.5) will generate more focused and conservative text.'),
      '#default_value' => $this->configuration['top_p'],
    ];

    $form['tune']['context_expire'] = [
      '#type' => 'number',
      '#title' => $this->t('Context expire'),
      '#description' => $this->t('The time before chat context should be purged from the cache.'),
      '#default_value' => $this->configuration['context_expire'],
      '#min' => 0,
    ];

    $form['tune']['context_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Context length'),
      '#description' => $this->t('The number of user message to send to the LLM as context.'),
      '#default_value' => $this->configuration['context_length'],
      '#min' => 0,
      '#max' => 25,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    if (!filter_var($values['tune']['temperature'], FILTER_VALIDATE_FLOAT, ['min_range' => 0.0, 'max_range' => 1.0])) {
      $form_state->setErrorByName('tune][temperature', $this->t('The temperature must be in the range 0.0 to 1.0.'));
    }

    if (!filter_var($values['tune']['top_p'], FILTER_VALIDATE_FLOAT, ['min_range' => 0.0, 'max_range' => 1.0])) {
      $form_state->setErrorByName('tune][top_p', $this->t('The top p must be in the range 0.0 to 1.0.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->configuration['provider_name'] = $values['chat']['provider_name'];
    $this->configuration['models'] = $this->encodeModelNames($values['chat']['models']);
    $this->configuration['system_prompt'] = $values['chat']['system_prompt'];
    $this->configuration['temperature'] = $values['tune']['temperature'];
    $this->configuration['top_k'] = $values['tune']['top_k'];
    $this->configuration['top_p'] = $values['tune']['top_p'];
    $this->configuration['context_expire'] = $values['tune']['context_expire'];
    $this->configuration['context_length'] = $values['tune']['context_length'];
    $this->configuration['ui']['buttons'] = $values['ui']['buttons'];
    $this->configuration['ui']['id'] = $values['ui']['id'];
    $this->configuration['ui']['preferred'] = $values['ui']['preferred'];
    $this->configuration['ui']['parse_markdown'] = $values['ui']['parse_markdown'];
  }

  /**
   * Retrieves the models from a provider.
   *
   * @param string $providerName
   *   The name of the provider.
   *
   * @return array
   *   An array of models retrieved from the given provider. Each model is in
   *   the format "model name (modified timestamp)".
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getModelsFromProvider(string $providerName): array {
    $provider = $this->providerManager->createInstance($providerName);
    $models = [];
    try {
      $models = $provider->listModels();
      $models = array_map(function ($model) {
        return sprintf('%s (%s)', $model['name'], $model['modified']);
      }, $models);
    }
    catch (CommunicationException $exception) {
      $this->messenger()->addMessage('Error communicating with LLM: ' . $exception->getMessage());
    }
    ksort($models);

    return $models;
  }

  /**
   * Encode model names.
   *
   * Drupal does not allow dots in configuration keys when storing arrays. So
   * this will fix that issue.
   *
   * @param array<string, string> $models
   *   Name of the models.
   *
   * @return array<string, string>
   *   Models array with encoded keys.
   */
  private function encodeModelNames(array $models): array {
    $encoded = [];
    foreach ($models as $model) {
      $encoded[str_replace('.', $this->modelEncoderChar, $model)] = $model;
    }

    return $encoded;
  }

  /**
   * Decodes the model names.
   *
   * @param array $models
   *   The array of models to decode.
   *
   * @return array
   *   The decoded model names.
   */
  private function decodeModelNames(array $models): array {
    $encoded = [];
    foreach ($models as $model) {
      $encoded[str_replace($this->modelEncoderChar, '.', $model)] = $model;
    }

    return $encoded;
  }

}
