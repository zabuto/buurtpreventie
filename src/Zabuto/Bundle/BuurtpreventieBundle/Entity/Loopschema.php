<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Zabuto\Bundle\UserBundle\Entity\User;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopresultaat;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use DateTime;

/**
 * Loopschema
 *
 * @ORM\Table(name="buurtprev_loopschema")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Entity(repositoryClass="Zabuto\Bundle\BuurtpreventieBundle\Entity\LoopschemaRepository")
 * @UniqueEntity(
 *     fields={"loper", "datum", "actueel"},
 *     errorPath="datum",
 *     message="Loper is al aan- of afgemeld voor deze datum"
 * )
 * @Assert\GroupSequence({"Loopschema", "Strict"})
 */
class Loopschema
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
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="Zabuto\Bundle\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="loper_id", referencedColumnName="id")
     * @Assert\NotBlank()
     */
    private $loper;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="datum", type="datetime")
     * @Assert\NotBlank()
     */
    private $datum;

    /**
     * @var boolean
     *
     * @ORM\Column(name="actueel", type="boolean")
     * @Assert\NotBlank()
     */
    private $actueel;

    /**
     * @var Loopresultaat
     *
     * @ORM\ManyToOne(targetEntity="Loopresultaat")
     * @ORM\JoinColumn(name="resultaat_id", referencedColumnName="id")
     */
    private $resultaat;

    /**
     * @var string
     *
     * @ORM\Column(name="bijzonderheden", type="text", nullable=true)
     */
    private $bijzonderheden;

    /**
     * @var string
     *
     * @ORM\Column(name="reden", type="text", nullable=true)
     */
    private $redenAfzegging;

    /**
     * @var string
     *
     * @ORM\Column(name="eventid", type="string", length=255, nullable=true)
     */
    private $eventId;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="aangemaakt", type="datetime")
     */
    private $aangemaaktOp;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="gewijzigd", type="datetime")
     */
    private $gewijzigdOp;

    /**
     * @var Looptoelichting[]
     *
     * @ORM\OneToMany(targetEntity="Looptoelichting", mappedBy="loopschema", cascade={"persist", "remove"})
     */
    protected $toelichtingen;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->setActueel(true);
        $this->setAangemaaktOp(new DateTime());
        $this->setGewijzigdOp(new DateTime());
        $this->toelichtingen = new ArrayCollection();
    }

    /**
     * @ORM\PreUpdate
     */
    public function setUpdatedValue()
    {
        $this->setGewijzigdOp(new DateTime());
    }

    /**
     * @Assert\True(message = "Toelichting moet bij bijzonderheden zijn ingevuld", groups={"Strict"})
     * @return boolean
     */
    public function isResultaatBijzonderheid()
    {
        $resultaat = $this->getResultaat();
        if ($resultaat instanceof Loopresultaat && $resultaat->getBijzonderheid() === true) {
            $bijzonderheden = $this->getBijzonderheden();
            if (empty($bijzonderheden)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @Assert\True(message = "Toelichting moet bij incidenten zijn ingevuld", groups={"Strict"})
     * @return boolean
     */
    public function isResultaatIncident()
    {
        $resultaat = $this->getResultaat();
        if ($resultaat instanceof Loopresultaat && $resultaat->getIncident() === true) {
            $bijzonderheden = $this->getBijzonderheden();
            if (empty($bijzonderheden)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @Assert\True(message = "Reden van afzegging moet zijn ingevuld", groups={"Strict"})
     * @return boolean
     */
    public function isAfzeggingReden()
    {
        return true;
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
     * Set loper
     *
     * @param User $loper
     * @return Loopschema
     */
    public function setLoper($loper)
    {
        $this->loper = $loper;
        return $this;
    }

    /**
     * Get loper
     *
     * @return User
     */
    public function getLoper()
    {
        return $this->loper;
    }

    /**
     * Set datum
     *
     * @param DateTime $datum
     * @return Loopschema
     */
    public function setDatum($datum)
    {
        $this->datum = $datum;
        return $this;
    }

    /**
     * Get datum
     *
     * @return DateTime
     */
    public function getDatum()
    {
        return $this->datum;
    }

    /**
     * Set actueel
     *
     * @param boolean $actueel
     * @return Loopschema
     */
    public function setActueel($actueel)
    {
        $this->actueel = $actueel;
        return $this;
    }

    /**
     * Get actueel
     *
     * @return boolean
     */
    public function getActueel()
    {
        return $this->actueel;
    }

    /**
     * Set resultaat
     *
     * @param Loopresultaat $resultaat
     * @return Loopschema
     */
    public function setResultaat($resultaat)
    {
        $this->resultaat = $resultaat;
        return $this;
    }

    /**
     * Get resultaat
     *
     * @return Loopresultaat
     */
    public function getResultaat()
    {
        return $this->resultaat;
    }

    /**
     * Set bijzonderheden
     *
     * @param string $bijzonderheden
     * @return Loopschema
     */
    public function setBijzonderheden($bijzonderheden)
    {
        $this->bijzonderheden = $bijzonderheden;
        return $this;
    }

    /**
     * Get bijzonderheden
     *
     * @return string
     */
    public function getBijzonderheden()
    {
        return $this->bijzonderheden;
    }

    /**
     * Set redenAfzegging
     *
     * @param string $memo
     * @return Loopschema
     */
    public function setRedenAfzegging($redenAfzegging)
    {
        $this->redenAfzegging = $redenAfzegging;
        return $this;
    }

    /**
     * Get redenAfzegging
     *
     * @return string
     */
    public function getRedenAfzegging()
    {
        return $this->redenAfzegging;
    }

    /**
     * Set eventId
     *
     * @param string $eventId
     * @return Loopschema
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
        return $this;
    }

    /**
     * Get eventId
     *
     * @return string
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Set aangemaaktOp
     *
     * @param DateTime $aangemaaktOp
     * @return Loopschema
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

    /**
     * Set gewijzigdOp
     *
     * @param DateTime $gewijzigdOp
     * @return Loopschema
     */
    public function setGewijzigdOp($gewijzigdOp)
    {
        $this->gewijzigdOp = $gewijzigdOp;
        return $this;
    }

    /**
     * Get gewijzigdOp
     *
     * @return DateTime
     */
    public function getGewijzigdOp()
    {
        return $this->gewijzigdOp;
    }

    /**
     * Add toelichting
     *
     * @param Looptoelichting $toelichting
     * @return Loopschema
     */
    public function addToelichting(Looptoelichting $toelichting)
    {
        if (!$this->toelichtingen->contains($toelichting)) {
            $toelichting->setLoopschema($this);
            $this->toelichtingen->add($toelichting);
        }
        return $this;
    }

    /**
     * Remove toelichting
     *
     * @param Looptoelichting $toelichting
     * @return Loopschema
     */
    public function removeToelichting(Looptoelichting $toelichting)
    {
        $this->toelichtingen->removeElement($toelichting);
        return $this;
    }

    /**
     * Get toelichtingen
     *
     * @param $toelichtingen Looptoelichting[]
     * @return Loopschema
     */
    public function setToelichtingen($toelichtingen)
    {
        $this->toelichtingen = $toelichtingen;
        return $this;
    }

    /**
     * Get toelichtingen
     *
     * @return Looptoelichting[]
     */
    public function getToelichtingen()
    {
        return $this->toelichtingen->toArray();
    }
}
