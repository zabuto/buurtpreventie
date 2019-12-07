<?php

namespace App\Interfaces;

use libphonenumber\PhoneNumber;

/**
 * WalkerInterface
 */
interface WalkerInterface
{
    /**
     * @return integer
     */
    public function getId();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @return string|PhoneNumber|null
     */
    public function getPhone();

    /**
     * @return string|PhoneNumber|null
     */
    public function getMobile();

    /**
     * @return string|null
     */
    public function getAddress();

    /**
     * @return boolean
     */
    public function isCredited();

    /**
     * @return boolean
     */
    public function isPermitted();
}
