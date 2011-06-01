<?php

/**
 *
 *
 * @author Tobias Sarnowski
 */
class MobProxy {

    private $className;

    public function __construct($className) {
        $this->className = $className;
    }

    public function __class() {
        return $this->className;
    }

    public function __call($name, $arguments) {
        return MobManager::getSingleton()->callMethod($this->className, $name, $arguments);
    }
}
