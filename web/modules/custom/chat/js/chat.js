(function (Drupal, once) {
  const BOT = 'bot';
  const USER = 'you'

  function getCurrentTime() {
    let date = new Date();
    let minutes = date.getMinutes();

    // Pad the minutes with '0' if less than 10.
    minutes = minutes < 10 ? '0' + minutes : minutes;

    return date.getHours() + ':' + minutes;
  }

  function generateUniqueID() {
    return 'id_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 9);
  }

  function cleanInput(element) {
    element.value = '';
  }

  function toggleTextarea(element) {
    if (element.hasAttribute('readonly')) {
      element.removeAttribute('readonly');
    } else {
      element.setAttribute('readonly', 'readonly');
    }
  }

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

  function addNewMessage(element, label) {
    return addMessage(element, label, waiterHTML(), BOT)
  }

  function removeMessageWaiter(element, id) {
    let container = element.querySelector('#' + id);
    let el = container.querySelector(".wait");
    if(el) {
      el.parentNode.removeChild(el);
    }
  }

  function appendMessage(element, id, message) {
    removeMessageWaiter(element, id);

    let container = element.querySelector('#' + id);
    container.innerHTML += message.replace('\n', '<br/><br/>');
    container.innerHTML += waiterHTML();
  }

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
            let msgId = addNewMessage(output, Drupal.t('bot'));
            cleanInput(input);
            toggleTextarea(input);

            // TODO: Move path into config with getting route.
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

                        // Follow content scroll.
                        //output.animate({ scrollTop: output.prop("scrollHeight")}, 1000);

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
