<?php

namespace App\Services\Payments;

interface MellatClientInterface
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function bpPayRequest(array $parameters): string;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function bpVerifySettleRequest(array $parameters): string;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function bpSettleRequest(array $parameters): string;
}
