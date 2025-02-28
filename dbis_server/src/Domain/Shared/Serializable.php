<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Domain\Shared;

/**
 * Description of Serializable
 *
 */
interface Serializable
{
    public function toAssocArray(): array;
}
