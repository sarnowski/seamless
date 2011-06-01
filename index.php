<?php
/**
 *
 *
 * @author Tobias Sarnowski
 */

include('system/MobManager.php');

$mobManager = new MobManager();
$mobManager->start(__DIR__, 'example');

$userManagerMob = $mobManager->getMob('UserManagerMob');
echo "logged in: ".$userManagerMob->isLoggedIn();

$userManagerMob->login();
echo "logged in: ".$userManagerMob->isLoggedIn();

$messagesMob = $mobManager->getMob('MessagesMob');
debug($messagesMob->getMessages());

$mobManager->stop();