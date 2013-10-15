<?php

require_once 'session_handler.php';

/**
 * Database Session Hander
 *
 * @author Brett Millett <bmillett@olwm.com>
 * @version 1.0
 *
 * @property boolean $available If memcache passed is legit.
 * @property Memcache $memcache Instance of memcache.
 */
class SessionHandlerMemcache extends SessionHandler {

    protected $available = FALSE;
    protected $memcache = NULL;
    protected $gc_maxlifetime = 0;

    /**
     *
     * @param PDO $db
     * @param string $key
     */
    public function __construct(PDO $db, $memcache) {

        if ($memcache instanceof Memcache) {
            // attempt to see if memcached is available
            $stats = @$memcache->getExtendedStats();
            $this->available = (is_array($stats) && count($stats) > 0);

            // make sure we have at least one memcache server.
            $has_servers = FALSE;
            if ($this->available) {
                foreach ($stats as $host => $data) {
                    if ($data !== FALSE) {
                        $has_servers = TRUE;
                        break;
                    }
                }
            }
            if (!$has_servers) {
                if ($memcache->addServer($_SERVER['SERVER_ADDR'], '11211')) {
                    $stats = @$memcache->getExtendedStats();
                    foreach ($stats as $host => $data) {
                        if ($data !== FALSE) {
                            $this->available = TRUE;
                            break;
                        }
                    }
                }
            }

            // get current garbage collection max TTL.
            $this->gc_maxlifetime = (int) ini_get('session.gc_maxlifetime');

            // make sure we never set 0.
            if (empty($this->gc_maxlifetime))
                $this->gc_maxlifetime = 1440;

            // set property to instance
            $this->memcache = & $memcache;
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
                $this->memcache->delete($session_id);
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
        $this->session_id = $session_id; // set in case we don't call parent method.
        if ($this->available) {
            try {
                $cached = $this->memcache->get($session_id);
                /**
                 * Get data from database in scenario where memcached has been
                 * restarted or cleared. Or when current server in cluster does
                 * not have memcached copy yet.
                 */
                if ($cached === FALSE) {
                    $cached = parent::read($session_id);
                    // add result to memory for next time if we have something to add.
                    if (!empty($cached)) {
                        $ttl = $this->ttl($session_id);
                        // add result to memory if we get a positive TTL only.
                        if ($ttl !== FALSE && $ttl > 0)
                            $this->memcache->add($session_id, $cached, FALSE, $ttl);
                    }
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
        $timestamp = time();
        $result = parent::write($session_id, $session_data, $timestamp);
        if ($this->available && $result) {
            // keep sync with database.
            $ttl = $this->gc_maxlifetime - (time() - $timestamp);
            try {
                // replace result in memory.
                $cached = $this->memcache->replace($session_id, $session_data, FALSE, $ttl);
                // if we couldn't replace cached result, add it.
                if ($cached === FALSE) {
                    $this->memcache->add($session_id, $session_data, FALSE, $ttl);
                }
            } catch (MemcachedException $e) {
                $this->email_admins($e->getMessage());
            } catch (Exception $e) {
                $this->email_admins($e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Get seconds left until original garbage collection should be called for
     * session indicated by session_id.
     *
     * @param string $session_id
     * @return mixed Return FALSE when no matching record found.
     */
    protected function ttl($session_id) {
        try {
            $stmt = $this->prepare('SELECT `timestamp` FROM `%s` WHERE id = ?');
            $stmt->execute(array($session_id));
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            if (!empty($result)) {
                $gc_ttl_limit = $result->timestamp + $this->gc_maxlifetime;
                if (time() >= $gc_ttl_limit) {
                    // gc should have deleted by now.
                    return FALSE;
                } else {
                    // seconds until gc should be called.
                    return $gc_ttl_limit - time();
                }
            }
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        }
        return FALSE;
    }

}