<?php

namespace App\Contracts;

interface InstanceInterface
{
    public function setUp();
    public function computeTrafficRule();
    public function tearDown();
    public function turnOff();
    public function turnOn();
    public function backUp();
    public function restore();
    public function downgrade();
    public function upgrade();
}
