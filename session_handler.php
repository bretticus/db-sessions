<?php

/**
 * Database Session Hander
 *
 * @author Brett Millett <bmillett@olwm.com>
 * @version 1.0
 */
class PDOSessionHandler implements SessionHandlerInterface {

    public $table = 'session_handler';

    protected $dbh = NULL;
    protected $session_id = NULL;
    protected $session_written = FALSE;

    const ADMIN_EMAIL = 'admin@domain.tld';

    /**
     * Automatically sets this instance to database session handler.
     *
     * @param PDO $db A PDO instance.
     */
    public function __construct(PDO $db) {
        $this->dbh = & $db;

        // Register this object as the session handler
        session_set_save_handler(
                array(&$this, 'open'), array(&$this, 'close'),
                array(&$this, 'read'), array(&$this, 'write'),
                array(&$this, 'destroy'), array(&$this, 'gc')
        );

        // the following prevents unexpected effects when using objects as save handlers
        register_shutdown_function('session_write_close');

        session_start();
    }

    /**
     * @return boolean
     */
    public function close() {
        /**
         * Keep session alive with db update where we will call this each method 
         * each time via session_write_close. This may not be neccessary.
         */        
        if (!empty($this->session_id) && !$this->session_written) {
            try {
                $stmt = $this->prepare('UPDATE `%s` SET `timestamp` = NOW() WHERE `id` = ?');
                $stmt->execute(array($session_id));
            } catch (PDOException $e) {
                $this->email_admins($e->getMessage());
            } catch (Exception $e) {
                $this->email_admins($e->getMessage());
            }
        }
        return TRUE;
    }

    /**
     *
     * @param string $session_id
     * @return boolean
     */
    public function destroy($session_id) {
        try {
            $stmt = $this->prepare('DELETE FROM `%s` WHERE `id` = ?');
            $stmt->execute(array($session_id));
            $destroyed = ($stmt->rowCount() > 0);
            if ($destroyed)
                $this->session_id = NULL;
            return $destroyed;
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        } catch (Exception $e) {
            $this->email_admins($e->getMessage());
        }
        return FALSE;
    }

    /**
     *
     * @param string $maxlifetime
     * @return boolean
     */
    public function gc($maxlifetime) {
        try {
            $stmt = $this->prepare('DELETE FROM `%s` WHERE `timestamp` < ?');
            $stmt->execute(array(time() - intval($maxlifetime)));
            return ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        } catch (Exception $e) {
            $this->email_admins($e->getMessage());
        }
        return FALSE;
    }

    /**
     *
     * @param string $save_path
     * @param string $name
     * @return boolean
     */
    public function open($save_path, $name) {
        if ($this->dbh instanceof PDO)
            return TRUE;
        return FALSE;
    }

    /**
     *
     * @param string $session_id
     * @return string
     */
    public function read($session_id) {
        $this->session_id = $session_id;
        try {
            $stmt = $this->prepare('SELECT `data` FROM `%s` WHERE id = ?');
            $stmt->execute(array($session_id));
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            return (empty($result)) ? '' : $result->data;
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        } catch (Exception $e) {
            $this->email_admins($e->getMessage());
        }
        return '';
    }

    /**
     *
     * @param string $session_id
     * @param string $session_data
     * @param integer $timestamp
     * @return boolean
     */
    public function write($session_id, $session_data, $timestamp = 0) {
        $this->session_written = TRUE;
        try {
            $stmt = $this->prepare('REPLACE INTO `%s` VALUES(?, ?, ?)');
            $stmt->execute(array($session_id, $session_data, ((int) $timestamp > 0) ? (int) $timestamp : time()));
            return ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            $this->email_admins($e->getMessage());
        } catch (Exception $e) {
            $this->email_admins($e->getMessage());
        }
        return FALSE;
    }

    protected function email_admins($message) {
        mail(self::ADMIN_EMAIL, __CLASS__ . ' Error', $message);
    }

    protected function prepare($query) {
        return $this->dbh->prepare(sprintf($query, $this->table));
    }

}