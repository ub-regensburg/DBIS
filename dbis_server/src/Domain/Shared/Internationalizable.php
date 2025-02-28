<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Domain\Shared;

/**
 * Description of Internationalizable
 *
 */
interface Internationalizable
{
    public function toI18nAssocArray(string $language): array;
}
