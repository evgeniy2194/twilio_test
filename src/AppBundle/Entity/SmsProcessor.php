<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SmsProcessor
 *
 * @ORM\Table(name="sms_processor")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SmsProcessorRepository")
 */
class SmsProcessor
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="phone_number", type="string", length=255)
     */
    private $phoneNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="msg", type="string", length=255)
     */
    private $msg;

    /**
     * @var bool
     *
     * @ORM\Column(name="result", type="boolean", nullable=true)
     */
    private $result;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set phoneNumber
     *
     * @param string $phoneNumber
     *
     * @return SmsProcessor
     */
    public function setPhoneNumber($phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Get phoneNumber
     *
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * Set msg
     *
     * @param string $msg
     *
     * @return SmsProcessor
     */
    public function setMsg($msg)
    {
        $this->msg = $msg;

        return $this;
    }

    /**
     * Get msg
     *
     * @return string
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * Set result
     *
     * @param boolean $result
     *
     * @return SmsProcessor
     */
    public function setResult($result)
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Get result
     *
     * @return bool
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return SmsProcessor
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }
}
