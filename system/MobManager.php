<?php

include('debug.php');
include('MobProxy.php');

/**
 *
 *
 * @author Tobias Sarnowski
 */
class MobManager {
    private static $SINGLETON;

    private $classes = array();

    private $mobs = array();

    private $annotations = array();

    private $aliases = array();

    private $scopes = array(
        'request' => array(),
        'session' => array()
    );

    public function __construct() {
        self::$SINGLETON = $this;
    }

    public function start($directory, $site) {
        // folders to watch for php code
        $applicationModuleMobClasses = $this->findMobs("$directory/application/modules");
        $applicationMobClasses = $this->findMobs("$directory/application/classes");
        $siteModuleMobClasses = $this->findMobs("$directory/application/sites/$site/modules");
        $siteMobClasses = $this->findMobs("$directory/application/sites/$site/classes");

        $this->classes = array_merge(
            $applicationModuleMobClasses,
            $applicationMobClasses,
            $siteModuleMobClasses,
            $siteMobClasses
        );

        // load all classes
        foreach ($this->classes as $className => $classFile) {
            if (substr($className, -3) == 'Mob')
                $this->loadClass($className);
        }

        // load scoped instances
        session_start();
        if (isset($_SESSION['sessionScoped'])) {
            $this->scopes['session'] = $_SESSION['sessionScoped'];
        }
    }

    public function stop() {
        $_SESSION['sessionScoped'] = $this->scopes['session'];
    }

    private function findMobs($directory) {
        $classes = array();
        $dh = dir($directory);
        while (false !== ($entry = $dh->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            if (is_dir("$directory/$entry")) {
                $classes = array_merge($classes, $this->findMobs("$directory/$entry"));
            } else if (substr($entry, -4) == '.php' && substr($entry, 0, 1) == strtoupper(substr($entry, 0, 1))) {
                $className = substr($entry, 0, strlen($entry) - 4);
                $classes[$className] = "$directory/$entry";
            }
        }
        $dh->close();
        return $classes;
    }

    public function loadClass($className) {
        if (class_exists($className, false) || interface_exists($className, false)) {
            return;
        }
        if (!isset($this->classes[$className])) {
            throw new Exception("Class not found $className.");
        }

        include($this->classes[$className]);

        if (substr($className, -3) != 'Mob') {
            return;
        }

        $class = array(
            'name' => $className,
            'running' => false,
            'definition' => new ReflectionClass($className)
        );

        if ($class['definition']->isAbstract()) return;
        if ($class['definition']->isInterface()) return;

        $class['annotations'] = $this->parseAnnotations($class['definition']->getDocComment());


        $class['aliases'] = array(strtolower(substr($className, 0, 1)).substr($className, 1));
        $class['aliases'] = array_merge($class['aliases'], $this->getClassNames($class['definition']));
        $class['aliases'] = array_merge($class['aliases'], $class['definition']->getInterfaceNames());

        $class['scope'] = 'request';

        foreach ($class['annotations'] as $annotation) {
            if ($annotation['name'] == 'scope') {
                $class['scope'] = $annotation['payload'];
            } elseif ($annotation['name'] == 'name') {
                if (!in_array($annotation['payload'], $class['aliases'])) {
                    $class['aliases'][] = $annotation['payload'];
                }
            }
        }

        $class['properties'] = array();
        foreach ($class['definition']->getProperties() as $property) {
            if (substr($property->getName(), 0, 2) == '__') {
                continue;
            }
            $class['properties'][$property->getName()] = array();
            $class['properties'][$property->getName()]['name'] = $property->getName();
            $class['properties'][$property->getName()]['definition'] = $property;
            $class['properties'][$property->getName()]['annotations'] = $this->parseAnnotations($property->getDocComment());
        }

        $class['methods'] = array();
        foreach ($class['definition']->getMethods() as $method) {
            if (substr($method->getName(), 0, 2) == '__') {
                continue;
            }
            $class['methods'][$method->getName()] = array();
            $class['methods'][$method->getName()]['name'] = $method->getName();
            $class['methods'][$method->getName()]['definition'] = $method;
            $class['methods'][$method->getName()]['annotations'] = $this->parseAnnotations($method->getDocComment());
        }

        foreach ($class['annotations'] as $annotation) {
            if (!isset($this->annotations[$annotation['name']])) {
                $this->annotations[$annotation['name']] = array(
                    'classes' => array(),
                    'methods' => array(),
                    'properties' => array()
                );
            }
            $this->annotations[$annotation['name']]['classes'][] = $className;
        }

        foreach ($class['properties'] as $property) {
            foreach ($property['annotations'] as $annotation) {
                if (!isset($this->annotations[$annotation['name']])) {
                    $this->annotations[$annotation['name']] = array(
                        'classes' => array(),
                        'methods' => array(),
                        'properties' => array()
                    );
                }
                $this->annotations[$annotation['name']]['methods'][] = $property['name'];
            }
        }

        foreach ($class['methods'] as $method) {
            foreach ($method['annotations'] as $annotation) {
                if (!isset($this->annotations[$annotation['name']])) {
                    $this->annotations[$annotation['name']] = array(
                        'classes' => array(),
                        'methods' => array(),
                        'properties' => array()
                    );
                }
                $this->annotations[$annotation['name']]['methods'][] = $method['name'];
            }
        }

        $class['injections'] = array();
        foreach ($class['properties'] as $property) {
            foreach ($property['annotations'] as $annotation) {
                if ($annotation['name'] == 'inject') {
                    $listInjection = false;
                    $dependencyName = $property['name'];
                    if (!empty($annotation['payload'])) {
                        $payload = explode(' ', $annotation['payload']);
                        if ($payload[0] == 'array') {
                            $listInjection = true;
                            array_shift($payload);
                        }
                        $dependencyName = $payload[0];
                    }
                    $class['injections'][$property['name']] = array(
                        'name' => $dependencyName,
                        'list' => $listInjection
                    );
                }
            }
        }

        $this->mobs[$className] = $class;

        foreach ($class['aliases'] as $alias) {
            $this->registerAlias($className, $alias);
        }
    }

    private function getClassNames($class) {
        $parents = array($class->getName());
        $parent = $class->getParentClass();
        if ($parent) {
            $parents += $this->getClassNames($parent);
        }
        return $parents;
    }

    private function parseAnnotations($comment) {
        $annotations = array();

        $lines = explode("\n", $comment);
        foreach ($lines as $line) {
            $line = trim($line);
            $pattern = '/^\* @([a-zA-Z0-9]+)(.*)$/';
            if (preg_match($pattern, $line, $matches)) {
                $name = $matches[1];
                if (count($matches) > 2) {
                    $payload = $matches[2];
                } else {
                    $payload = '';
                }
                $annotations[] = array(
                    'name' => $name,
                    'payload' => trim($payload)
                );
            }
        }

        return $annotations;
    }

    private function registerAlias($className, $alias) {
        if (!isset($this->mobs[$className])) {
            throw new Exception("Mob $className not found.");
        }
        if (!isset($this->aliases[$alias])) {
            $this->aliases[$alias] = array();
        }
        $this->aliases[$alias][] = $className;
    }

    public function getDefinition($className) {
        if (!isset($this->mobs[$className])) {
            throw new Exception("Class $className not found.");
        }
        return $this->mobs[$className];
    }

    public function getMob($alias) {
        if (!isset($this->aliases[$alias])) {
            throw new Exception("Mob $alias not found.");
        }
        if (count($this->aliases[$alias]) != 1) {
            throw new Exception("Multiple mobs for $alias found.");
        }
        return new MobProxy($this->aliases[$alias][0]);
    }

    public function getMobs($alias) {
        if (!isset($this->aliases[$alias])) {
            return array();
        }
        $mobs = array();
        foreach ($this->aliases[$alias] as $className) {
            $mobs[] = new MobProxy($className);
        }
        return $mobs;
    }

    public function callMethod($className, $method, $arguments) {
        if (!isset($this->mobs[$className])) {
            throw new Exception("Class $className not found.");
        }
        $class = $this->mobs[$className];

        if (!isset($class['methods'][$method])) {
            throw new Exception("Method $method not found.");
        }
        $method = $class['methods'][$method];

        $instance = $this->getInstance($className);

        foreach ($class['injections'] as $propertyName => $injection) {
            if ($injection['list']) {
                $dependency = $this->getMobs($injection['name']);
            } else {
                $dependency = $this->getMob($injection['name']);
            }

            $property = $class['properties'][$propertyName];
            $property['definition']->setAccessible(true);
            $property['definition']->setValue($instance, $dependency);
        }

        $result = $method['definition']->invokeArgs($instance, $arguments);

        foreach ($class['injections'] as $propertyName => $injection) {
            $property = $class['properties'][$propertyName];
            $property['definition']->setAccessible(true);
            $property['definition']->setValue($instance, null);
        }

        return $result;
    }

    private function getInstance($className) {
        $mob = $this->mobs[$className];

        if (!isset($this->scopes[$mob['scope']])) {
            throw new Exception("Scope $mob[scope] does not exist.");
        }
        if (!isset($this->scopes[$mob['scope']][$className])) {
            $instance = $mob['definition']->newInstance();
            $this->scopes[$mob['scope']][$className] = $instance;

            $this->mobs[$className]['running'] = true;
        }
        return $this->scopes[$mob['scope']][$className];
    }

    private function hasAnnotation($obj, $annotation) {
        foreach ($obj['annotations'] as $annotation) {
            if ($annotation['name'] == $annotation) {
                return true;
            }
        }
        return false;
    }

    private function getAnnotation($obj, $annotation) {
        foreach ($obj['annotations'] as $annotation) {
            if ($annotation['name'] == $annotation) {
                return $annotation;
            }
        }
        throw new Exception("Annotation $annotation not found.");
    }

    public static function getSingleton() {
        return self::$SINGLETON;
    }
}

function __autoload($className) {
    $mobManager = MobManager::getSingleton();
    $mobManager->loadClass($className);
}