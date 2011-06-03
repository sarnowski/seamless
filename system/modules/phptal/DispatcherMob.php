<?php
include('PHPTAL-1.2.2/PHPTAL.php');

/**
 *
 *
 * @name dispatcher
 * @author Tobias Sarnowski
 */
class DispatcherMob implements Dispatcher {

    public function dispatch($site) {
        $requestUri = $_SERVER['SCRIPT_URL'];
        if (substr($requestUri, 0, 1) != '/') {
            $requestUri = "/$requestUri";
        }
        if ($requestUri == '/') {
            $requestUri = '/index';
        }

        $view = "$requestUri.xhtml";

        $file = $this->findView('application/sites/'.$site, $view);
        if (!$file) {
            $file = $this->findView('application', $view);
            if (!$file) {
                throw new Exception("View $view not found.");
            }
        }

        $template = new PHPTAL($file);

        foreach (MobManager::getSingleton()->getNames() as $name) {
            if (MobManager::getSingleton()->hasSingleMob($name)) {
                $template->$name = MobManager::getSingleton()->getMob($name);
            } else {
                $template->$name = MobManager::getSingleton()->getMobs($name);
            }
        }

        echo $template->execute();
    }

    private function findView($basepath, $view) {
        if (file_exists("$basepath/views$view")) {
            return "$basepath/views$view";
        }

        if (!file_exists("$basepath/modules")) {
            return false;
        }

        $dh = dir("$basepath/modules");
        while (false !== ($entry = $dh->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            if (file_exists("$basepath/modules/$entry/views$view")) {
                return "$basepath/modules/$entry/views$view";
            }
        }
        $dh->close();

        return false;
    }
}
