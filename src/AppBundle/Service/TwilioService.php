<?php

namespace AppBundle\Service;


use Twilio\Rest\Client;

class TwilioService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param $phoneNumber
     * @param $msg
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendSms($phoneNumber, $msg)
    {
        return $this->client->messages->create($phoneNumber, [
            'from' => '+15005550006',
            'body' => $msg
        ]);
    }
}