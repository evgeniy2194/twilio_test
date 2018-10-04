<?php

namespace AppBundle\Command;

use AppBundle\Entity\SmsProcessor;
use AppBundle\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class BulkSendSms extends Command
{
    /** @var TwilioService $twilioService */
    private $twilioService;

    /** @var EntityManagerInterface $em */
    private $em;

    private $limit = 1000;
    private $chunkSize = 100;

    public function __construct(TwilioService $twilioService, EntityManagerInterface $em)
    {
        $this->twilioService = $twilioService;
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('cariba:send-sms')
            ->setDescription('Bulk sms sending')
            ->addArgument('first_id', InputArgument::OPTIONAL, 'Id of first sms to send')
            ->addArgument('last_id', InputArgument::OPTIONAL, 'Id of last sms to send');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $firstId = $input->getArgument('first_id');
        $lastId = $input->getArgument('last_id');

        if ($firstId && $lastId) {
            $this->sendSms($firstId, $lastId);
        } else {
            //Run daemon
            $this->daemon();
        }
    }

    private function getMessages($fromId = null, $toId = null)
    {
        $where = ['result' => null];

        if ($fromId && $toId) {
            $where['id'] = range($fromId, $toId);
        }

        return $this->em->getRepository('AppBundle:SmsProcessor')
            ->findBy($where, ['id' => 'ASC'], $this->limit);
    }

    private function sendSms($firstId, $lastId)
    {
        $messages = $this->getMessages($firstId, $lastId);

        /** @var SmsProcessor $message */
        foreach ($messages as $message) {
            $result = $this->twilioService->sendSms(
                $message->getPhoneNumber(),
                $message->getMsg()
            );
            $message->setResult($result->errorCode ? 0 : 1);
            $this->em->flush();
        }
    }

    private function daemon()
    {
        $processes = [];

        while (1) {
            echo 'get messages';

            if (!sizeof($processes)) {
                $messages = $this->getMessages();

                echo 'found ' . sizeof($messages);

                if (sizeof($messages)) {
                    $chunks = array_chunk($messages, $this->chunkSize);

                    foreach ($chunks as $chunk) {
                        $firsId = ($chunk[0])->getId();
                        $lasId = end($chunk)->getId();

                        $process = new Process('php bin/console cariba:send-sms ' . $firsId . ' ' . $lasId);
                        $process->start();
                        $processes[] = $process;
                    }

                    while (sizeof($processes) > 0) {
                        foreach ($processes as $processKey => $process) {
                            if (!$process->isRunning()) {
                                unset($processes[$processKey]);
                            }
                        }
                    }
                }
            }

            sleep(2);
        }
    }
}