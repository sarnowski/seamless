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

        $action = false;
        foreach ($_POST as $key => $value) {
            if (substr($key, 0, 7) == 'action:') {
                $action = substr($key, 7);
                list($mob, $method) = explode('->', $action);
                break;
            }
        }

        if ($action) {
            $mob = MobManager::getSingleton()->getMob($mob);
            $class = MobManager::getSingleton()->getDefinition($mob->__class());
            if (!isset($class['methods'][$method])) {
                throw new Exception('Method '.$method.' does not exist.');
            }
            $found = false;
            foreach ($class['methods'][$method]['annotations'] as $annotation) {
                if ($annotation['name'] == 'action') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new Exception('Method '.$method.' is not accessible.');
            }
            $mob->$method();
        }

        foreach (MobManager::getSingleton()->getNames() as $name) {
            if (MobManager::getSingleton()->hasSingleMob($name)) {
                $template->$name = MobManager::getSingleton()->getMob($name);
            } else {
                $template->$name = MobManager::getSingleton()->getMobs($name);
            }
        }

        $this->addTemplateDirs($template, 'application/sites/'.$site);
        $this->addTemplateDirs($template, 'application');

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

    private function addTemplateDirs($phptal, $basepath) {
        if (file_exists("$basepath/templates")) {
            $phptal->setTemplateRepository("$basepath/templates");
        }

        if (!file_exists("$basepath/modules")) {
            return;
        }

        $dh = dir("$basepath/modules");
        while (false !== ($entry = $dh->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            if (file_exists("$basepath/modules/$entry/templates")) {
                $phptal->setTemplateRepository("$basepath/modules/$entry/templates");
            }
        }
        $dh->close();
    }

}
