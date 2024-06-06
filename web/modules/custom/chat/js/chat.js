(function (Drupal, once) {
  const BOT = 'bot';
  const USER = 'you'

  /**
   * Returns the current time in HH:mm format.
   *
   * @returns {string}
   *   The current time in HH:mm format.
   */
  function getCurrentTime() {
    let date = new Date();
    let minutes = date.getMinutes();

    // Pad the minutes with '0' if less than 10.
    minutes = minutes < 10 ? '0' + minutes : minutes;

    return date.getHours() + ':' + minutes;
  }

  /**
   * Generates a unique ID string.
   *
   * @returns {string}
   *   A unique ID string in the format 'id_{timestamp}_{random}'.
   */
  function generateUniqueID() {
    return 'id_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 9);
  }

  /**
   * Clears the value of the given input element.
   *
   * @param {HTMLInputElement} element
   *   The input element to be cleared.
   */
  function cleanInput(element) {
    element.value = '';
  }

  /**
   * Toggles the readonly attribute of a textarea element.
   *
   * @param {HTMLTextAreaElement} element
   *   The textarea element to toggle.
   */
  function toggleTextarea(element) {
    if (element.hasAttribute('readonly')) {
      element.removeAttribute('readonly');
    } else {
      element.setAttribute('readonly', 'readonly');
    }
  }

  /**
   * Creates a new chat message and appends it to the given element.
   *
   * @param {HTMLElement} element
   *   The element to append the chat message to.
   * @param {string} label
   *   The label for the chat message.
   * @param {string} message
   *   The content of the chat message.
   * @param {string} type
   *   The type of the chat message variant.
   * @returns {string}
   *   The unique ID of the appended chat message.
   */
  function addMessage(element, label, message, type) {
    let id = generateUniqueID()
    let time = getCurrentTime();
    let content = `
    <div class="chat-message-container-${type}">
      <div class="text-secondary">
        <span>${label}</span>
        <span class="time">${time}</span>
      </div>
      <span id=${id} class="message chat-message-variant-${type}">
            ${message}
      </span>
    </div>`;

    element.innerHTML += content;

    return id;
  }

  /**
   * Generates HTML markup for a waiter animation.
   *
   * @returns {string}
   *   The HTML markup for the waiter animation.
   */
  function waiterHTML() {
    // @todo: would is be possible to create class for the styles here.
    return `
      <div class="wait" style="font-size: 0.5rem;">
        <svg class="svg-inline--fa fa-circle fa-bounce fa-fw fa-xs" style="--fa-bounce-land-scale-x: 1.2;--fa-bounce-land-scale-y: .8;--fa-bounce-rebound: 1px; --fa-animation-delay: 0.0s;" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg>
        <svg class="svg-inline--fa fa-circle fa-bounce fa-fw fa-xs" style="--fa-bounce-land-scale-x: 1.2;--fa-bounce-land-scale-y: .8;--fa-bounce-rebound: 1px; --fa-animation-delay: 0.0s;" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg>
        <svg class="svg-inline--fa fa-circle fa-bounce fa-fw fa-xs" style="--fa-bounce-land-scale-x: 1.2;--fa-bounce-land-scale-y: .8;--fa-bounce-rebound: 1px; --fa-animation-delay: 0.2s;" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg>
        <svg class="svg-inline--fa fa-circle fa-bounce fa-fw fa-xs" style="--fa-bounce-land-scale-x: 1.2;--fa-bounce-land-scale-y: .8;--fa-bounce-rebound: 1px; --fa-animation-delay: 0.4s;" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg>
      </div>`
  }

  /**
   * Adds a new message to the given element.
   *
   * @param {HTMLElement} element
   *   The element to which the message will be added.
   * @param {string} label
   *   The label or content of the new message.
   * @returns {string}
   *   The generated HTML string of the new message.
   */
  function addNewMessage(element, label) {
    return addMessage(element, label, waiterHTML(), BOT)
  }

  /**
   * Removes the message waiter from the given element.
   *
   * @param {HTMLElement} element
   *   The element to remove the message waiter from.
   * @param {string} id
   *   The ID of the container element containing the message waiter.
   */
  function removeMessageWaiter(element, id) {
    let container = element.querySelector('#' + id);
    let el = container.querySelector(".wait");
    if(el) {
      el.parentNode.removeChild(el);
    }
  }

  /**
   * Append a message to a specified element with the given id.
   *
   * @param {HTMLElement} element
   *   The element to append the message to.
   * @param {string} id
   *   The id of the container element.
   * @param {string} message
   *   The message to be appended to the element.
   *
   * @return {void}
   */
  function appendMessage(element, id, message) {
    removeMessageWaiter(element, id);

    let container = element.querySelector('#' + id);
    container.innerHTML += message.replace('\n', '<br/><br/>');
    container.innerHTML += waiterHTML();
  }

  /**
   * Attach javascript to the chat window.
   *
   * @type {{attach: Drupal.behaviors.chat_behavior.attach}}
   */
  Drupal.behaviors.chat_behavior = {
    attach: function (context) {
      once('processed', '#' + drupalSettings.chat.id, context).forEach((element) => {
        const settings = drupalSettings.chat;
        const chatWindow = document.getElementById(settings.id);

        // Build default payload for the post-request.
        let payload = {
          "provider": settings.provider_name,
          "model": '',
          "prompt": '',
          "system_prompt": settings.system_prompt,
          "temperature": settings.temperature,
          "top_k": settings.top_k,
          "top_p": settings.top_p,
          "context_expire": settings.context_expire,
        }

        const output = chatWindow.querySelector('main');
        const input = chatWindow.querySelector('#inputMessage');
        input.addEventListener('keypress', function (e) {
          let key = e.which || e.keyCode;
          // Enter pressed (without the shift key pressed).
          if (key === 13 && !e.shiftKey) {
            e.preventDefault();

            // Check that model has been selected.
            let models = chatWindow.querySelector('#models');
            let model = models.value;
            if (!model) {
              alert('Please select a model first');
              return;
            }

            // Check that input has been provided.
            if (input.value.trim() === "") {
              alert('Please provide an input');
              return;
            }

            // Build payload
            let data = Object.assign({}, payload, {
              "model": model,
              "prompt": input.value,
            });

            // Add user's chat message to the chat window and clear input.
            addMessage(output, Drupal.t('you'), input.value, USER);
            output.scrollTop = output.scrollHeight;

            // Create a bot message with "waiter/loader" and get id for the HTML
            // element (used later for appending stream response into the
            // field).
            let msgId = addNewMessage(output, Drupal.t('bot'));

            // Clear inout and toggle text area to prevent more input.
            cleanInput(input);
            toggleTextarea(input);

            // TODO: Move path into config with getting route.
            // Send the chat request to the backend.
            fetch("/ajax/chat", {
              method: 'POST',
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(data)
            })
              .then((response) => response.body)
              .then((rb) => {
                const reader = rb.getReader();
                return new ReadableStream({
                  start(controller) {
                    // The following function handles each data chunk
                    function push() {
                      reader.read().then(({ done, value }) => {
                        // If there is no more data to read
                        if (done) {
                          controller.close();
                          return;
                        }
                        // Get the data and send it to the browser via the
                        // controller
                        controller.enqueue(value);

                        // Decode chunk and append to HTML.
                        let data = new TextDecoder().decode(value);
                        appendMessage(output, msgId, data);

                        // @todo: better scroll with some animation?
                        // Follow content scroll.
                        output.scrollTop = output.scrollHeight;

                        push();
                      });
                    }
                    push();
                  },
                });
              })
              .then((stream) =>
                // Respond with our stream.
                new Response(stream, { headers: { "Content-Type": "application/json" } }).text(),
              )
              .then((result) => {
                // Remove waiter/loader.
                removeMessageWaiter(output, msgId)

                // Enable chat button for more questions.
                toggleTextarea(input);
              });
          }
        }, { capture: true });
      });
    }
  };
})(Drupal, once);
