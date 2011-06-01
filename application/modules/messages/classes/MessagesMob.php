<?php

/**
 *
 *
 * @author Tobias Sarnowski
 */
class MessagesMob {

    private $messages = array();

    public function addMessage($test) {
        $this->messages[] = $test;
    }

    public function getMessages() {
        return $this->messages;
    }
}
