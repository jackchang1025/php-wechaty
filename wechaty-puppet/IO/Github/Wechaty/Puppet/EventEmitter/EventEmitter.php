<?php
/**
 * Created by PhpStorm.
 * User: peterzhang
 * Date: 2020/7/10
 * Time: 6:56 PM
 */
namespace IO\Github\Wechaty\Puppet\EventEmitter;

class EventEmitter {
    /**
     * @var array Events listeners
     */
    protected $listeners = array();

    /**
     * fire event
     *
     * @static
     * @param int|string $event
     * @param mixed|null $args
     * @return bool
     */
    public function emit(int|string $event, mixed $args = null) {

        $event = strtolower($event);

        if ($args !== null) {
            // Check arguments, set inline args more than 1
            $args = array_slice(func_get_args(), 1);
        } else {
            $args = array();
        }

        $allListeners = array();

        foreach ($this->listeners as $name => $listeners) {
            if (strpos($name, '*') === false || !self::match($name, $event)) {
                continue;
            }

            foreach ($this->listeners[$name] as &$listener) {
                $allListeners[$name][] = & $listener;
            }
        }

        if (!empty($this->listeners[$event])) {
            foreach ($this->listeners[$event] as &$listener) {
                $allListeners[$event][] = & $listener;
            }
        }

        $emitted = false;

        // Loop listeners for callback
        foreach ($allListeners as $name => $listeners) {
            $thisArgs = $args;
            if (str_contains($name, '*')) {
                $thisArgs[] = $event;
            }
            foreach ($listeners as &$listener) {
                if ($listener instanceof \Closure) {
                    // Closure Listener
                    call_user_func_array($listener, $thisArgs);
                    $emitted = true;
                } elseif (is_array($listener) && $listener[0] instanceof \Closure) {
                    if ($listener[1]['times'] > 0) {
                        // Closure Listener
                        call_user_func_array($listener[0], $thisArgs);
                        $emitted = true;
                        $listener[1]['times']--;
                    }
                }
            }
        }

        return $emitted;
    }

    /**
     * Attach a event listener
     *
     * @static
     * @param int|array|string $event
     * @param \Closure     $listener
     * @return $this
     */
    public function on(int|array|string $event, \Closure $listener): static
    {
        foreach ((array)$event as $e) {
            $this->listeners[strtolower($e)][] = $listener;
        }
        return $this;
    }

    /**
     * Attach a listener to emit once
     *
     * @param array|string $event
     * @param callable     $listener
     * @return $this
     */
    public function once($event, \Closure $listener) {
        foreach ((array)$event as $e) {
            $this->listeners[strtolower($e)][] = array($listener, array('times' => 1));
        }
        return $this;
    }

    /**
     * Attach a listener to emit many times
     *
     * @param array|string|int $event
     * @param int $times
     * @param \Closure|null $listener
     * @return $this
     */
    public function many(array|string|int $event, int $times = 1, \Closure $listener = null): static
    {
        foreach ((array)$event as $e) {
            $this->listeners[strtolower($e)][] = array($listener, array('times' => $times));
        }
        return $this;
    }

    /**
     * Alias for removeListener
     *
     * @param array|string $event
     * @param callable     $listener
     * @return $this
     */
    public function off($event, \Closure $listener) {
        foreach ((array)$event as $e) {
            $e = strtolower($e);
            if (!empty($this->listeners[$e])) {
                // Find Listener index
                if (($key = array_search($listener, $this->listeners[$e])) !== false) {
                    // Remove it
                    unset($this->listeners[$e][$key]);
                }
            }
        }
        return $this;
    }

    /**
     * Get listeners of given event
     *
     * @param string $event
     * @return array
     */
    public function listeners($event) {
        if (!empty($this->listeners[$event])) {
            return $this->listeners[$event];
        }
        return array();
    }

    /**
     * Attach a event listener
     *
     * @static
     * @param array|string $event
     * @param \Closure     $listener
     * @return $this
     */
    public function addListener($event, \Closure $listener) {
        return $this->on($event, $listener);
    }

    /**
     * Detach a event listener
     *
     * @static
     * @param string   $event
     * @param \Closure $listener
     * @return $this
     */
    public function removeListener($event, \Closure $listener) {
        return $this->off($event, $listener);
    }

    /**
     * Remove all listeners of given event
     *
     * @param string $event
     * @return $this
     */
    public function removeAllListeners($event = null) {
        if ($event === null) {
            $this->listeners = array();
        } else if (($event = strtolower($event)) && !empty($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }
        return $this;
    }

    /**
     * Match the pattern
     *
     * @param string $pattern
     * @param string $string
     * @return bool|int
     */
    protected static function match($pattern, $string) {
        return preg_match("#^" . strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.')) . "$#i", $string);
    }
}