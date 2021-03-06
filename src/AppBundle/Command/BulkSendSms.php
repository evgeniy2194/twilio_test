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

    /** @var int $sleepTime - smsProcessor sleep time */
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
     * Else - run smsProcessor that checks new scheduled messages and start asynchronous processes
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
            //Run smsProcessor
            $this->smsProcessor($output);
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
     * Run php smsProcessor
     *
     * @param $output OutputInterface
     */
    private function smsProcessor(OutputInterface $output)
    {
        $processes = [];

        $output->writeln('Run sms processor');

        while (1) {
            //Check if there are any running processes
            if (!sizeof($processes)) {
                $output->writeln('Get scheduled messages');
                $messages = $this->getSmsProcessor();

                $messagesCount = sizeof($messages);

                if (!$messagesCount) {
                    $output->writeln('There are no scheduled messages');
                    $output->writeln('Done!');
                    exit;
                }

                $output->writeln('Found ' . $messagesCount . ' messages');

                //If unsent messages exists
                if (sizeof($messages)) {
                    $chunks = array_chunk($messages, $this->chunkSize);

                    //Run asynchronous process for each chunk
                    foreach ($chunks as $chunk) {
                        $firstId = ($chunk[0])->getId();
                        $lastId = end($chunk)->getId();

                        $output->writeln("Processing messages from $firstId to $lastId");

                        //Run asynchronous process that send chunk of messages
                        $process = new Process('php bin/console cariba:send-sms ' . $firstId . ' ' . $lastId);
                        $process->start();
                        $processes[] = $process;
                    }

                    //Check if processes is running
                    while (sizeof($processes) > 0) {
                        $output->writeln("Waiting for the processes to complete...");
                        foreach ($processes as $processKey => $process) {
                            if (!$process->isRunning()) {
                                unset($processes[$processKey]);
                            }
                        }

                        sleep(1);
                    }
                }
            }

            $output->writeln('Sleep ' . $this->sleepTime . ' seconds');
            sleep($this->sleepTime);
        }
    }

    /**
     * Return unsent sms_processor
     *
     * @param null $firstId
     * @param null $lastId
     * @return mixed
     */
    private function getSmsProcessor($firstId = null, $lastId = null)
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository('AppBundle:SmsProcessor');

        $query = $repository->createQueryBuilder('s')
            ->where('s.result IS NULL')
            ->orderBy('s.id')
            ->setMaxResults($this->limit);

        if ($firstId && $lastId) {
            $query->andWhere('s.id >= :fromId')
                ->andWhere('s.id <= :toId')
                ->setParameters([
                    'fromId' => $firstId,
                    'toId' => $lastId
                ]);
        }

        return $query->getQuery()->getResult();
    }
}