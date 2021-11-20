<?php

namespace CrypTax\Exceptions;

class NotFoundException extends BaseException
{
    private $entity = '';

    public function __construct($entity = '') {
        $this->entity = $entity;

        parent::__construct();
    }

    public function __toString() {
        return $this->getShortName() . ': ' . ($this->entity ? ('(' . $this->entity . ') ') : '') . 'Not found.';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'entity' => $this->entity
        ];
    }
}
