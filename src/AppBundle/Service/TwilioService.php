<?php

namespace AppBundle\Service;


use Twilio\Rest\Client;

class TwilioService
{
    private $client;
    private $from;

    public function __construct(Client $client, $from)
    {
        $this->client = $client;
        $this->from = $from;
    }

    /**
     * @param $phoneNumber
     * @param $msg
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendSms($phoneNumber, $msg)
    {
        return $this->client->messages->create($phoneNumber, [
            'from' => $this->from,
            'body' => $msg
        ]);
    }
}