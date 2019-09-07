<?php

namespace App\Interfaces;

/**
 * ResultInterface
 */
interface ResultInterface
{
    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return boolean
     */
    public function allowRemarks();

    /**
     * @return boolean
     */
    public function isIncident();
}
