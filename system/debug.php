<?php
/**
 * @author Tobias Sarnowski
 */

if (!function_exists('debug')) {

    /**
     * Helper function to fastly debug things on the fly - a better alternative
     * to print_r().
     *
     * @return void
     */
    function debug() {
        if (strtolower(ini_get('display_errors')) != '1') {
            return;
        }

        $trace = debug_backtrace();
        $call = $trace[0];

        $root = realpath(dirname(__FILE__));

        if (substr($call['file'], 0, strlen($root)) == $root) {
            $relative = substr($call['file'], strlen($root));
        } else {
            $relative = $call['file'];
        }

        if (count($trace) > 1) {
            $caller = $trace[1];
            if (isset($caller['class'])) {
                $method = ' ['.$caller['class'].'-&gt;'.$caller['function'].'('.implode(',',$caller['args']).')]';
            } else {
                $method = ' ['.$caller['function'].'('.implode(',',$caller['args']).')]';
            }
        }

        echo '<div style="border: 1px solid orange; background-color: white; padding: 3px;">';
        echo '<p style="color: black; font-weight: bold; margin: 3px 5px">['.date('H:i:s.u').'] '.$relative.' ('.$call['line'].')'.(isset($method)?$method:'').'</p>';
        foreach ($call['args'] as $arg) {
            if ($arg == '') {
                $arg = '(empty)';
            } elseif ($arg === null) {
                $arg = '(null)';
            }
            echo '<pre style="border: 1px dashed black; background-color: white; color: black; margin: 3px 5px; padding: 4px">'.print_r($arg, true).'</pre>';
        }
        echo '</div>';
        if (ob_get_length() !== false) {
            ob_flush();
        }
        flush();
    }
}