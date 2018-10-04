<?php

namespace AppBundle\DataFixtures;

use AppBundle\Entity\SmsProcessor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;


class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        //create 20k messages
        for ($i = 0; $i < 20000; $i++) {
            $msg = new SmsProcessor();
            $msg->setPhoneNumber('+380664902195');
            $msg->setMsg('Test message ' . $i);
            $manager->persist($msg);
        }

        $manager->flush();
    }
}