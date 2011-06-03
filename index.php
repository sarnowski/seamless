<?php
/**
 *
 *
 * @author Tobias Sarnowski
 */

include('system/core/MobManager.php');
include('system/core/Dispatcher.php');

$site = 'example';
if (file_exists('sites.php')) {
    include('sites.php');
    if (!isset($sites)) {
        throw new Exception('$sites not found after sites.php inclusion');
    }
    $found = false;
    foreach ($sites as $pattern => $name) {
        if (preg_match("#$pattern#", $_SERVER['HTTP_HOST'])) {
            $site = $name;
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new Exception('No site found for '.$_SERVER['HTTP_HOST']);
    }
    if (!file_exists("application/sites/".$site)) {
        throw new Exception("Site $site does not exist.");
    }
}

try {
    $mobManager = new MobManager();
    $mobManager->start(__DIR__, $site);

    $dispatcher = $mobManager->getMob('dispatcher');

    $dispatcher->dispatch($site);
} catch (Exception $e) {
    echo "<pre>$e</pre>";
}

$mobManager->stop();