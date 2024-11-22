<?php

namespace Drupal\gtmai_frontpage_redirect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'gtmai_frontpage_redirect.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'gtmai_frontpage_redirect_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('gtmai_frontpage_redirect.settings');

    // Authenticated user node ID setting.
    $form['authenticated_node_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node ID'),
      '#default_value' => $config->get('authenticated_node_id'),
      '#description' => $this->t('Node ID to use as front page for authenticated users.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('gtmai_frontpage_redirect.settings')
      ->set('authenticated_node_id', $form_state->getValue('authenticated_node_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
