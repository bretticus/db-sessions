<?php

/**
 * Database Session Hander
 *
 * @author Brett Millett <bmillett@olwm.com>
 * @version 1.0
 */
class SessionHandlerEncrypted extends PDOSessionHandler {
    
    protected $key = NULL;

    /**
     * 
     * @param PDO $db
     * @param string $key
     */
    public function __construct(PDO $db, $key) {
        $this->key = substr($key, 0, 24); //make sure no longer than 24 chars.
        parent::__construct($db);
    }

    /**
     * 
     * @param string $session_id
     * @return string
     */
    public function read($session_id) {
        $data = parent::read($session_id);
        return mcrypt_decrypt(MCRYPT_3DES, $this->key, base64_decode($data), MCRYPT_MODE_ECB);
    }

    /**
     * 
     * @param string $session_id
     * @param string $session_data
     * @return boolean
     */
    public function write($session_id, $session_data) {
        $session_data = mcrypt_encrypt(MCRYPT_3DES, $this->key, $session_data, MCRYPT_MODE_ECB);
        return parent::write($session_id, base64_encode($session_data));
    }

}