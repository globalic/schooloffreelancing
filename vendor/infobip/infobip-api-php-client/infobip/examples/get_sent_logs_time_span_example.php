<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use infobip\api\client\GetSentSmsLogs;
use infobip\api\configuration\BasicAuthConfiguration;
use infobip\api\model\sms\mt\logs\GetSentSmsLogsExecuteContext;

// Initializing GetSentSmsLogs client with appropriate configuration
$client = new GetSentSmsLogs(new BasicAuthConfiguration(USERNAME, PASSWORD));
// Creating execution context
$context = new GetSentSmsLogsExecuteContext();

// Note that timezone used for these DateTimes is defined in examples.php
$context->setSentSince(new DateTime("now -30 minute"));
$context->setSentUntil(new DateTime("now -10 minute"));

// Executing request
$response = $client->execute($context);

for ($i = 0; $i < count($response->getResults()); ++$i) {
    $result = $response->getResults()[$i];
    echo "Message ID: " . $result->getMessageId() . "\n";
    echo "Sent at: " . $result->getSentAt()->format('Y-m-d H:i:s P') . "\n";
    echo "Sender: " . $result->getFrom() . "\n";
    echo "Receiver: " . $result->getTo() . "\n";
    echo "Message text: " . $result->getText() . "\n";
    echo "Status: " . $result->getStatus()->getName() . "\n";
    echo "Price: " . $result->getPrice()->getPricePerMessage() . " " . $result->getPrice()->getCurrency() . "\n\n";
}