<?php

/**
 * Database Session Hander
 *
 * @author Brett Millett <bmillett@olwm.com>
 * @version 1.0
 *
 * @property boolean $available If memcached passed is legit.
 * @property Memcached $memcached Instance of memcached.
 */
class SessionHandlerMemcached extends PDOSessionHandler {

    protected $available = FALSE;
    protected $memcached = NULL;

    /**
     *
     * @param PDO $db
     * @param string $key
     */
    public function __construct(PDO $db, $memcached) {

        if ($memcached instanceof Memcached) {
            // attempt to see if memcached is available
            $servers = @$memcached->getServerList();
            $this->available = is_array($servers);

            if ($this->available && !count($servers)) {
                $this->available = $memcached->addServer($_SERVER['SERVER_ADDR'], '11211');
            }

            // set property to instance
            $this->memcached = & $memcached;
        }

        parent::__construct($db);
    }

    /**
     *
     * @param string $session_id
     * @return boolean
     */
    public function destroy($session_id) {
        $result = parent::destroy($session_id);
        if ($this->available && $result) {
            try {
                // add result to memory for next time.
                $this->memcached->delete($session_id);
            } catch (MemcachedException $e) {
                $this->email_admins($e->getMessage());
            } catch (Exception $e) {
                $this->email_admins($e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Get cached value from memory first.
     *
     * @param string $session_id
     * @return string
     */
    public function read($session_id) {
        if ($this->available) {
            try {
                $cached = $this->memcached->get($session_id);
                if ($cached === FALSE) {
                    $cached = parent::read($session_id);
                    // add result to memory for next time.
                    $this->memcached->add($session_id, $cached, $this->ttl($session_id));
                }
            } catch (MemcachedException $e) {
                $this->email_admins($e->getMessage());
                // fallback to parent if memcached exception.
                $cached = parent::read($session_id);
            } catch (Exception $e) {
                $this->email_admins($e->getMessage());
                // fallback to parent if any exception.
                $cached = parent::read($session_id);
            }
        } else {
            // fallback to parent if no memcached availability.
            $cached = parent::read($session_id);
        }
        return $cached;
    }

    /**
     *
     * @param string $session_id
     * @param string $session_data
     * @return boolean
     */
    public function write($session_id, $session_data) {
        // always write to database first so we have concurrency among servers.
        $result = parent::write($session_id, $session_data);
        if ($this->available && $result) {
            try {
                // add result to memory for next time.
                $this->memcached->add($session_id, $cached, $this->ttl($session_id));
            } catch (MemcachedException $e) {
                $this->email_admins($e->getMessage());
            } catch (Exception $e) {
                $this->email_admins($e->getMessage());
            }
        }
        return $result;
    }

    /**
     *
     * @param string $session_id
     * @return mixed NULL if timestamp record is not returned.
     */
    protected function ttl($session_id) {
        try {
            $stmt = $this->prepare('SELECT `timestamp` FROM `%s` WHERE id = ?');
            $stmt->execute(array($session_id));
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            if (!empty($result)) {
                return time() - $result->timestamp;
            }
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        }
        return NULL;
    }

}