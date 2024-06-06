<?php

namespace Drupal\chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\llm_services\Model\Message;
use Drupal\llm_services\Model\MessageRoles;
use Drupal\llm_services\Model\Payload;
use Drupal\llm_services\Plugin\LLModelProviderManager;
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
    // @todo Validate data in request.
    $data = json_decode($request->getContent(), associative: TRUE, flags: JSON_THROW_ON_ERROR);

    $provider = $this->providerManager->createInstance($data['provider']);

    $expire = $data['context_expire'];
    $cid = \Drupal::service('session')->getId();
    $cached = \Drupal::cache('chat')->get($cid);
    if ($cached) {
      $payload = $cached->data;
    }
    else {
      $payload = new Payload();
      $payload->setModel($data['model'])
        ->addOption('temperature', (float) $data['temperature'])
        ->addOption('top_k', (int) $data['top_k'])
        ->addOption('top_p', (float) $data['top_p']);
      $msg = new Message();
      $msg->role = MessageRoles::System;
      $msg->content = $data['system_prompt'];
      $payload->addMessage($msg);
    }

    // Enforce context length, number of message in the payload based on
    // configuration.
    $this->enforceContextLength($payload, (int) $data['context_length']);

    // Add the newest question.
    $msg = new Message();
    $msg->role = MessageRoles::User;
    $msg->content = $data['prompt'];
    $payload->addMessage($msg);

    return new StreamedResponse(
      function () use ($provider, $payload, $cid, $expire) {
        $message = '';
        foreach ($provider->chat($payload) as $res) {
          echo $res->getContent();

          // To make the stream actual, well stream, we need to ensure buffers
          // are flushed. Thanks, Drupal, for that one.
          // @see https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response
          ob_flush();
          flush();

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
        // For some known reason, the application type need to be json not clean
        // text, even though we send clean text. If changed stream stops
        // working.
        'Content-Type' => 'application/json',
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
   */
  public function reset(Request $request): Response {
    $cid = \Drupal::service('session')->getId();
    \Drupal::cache('chat')->delete($cid);

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

}
