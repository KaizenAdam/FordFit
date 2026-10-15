<?php
// Copy this file to fordfit-config.php in the hosting account's home folder (one level ABOVE
// public_html, so the web can never serve it and deploys never overwrite it), then paste the
// Resend API key. Never commit the real key: this repository is public.
return [
  'resend_api_key' => 'PASTE_KEY_HERE',
  'to'   => ['toby@fordfitpt.com'],
  'from' => 'do-not-reply@fordfitpt.com',
];
