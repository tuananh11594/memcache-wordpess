<?php 

    class MemcacheQuery {

        function __construct( $host, $port ) {
            $this->host = $host;
            $this->port = $port;
        }

        public function connectMemcache() {
            $this->memcache = new Memcache;
            return $this->memcache->connect($this->host, $this->port);
        }
        
        public function closeMemcache() {
            if ( $this->memcache != null ) {
                $this->memcache->close();
            }
        }

        public function setMemcache( $key, $data, $timeCache ) {
            $this->memcache->set( $key, $data, false, $timeCache ) or die( "Failed to save data at the memcache server" );
        }

        public function getMemcache( $key ) {
            $dataMemcache = $this->memcache->get( $key );
            return $dataMemcache;
        }

}
?>