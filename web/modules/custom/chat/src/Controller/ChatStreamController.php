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

class ChatStreamController extends ControllerBase {

  public function __construct(
    private readonly LLModelProviderManager $providerManager,
  )
  {
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.llm_services')
    );
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * {
   *   "provider":"ollama",
   *   "model": "llama3",
   *   "prompt": "Why is the sky blue?"
   * }
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   * @throws \JsonException
   */
  public function callback(Request $request): StreamedResponse {
    // @todo Validate data in request.
    $data = json_decode($request->getContent(), associative: TRUE, flags: JSON_THROW_ON_ERROR);

    $provider = $this->providerManager->createInstance($data['provider']);

    $payLoad = new Payload();
    $payLoad->model = $data['model'];
//    $payLoad->options = [
//      'temperature' => $temperature,
//      'top_k' => $topK,
//      'top_p' => $topP,
//    ];
//    $msg = new Message();
//    $msg->role = MessageRoles::System;
//    $msg->content = $systemPrompt;
//    $payLoad->messages[] = $msg;

    // @todo Make session cache with previous chat messages to create context.

    $msg = new Message();
    $msg->role = MessageRoles::User;
    $msg->content = $data['prompt'];
    $payLoad->messages[] = $msg;

    return new StreamedResponse(
      function () use ($provider, $payLoad) {
        foreach ($provider->chat($payLoad) as $res) {
          echo $res->getContent();

          // To make the stream actual, well stream, we need to ensure buffers
          // are flushed. Thanks, Drupal, for that one.
          // @see https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response
          ob_flush();
          flush();
        }
      },
      Response::HTTP_OK,
      headers: [
        'Content-Type' => 'application/json',
        'X-Accel-Buffering' => 'no',
      ]
    );
  }

}
