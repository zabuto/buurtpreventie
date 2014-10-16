<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Loopresultaat
 *
 * @ORM\Table(name="buurtprev_loopresultaat")
 * @ORM\Entity(repositoryClass="Zabuto\Bundle\BuurtpreventieBundle\Entity\LoopresultaatRepository")
 * @UniqueEntity("omschrijving")
 */
class Loopresultaat
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
     * @var string
     *
     * @ORM\Column(name="omschrijving", type="string", length=100, unique=true)
     * @Assert\NotBlank()
     */
    private $omschrijving;

    /**
     * @var boolean
     *
     * @ORM\Column(name="bijzonderheid", type="boolean")
     * @Assert\NotBlank()
     */
    private $bijzonderheid;

    /**
     * @var boolean
     *
     * @ORM\Column(name="incident", type="boolean")
     * @Assert\NotBlank()
     */
    private $incident;

    /**
     * Magic method toString
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getOmschrijving();
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
     * Set omschrijving
     *
     * @param string $omschrijving
     * @return Loopschema
     */
    public function setOmschrijving($omschrijving)
    {
        $this->omschrijving = $omschrijving;
        return $this;
    }

    /**
     * Get omschrijving
     *
     * @return string
     */
    public function getOmschrijving()
    {
        return $this->omschrijving;
    }

    /**
     * Set bijzonderheid
     *
     * @param boolean $bijzonderheid
     * @return Loopresultaat
     */
    public function setBijzonderheid($bijzonderheid)
    {
        $this->bijzonderheid = $bijzonderheid;
        return $this;
    }

    /**
     * Get bijzonderheid
     *
     * @return boolean
     */
    public function getBijzonderheid()
    {
        return $this->bijzonderheid;
    }

    /**
     * Set incident
     *
     * @param boolean $incident
     * @return Loopresultaat
     */
    public function setIncident($incident)
    {
        $this->incident = $incident;
        return $this;
    }

    /**
     * Get incident
     *
     * @return boolean
     */
    public function getIncident()
    {
        return $this->incident;
    }
}
