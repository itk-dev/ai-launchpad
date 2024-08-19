<?php

namespace Drupal\chat\Controller;

use Drupal\chat\Model\ChatCallbackData;
use Drupal\Core\Controller\ControllerBase;
use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;
use Drupal\llm_services\Plugin\LLModelProviderManager;
use GuzzleHttp\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat stram controller.
 */
class ChatStreamController extends ControllerBase {

  /**
   * Default constructor.
   *
   * @param \Drupal\llm_services\Plugin\LLModelProviderManager $providerManager
   *   Model provider manager.
   */
  public function __construct(
    private readonly LLModelProviderManager $providerManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.llm_services')
    );
  }

  /**
   * Stream callback for chat communication with LLM.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request from the front end.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   Stream with text content from the LLM.
   *
   * @throws \JsonException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function callback(Request $request): StreamedResponse {
    $json = json_decode($request->getContent(), associative: TRUE, flags: JSON_THROW_ON_ERROR);
    $data = $this->mapJsonToData($json);

    $provider = $this->providerManager->createInstance($data->provider);

    $expire = $data->contextExpire;
    $cid = $data->cid;
    $cached = \Drupal::cache('chat')->get($cid);
    if ($cached) {
      $payload = $cached->data;
    }
    else {
      $payload = new Payload();
      $payload->addOption('temperature', $data->temperature)
        ->addOption('top_k', $data->topK)
        ->addOption('top_p', $data->topP);
      $msg = new Message();
      $msg->role = MessageRoles::System;
      $msg->content = $data->systemPrompt;
      $payload->addMessage($msg);
    }

    // All-ways set model to support a switching model in the frontned.
    $payload->setModel($data->model);

    // Enforce context length, number of message in the payload based on
    // configuration.
    $this->enforceContextLength($payload, (int) $data->contextLength);

    // Add the newest question.
    $msg = new Message();
    $msg->role = MessageRoles::User;
    $msg->content = $data->prompt;
    $payload->addMessage($msg);

    $parseMarkdown = $data->parseMarkdown;

    return new StreamedResponse(
      function () use ($provider, $payload, $cid, $expire, $parseMarkdown) {
        $message = '';
        foreach ($provider->chat($payload) as $res) {
          if ($parseMarkdown) {
            echo $res->getContent();
          }
          else {
            // Markdown parser is not activated in the UI, so lets convert
            // new-lines to simple line-breaks.
            echo str_replace("\n", '<br />', $res->getContent());
          }

          // To make the stream actual, well stream, we need to ensure buffers
          // are flushed. Thanks, Drupal, for that one.
          // @see https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response
          ob_flush();
          flush();

          // Build a complete message to store in cache to enable context for
          // the next chat message.
          $message .= $res->getContent();
          if ($res->isCompleted()) {
            // Add the completed message to payload.
            $msg = new Message();
            $msg->role = MessageRoles::Assistant;
            $msg->content = $message;
            $payload->addMessage($msg);

            // Save new chat session to cache.
            $cache = \Drupal::cache('chat');
            $cache->set($cid, $payload, time() + $expire);
          }
        }
      },
      Response::HTTP_OK,
      headers: [
        // Ensure nginx and proxy do not cache.
        'X-Accel-Buffering' => 'no',
        // Ensure browser do not cache.
        'Cache-Control' => 'no-cache, no-store, private',
      ]
    );
  }

  /**
   * Delet current chat history from given session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Return 204 http respons.
   *
   * @throws \JsonException
   */
  public function reset(Request $request): Response {
    $json = json_decode($request->getContent(), associative: TRUE, flags: JSON_THROW_ON_ERROR);
    \Drupal::cache('chat')->delete($json['cid']);

    return new Response('', Response::HTTP_NO_CONTENT);
  }

  /**
   * Enforce the context length of a payload's messages.
   *
   * @param \Drupal\llm_services\Model\Payload $payload
   *   The payload to enforce the context length of.
   * @param int $context_length
   *   The desired context length. Default: 10.
   */
  private function enforceContextLength(Payload $payload, int $context_length = 10): void {
    $messages = $payload->getMessages();

    // Length times two (every question has an answerer) + system prompt.
    $max = ($context_length * 2) + 1;

    // If count doesn't exceed max, no need to enforce context length.
    if (count($messages) <= $max) {
      return;
    }

    // Remove the system message.
    $system = array_shift($messages);

    // Remove messages exceeding the max limit. From the start of the array.
    $messages = array_slice(
      array: array_reverse($messages),
      offset: 0,
      length: $max - 1
    );

    // Reverse back to correct order and add the system message as the first
    // message.
    $messages = array_reverse($messages);
    array_unshift($messages, $system);

    // Override messages in the payload.
    $payload->setMessages($messages);
  }

  /**
   * Create a callback payload from JSON.
   *
   * @param array $json
   *   The JSON data to create the callback payload from.
   *
   * @return \Drupal\Chat\Model\ChatCallbackData
   *   The created callback payload object.
   *
   * @throws \InvalidArgumentException
   *   If the JSON given is not valid.
   */
  private function mapJsonToData(array $json): ChatCallbackData {
    $keys = [
      'provider',
      'model',
      'prompt',
      'system_prompt',
      'temperature',
      'top_k',
      'top_p',
      'context_expire',
      'context_length',
      'cid',
    ];

    // All keys exist.
    $common = array_intersect_key(array_flip($keys), $json);
    if (count($keys) !== count($common)) {
      throw new InvalidArgumentException('Request data is not valid');
    }

    try {
      return new ChatCallbackData(
        provider: $json['provider'],
        model: $json['model'],
        prompt: $json['prompt'],
        systemPrompt: $json['system_prompt'],
        temperature: (float) $json['temperature'],
        topK: (int) $json['top_k'],
        topP: (float) $json['top_p'],
        contextExpire: (int) $json['context_expire'],
        contextLength: (int) $json['context_length'],
        cid: $json['cid'],
        parseMarkdown: $json['parse_markdown'] ?? FALSE,
      );
    }
    catch (\TypeError $exception) {
      throw new InvalidArgumentException('Request data is not valid: ' . $exception->getMessage(), $exception->getCode(), $exception);
    }
  }

}
