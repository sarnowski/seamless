<?php

/**
 *
 *
 * @scope session
 * @name userManager
 * @author Tobias Sarnowski
 */
class UserManagerMob {

    /**
     * @inject
     * @var MessagesMob
     */
    private $messagesMob;

    /**
     * @inject array AdminPlugin
     */
    private $plugins;

    /**
     * @outject user
     * @var string
     */
    private $currentUser;

    /**
     * @validate email
     * @var string
     */
    private $email;

    private $counter = 0;

    /**
     * @valdate password
     * @var string
     */
    private $password;

    /**
     * @action
     * @throws LoginFailedException
     * @return void
     */
    public function login() {
        debug("logging in");
        if ($this->email != $this->password) {
            $this->messagesMob->addMessage("fail");
            throw new LoginFailedException();
        } else {
            $this->currentUser = $this->email;
            $this->counter++;
            $this->messagesMob->addMessage("ok ".$this->counter);
        }
    }

    /**
     * @loggedIn
     * @throws NotLoggedInException
     * @return void
     */
    public function logout() {
        $this->currentUser = null;
    }

    public function isLoggedIn() {
        return $this->currentUser != null;
    }

    /**
     * @return void
     * @observes killall
     */
    private function killall() {
        $this->currentUser = null;
    }

    /**
     * @pointcutFor loginCheck
     * @param $method
     * @return void
     */
    private function loginCheckPointcut($method) {

    }

    private function loginCheck($chain) {

    }

    /**
     * @bypassInterceptors
     * @return string
     */
    function __toString() {
        return 'UserManagerMob';
    }
}
