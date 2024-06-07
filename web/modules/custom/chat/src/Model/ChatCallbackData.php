<?php

namespace Drupal\chat\Model;

/**
 * Represents a data object.
 */
readonly class ChatCallbackData {

  /**
   * Class constructor.
   *
   * @param string $provider
   *   The provider name.
   * @param string $model
   *   The model name.
   * @param string $prompt
   *   The user prompt.
   * @param string $systemPrompt
   *   The system prompt.
   * @param float $temperature
   *   The temperature value.
   * @param int $topK
   *   The top K value.
   * @param float $topP
   *   The top P value.
   * @param int $contextExpire
   *   The context expiration time.
   * @param int $contextLength
   *   The context length.
   */
  public function __construct(
    public string $provider,
    public string $model,
    public string $prompt,
    public string $systemPrompt,
    public float $temperature,
    public int $topK,
    public float $topP,
    public int $contextExpire,
    public int $contextLength,
  ) {
  }

}
