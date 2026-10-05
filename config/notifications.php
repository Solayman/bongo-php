<?php

return [
    // SMS: an HTTP gateway that takes a POST. Without a URL, SMS messages are written to the log.
    'sms' => [
        'url'    => env('SMS_URL', ''),                       // e.g. https://api.your-sms-gateway.com/send
        'token'  => env('SMS_TOKEN', ''),                     // sent as "Authorization: Bearer ..."
        'fields' => ['to' => 'to', 'text' => 'text'],         // the gateway's names for the number and the message
        'extra'  => [],                                       // fixed fields, e.g. ['sender_id' => 'Bongo']
    ],

    // Slack: an incoming-webhook URL. Without one, Slack messages are written to the log.
    'slack' => [
        'webhook' => env('SLACK_WEBHOOK_URL', ''),
    ],
];
