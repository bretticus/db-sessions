<?php

/**
 * Session handler interface
 *
 * @author bmillett
 */
interface SessionHandlerInterface {

    /**
     * @return boolean
     */
    public function close();

    /**
     *
     * @param string $session_id
     * @return boolean
     */
    public function destroy($session_id);

    /**
     *
     * @param string $maxlifetime
     * @return boolean
     */
    public function gc($maxlifetime);

    /**
     *
     * @param string $save_path
     * @param string $name
     * @return boolean
     */
    public function open($save_path, $name);

    /**
     *
     * @param string $session_id
     * @return string
     */
    public function read($session_id);

    /**
     *
     * @param string $session_id
     * @param string $session_data
     * @return boolean
     */
    public function write($session_id, $session_data);
}