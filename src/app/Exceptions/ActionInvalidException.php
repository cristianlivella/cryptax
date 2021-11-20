<?php

namespace CrypTax\Exceptions;

class ActionInvalidException extends BaseException
{
    private $action;

    public function __construct($action) {
        $this->action = $action;

        parent::__construct();
    }

    public function __toString() {
        return $this->getShortName() . ': Action ' . $this->action . ' is invalid ';
    }

    public function toJson() {
        return [
            'exception' => $this->getShortName(),
            'action' => $this->action
        ];
    }
}
