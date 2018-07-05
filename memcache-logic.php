<?php 

    require_once( get_template_directory().'/memcache/memcache-config.php' );
    require_once( get_template_directory().'/memcache/memcache-query.php' );
    require_once( get_template_directory().'/memcache/conversation-query.php' );

    class MemcacheLogic {
        
        function __construct() {
            $this->numberOfBlocks = 2;
            $this->conversationQuery = new ConversationQuery();            
            
            $this->memcacheEnabled = MEMCACHE_ENABLE;
            if ( $this->memcacheEnabled ) {
                $this->memcacheQuery = new MemcacheQuery( MEMCACHE_HOST, MEMCACHE_PORT );
                if ( !$this->memcacheQuery->connectMemcache() )
                {
                    $this->memcacheEnabled = false;
                }
            }

            add_action( 'delete_post', array( $this, 'updateDataMemcacheWhenEditOrDeletePost' ), 10, 1 );
            add_action( 'save_post', array( $this, 'updateDataMemcacheWhenEditOrDeletePost' ), 10, 1 );
            add_action( 'transition_post_status', array( $this,'updateMemcacheWhenAddNewPost' ), 10, 3 );
            
            if ( SET_DATA_TO_MEMCACHE ) {
                $this->setDataConversationHomepageToMemcache();                
            }
        }

        public function setDataConversationHomepageToMemcache() {
            
            if ( $this->memcacheEnabled ) {
                for( $block = 1; $block <= $this->numberOfBlocks; $block++ ) :
                  $query = $this->conversationQuery->queryConversations( $block );
                  if ( !is_null( $query ) ) {
                        $conversationData[ $block ][ 'posts' ] = $query;
                        if ( $query->have_posts() ): while ( $query->have_posts() ) : $query->the_post();
                            $current_post_id = $query->post->ID;
                            $conversationData[ 'post' ][ $current_post_id ][ 'children' ] = $this->conversationQuery->queryConversationChildren( $current_post_id );
                            $conversationData[ 'post' ][ $current_post_id ][ 'upvotes' ] = get_conversation_votes( $current_post_id, 1 );
		                    $conversationData[ 'post' ][ $current_post_id ][ 'downvotes' ] = get_conversation_votes( $current_post_id, 0 );
                            endwhile;
                        endif;

                        $this->memcacheQuery->setMemcache( 'asikt_conversation_data_memcache', $conversationData, MEMCACHE_TIME_CACHE_CONVERSATION );
                  
                    };
                endfor;
            }
        }

        public function updateDataMemcacheWhenEditOrDeletePost( $post_id ) {
            if ( is_null( $this->dataConversationMemcache ) ) {
                $this->retrieveDataConversationHomepageFromMemcache();
            }
            if ( !is_null($this->dataConversationMemcache[ 'post' ][ $post_id ] )) {
                $this->setDataConversationHomepageToMemcache();
            }
        }

        function updateMemcacheWhenAddNewPost( $new_status, $old_status, $post ) {
            if ( $new_status == 'publish' ) {
                if ( is_null( $this->dataConversationMemcache ) ) {
                    $this->retrieveDataConversationHomepageFromMemcache();
                }
                $this->setDataConversationHomepageToMemcache();                
            }
        }

        public function updateDataConversationVote( $post_id ){
            
            if ( $this->memcacheEnabled ) {
                if ( is_null( $this->dataConversationMemcache )) {
                    $this->retrieveDataConversationHomepageFromMemcache();
                }
                
                if ( !is_null( $this->dataConversationMemcache[ 'post' ][ $post_id ] )) {
                    $dataUpdate = $this->dataConversationMemcache;
                    $dataUpdate[ 'post' ][ $post_id ][ 'upvotes' ] = get_conversation_votes( $post_id, 1 );
                    $dataUpdate[ 'post' ][ $post_id ][ 'downvotes' ] = get_conversation_votes( $post_id, 0 );
                    $this->memcacheQuery->setMemcache( 'asikt_conversation_data_memcache', $dataUpdate, MEMCACHE_TIME_CACHE_CONVERSATION );          
                }
                
            }
        }

        private function retrieveDataConversationHomepageFromMemcache() {
            $this->dataConversationMemcache = $this->memcacheQuery->getMemcache( 'asikt_conversation_data_memcache' );
            if ( $this->dataConversationMemcache == false ){
                return null;
            }
            return $this->dataConversationMemcache;            
        }


        public function getDataConversations( $block ) {
            if ( $this->memcacheEnabled ) {
                if( !is_null( $this->retrieveDataConversationHomepageFromMemcache() )) {
                    // var_dump("Memcache Debug: Memcache return data Conversations");                                        
                    return $this->dataConversationMemcache[ $block ][ 'posts' ];                    
                }
            }

            return $this->conversationQuery->queryConversations( $block );
        }

        public function getDataConversationChildren( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $dataConversationChildren = $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'children' ];                
                if ( !is_null( $dataConversationChildren ) ) {
                    // var_dump("Memcache Debug: Memcache return data Conversations Children");                    
                    return $dataConversationChildren;
                }
            }
            
            return $this->conversationQuery->queryConversationChildren( $post_id );
        }

        public function getDataConversationUpVotes( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $dataConversationUpVotes = $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'upvotes' ];                
                if ( !is_null($dataConversationUpVotes )) {
                    // var_dump("Memcache Debug: Memcache return data Votes");
                    return $dataConversationUpVotes;
                }
            }
            
            return get_conversation_votes( $post_id, 1 );
        }

        public function getDataConversationDownVotes( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $dataConversationDownVotes = $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'downvotes' ];                
                if ( !is_null( $dataConversationDownVotes ) ) {
                    return $dataConversationDownVotes;
                }
            }

            return get_conversation_votes( $post_id, 0 );
        }
    }
?>