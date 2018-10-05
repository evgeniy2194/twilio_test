<?php

namespace AppBundle\Command;

use AppBundle\Entity\SmsProcessor;
use AppBundle\Service\TwilioService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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

    /** @var int $sleepTime - daemon sleep time */
    private $sleepTime = 2;

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

    /**
     * If exists first_id and last_id - send all unsent messages from first_id to last_id
     * Else - run daemon that check new unsent messages and start asynchronous processes
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
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

    /**
     * Send bulk sms from $firstId to $lastId
     *
     * @param $firstId
     * @param $lastId
     */
    private function sendSms($firstId, $lastId)
    {
        //Load sms_processor from database
        $messages = $this->getSmsProcessor($firstId, $lastId);

        /** @var SmsProcessor $message */
        foreach ($messages as $message) {
            //Send sms by twilio api
            $result = $this->twilioService->sendSms(
                $message->getPhoneNumber(),
                $message->getMsg()
            );
            $message->setResult($result->errorCode ? 0 : 1);
            $this->em->flush();
        }
    }

    /**
     * Run php daemon
     */
    private function daemon()
    {
        $processes = [];

        while (1) {
            //Check if there are any running processes
            if (!sizeof($processes)) {
                $messages = $this->getSmsProcessor();

                //If unsent messages exists
                if (sizeof($messages)) {
                    $chunks = array_chunk($messages, $this->chunkSize);

                    //Run asynchronous process for each chunk
                    foreach ($chunks as $chunk) {
                        $firsId = ($chunk[0])->getId();
                        $lastId = end($chunk)->getId();

                        //Run asynchronous process that send chunk of messages
                        $process = new Process('php bin/console cariba:send-sms ' . $firsId . ' ' . $lastId);
                        $process->start();
                        $processes[] = $process;
                    }

                    //Check if processes is running
                    while (sizeof($processes) > 0) {
                        foreach ($processes as $processKey => $process) {
                            if (!$process->isRunning()) {
                                unset($processes[$processKey]);
                            }
                        }
                    }
                }
            }

            sleep($this->sleepTime);
        }
    }

    /**
     * Return unsent sms_processor
     *
     * @param null $fromId
     * @param null $toId
     * @return mixed
     */
    private function getSmsProcessor($fromId = null, $toId = null)
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository('AppBundle:SmsProcessor');

        $query = $repository->createQueryBuilder('s')
            ->where('s.result IS NULL')
            ->orderBy('s.id')
            ->setMaxResults($this->limit);

        if ($fromId && $toId) {
            $query->andWhere('s.id >= :fromId')
                ->andWhere('s.id <= :toId')
                ->setParameters([
                    'fromId' => $fromId,
                    'toId' => $toId
                ]);
        }

        return $query->getQuery()->getResult();
    }
}