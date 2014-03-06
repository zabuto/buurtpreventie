<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use DateTime;

/**
 * Looptoelichting
 *
 * @ORM\Table(name="buurtprev_looptoelichting")
 * @ORM\Entity(repositoryClass="Zabuto\Bundle\BuurtpreventieBundle\Entity\LooptoelichtingRepository")
 */
class Looptoelichting
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Loopschema
     *
     * @ORM\ManyToOne(targetEntity="Loopschema")
     * @ORM\JoinColumn(name="schema_id", referencedColumnName="id")
     * @Assert\NotBlank()
     */
    protected $loopschema;

    /**
     * @var string
     *
     * @ORM\Column(name="memo", type="text")
     * @Assert\NotBlank()
     */
    private $memo;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="aangemaakt", type="datetime")
     */
    private $aangemaaktOp;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->setAangemaaktOp(new DateTime());
    }

    /**
     * Magic method toString
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getMemo() . ' (' . $this->getAangemaaktOp()->format('d-m-Y H:i') . ')';
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set loopschema
     *
     * @param Loopschema $loopschema
     * @return Looptoelichting
     */
    public function setLoopschema(Loopschema $loopschema)
    {
        $this->loopschema = $loopschema;
        return $this;
    }

    /**
     * Get loopschema
     *
     * @return Loopschema
     */
    public function getLoopschema()
    {
        return $this->loopschema;
    }

    /**
     * Set memo
     *
     * @param string $memo
     * @return Looptoelichting
     */
    public function setMemo($memo)
    {
        $this->memo = $memo;
        return $this;
    }

    /**
     * Get memo
     *
     * @return string
     */
    public function getMemo()
    {
        return $this->memo;
    }

    /**
     * Set aangemaaktOp
     *
     * @param DateTime $aangemaaktOp
     * @return Looptoelichting
     */
    public function setAangemaaktOp($aangemaaktOp)
    {
        $this->aangemaaktOp = $aangemaaktOp;
        return $this;
    }

    /**
     * Get aangemaaktOp
     *
     * @return DateTime
     */
    public function getAangemaaktOp()
    {
        return $this->aangemaaktOp;
    }
}
