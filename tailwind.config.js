/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./web/themes/custom/ai_launchpad/**/*.{twig,html,js}"],
  safelist: [
    'chat-message-container-you',
    'chat-message-container-bot',
    'chat-message-container-info',
    'chat-message-variant-you',
    'chat-message-variant-bot',
    'chat-message-variant-info',
    'chat-message-info-type',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  darkMode: 'selector',
}

