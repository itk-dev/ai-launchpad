(function ($, Drupal, once) {
  Drupal.behaviors.chat_behavior = {
    attach: function (context) {
      once('chatProcessed', '#chat .btn', context).forEach((element) => {
        element.addEventListener('click', (event) => {
          event.preventDefault();

          // Disable chat button.
          element.disabled = !element.disabled;

          let chatElement = $('#chat');
          let data = {
            "provider": chatElement.data('provider'),
            "model": $('#chat .model').find(":selected").val(),
            "prompt": $('#chat .question').val(),
            "system_prompt": chatElement.data('system_prompt'),
            "temperature": chatElement.data('temperature'),
            "top_k": chatElement.data('top_k'),
            "top_p": chatElement.data('top_p'),
            "context_expire": chatElement.data('context_expire'),
          }
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
                      let output = $('#chat .output');
                      let data = new TextDecoder().decode(value);
                      output.append(data.replace('\n', '<br/><br/>'));

                      // Follow content scroll.
                      output.animate({ scrollTop: output.prop("scrollHeight")}, 1000);

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
              // Heck to get some spaces.
              $('#chat .output').append('<br/><br/><hr/><br/>');
              // Enable chat button for more questions.
              element.disabled = !element.disabled;
              console.log('Completed');
            });
        });
      });
    }
  };
})(jQuery, Drupal, once);
