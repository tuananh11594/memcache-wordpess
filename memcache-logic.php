<?php 
    require_once( get_template_directory().'/memcache/conversation-config.php' );
    require_once( get_template_directory().'/memcache/memcache-config.php' );
    require_once( get_template_directory().'/memcache/memcache-query.php' );
    require_once( get_template_directory().'/memcache/conversation-query.php' );

    class MemcacheLogic {
        
        function __construct() {
            $this->conversationQuery = new ConversationQuery();
            
            $this->memcacheEnabled = MEMCACHE_ENABLE;
            if ( $this->memcacheEnabled ) {
                $this->memcacheQuery = new MemcacheQuery( MEMCACHE_HOST, MEMCACHE_PORT );
                $this->turnOffMemcache(!$this->memcacheQuery->connectMemcache());
                add_action( 'transition_post_status', array( $this,'updateMemcacheAfterAddEditDeletePost' ), 10, 3 );
                $this->setDataMemcacheWithApi();
            }
        }

        function __destruct() {
            if ( $this->memcacheEnabled ) {
                $this->memcacheQuery->closeMemcache();
            }
        }

        function setDataMemcacheWithApi() {
            if (isset($_GET['asikt-homepage-memcache'])) {
                $this->setDataConversationHomepageToMemcache();
            }
        }

        //NOTE: This function is currently not in use. If we want to load "View More" data from memcache in ajax-calls.php, this function might be useful
        // function getMoreDataConversations( $offset ) {
        //     $totalCached = POSTS_HOMEPAGE_NUM_OF_BLOCKS * POSTS_HOMEPAGE_BLOCKSIZE + POSTS_VIEWMORE_PAGES_TO_CACHE * POSTS_VIEWMORE_PAGESIZE;
        //     if ( $offset < $totalCached) {
        //         return data
        //     }
        // }

        function showDebugMemcache( $content ) {
            if ( SHOW_DEBUG_MEMCACHE ) {
                var_dump( $content );
            }
        }

        function turnOffMemcache( $paramCheck ) {
            if ($paramCheck) {
                $this->memcacheEnabled = false;
            }
        }

        function setDataToMemcacheAndCheckSuccess( $data ) {
            $setDataMemcacheSuccess = $this->memcacheQuery->setMemcache( 'asikt_conversation_data_memcache', $data, MEMCACHE_TIME_CACHE_CONVERSATION );
            $this->turnOffMemcache( !$setDataMemcacheSuccess );
        }

        function setDataVote( &$data, $post_id) {
            $data[ 'post' ][ $post_id ][ 'upvotes' ] = get_conversation_votes( $post_id, 1 );
            $data[ 'post' ][ $post_id ][ 'downvotes' ] = get_conversation_votes( $post_id, 0 );
        }

        function setDataConversation( &$conversationData, $query ) {
            if ( $query->have_posts() ): while ( $query->have_posts() ) : $query->the_post();
                $current_post_id = $query->post->ID;
                // $conversationData[ 'post' ][ 'total-post' ] += 1;
                $conversationData[ 'post' ][ $current_post_id ][ 'has-data' ] = 1;
                $conversationData[ 'post' ][ $current_post_id ][ 'children' ] = $this->conversationQuery->queryConversationChildren( $current_post_id );
                $dataPostChild = $this->conversationQuery->queryConversationChildren( $current_post_id );

                if ( $dataPostChild->have_posts() ): while ( $dataPostChild->have_posts() ) : $dataPostChild->the_post();
                    $child_post_id = $dataPostChild->post->ID;
                    $conversationData[ 'children' ][ $child_post_id ] = $this->conversationQuery->queryConversationChildren( $current_post_id );
                    endwhile;
                endif;
                $this->setDataVote( $conversationData, $current_post_id );
                endwhile;
            endif;
        }

        public function setDataConversationHomepageToMemcache() {
            if ( $this->memcacheEnabled ) {
                //Posts on homepage
                for( $block = 1; $block <= POSTS_HOMEPAGE_NUM_OF_BLOCKS; $block++ ) :
                    $queryBlock = $this->conversationQuery->queryConversations( ( $block - 1 ) * POSTS_HOMEPAGE_BLOCKSIZE, POSTS_HOMEPAGE_BLOCKSIZE );
                    if ( !is_null( $queryBlock ) ) {
                        $conversationData[ "block-".$block ][ 'posts' ] = $queryBlock;
                        $this->setDataConversation($conversationData, $queryBlock);
                    }else{
                        return;
                    };
                endfor;

                //Posts appear when click "View More"
                $offset = POSTS_HOMEPAGE_NUM_OF_BLOCKS * POSTS_HOMEPAGE_BLOCKSIZE;
                for( $page = 1; $page <= POSTS_VIEWMORE_PAGES_TO_CACHE; $page++ ) :
                    $queryPage = $this->conversationQuery->queryConversations( $offset + ($page - 1) * POSTS_VIEWMORE_PAGESIZE, POSTS_VIEWMORE_PAGESIZE );
                    if ( !is_null( $queryPage ) ) {
                        $conversationData[ "offset-".$offset ][ 'posts' ] = $queryPage;
                        $this->setDataConversation($conversationData, $queryPage);
                    }else{
                        break;
                    };
                endfor;

                //Store data into memcache
                if(!is_null( $conversationData )){
                    $this->setDataToMemcacheAndCheckSuccess($conversationData);
                }
            }
        }

        function makesureDataConversationHomepageRetrievedFromMemcache() {
            if ( is_null( $this->dataConversationMemcache ) ) {

                $this->retrieveDataConversationHomepageFromMemcache();
            }
        }

        function updateMemcacheAfterAddEditDeletePost( $new_status, $old_status, $post ) {
            if ( $this->memcacheEnabled ) {
                //Check if the post which was Added, Deleted or Updated is in the list of posts showing on homepage
                //To do this, get cached homepage data from memcache to check
                $this->makesureDataConversationHomepageRetrievedFromMemcache();

                if ( !is_null( $this->dataConversationMemcache ) ) {
                    if ( 
                        //Check if the post which was Deleted (status changed to Trash) or Updated is in the list of posts showing on homepage
                        !is_null( $this->dataConversationMemcache[ 'post' ][ $post->ID ] )
    
                        //Check if the post which was Deleted (status changed to Trash) or Updated is one of the child posts on the homepage
                        || !is_null( $this->dataConversationMemcache[ 'children' ][ $post->ID ] )
    
                        //Check if the post was Published (as it may appear on the homepage)
                        || ( $new_status == 'publish' && $old_status != 'publish' )
    
                        //Check if the post's parent was changed and the new value of the post's parent is one of the posts on the homepage
                        || !is_null( $this->dataConversationMemcache[ 'post' ][ $post->post_parent ] )
    
                        //Check if the post's parent was changed and the new value of the post's parent is one of the child posts on the homepage 
                        || !is_null( $this->dataConversationMemcache[ 'children' ][ $post->post_parent ] )
                        ) {
                        $this->setDataConversationHomepageToMemcache();
                    }
                }
            }
        }

        public function updateMemcacheAfterVotePost( $post_id ) {
            if ( $this->memcacheEnabled ) {
                //Get cached homepage data from memcache to check
                $this->makesureDataConversationHomepageRetrievedFromMemcache();

                //Check if the post which was Voted is in the list of posts showing on homepage
                if ( !is_null( $this->dataConversationMemcache ) ) {
                    if ( !is_null( $this->dataConversationMemcache[ 'post' ][ $post_id ] ) ) {
                        $this->setDataVote($this->dataConversationMemcache, $post_id);
                        $this->setDataToMemcacheAndCheckSuccess($this->dataConversationMemcache);
                    }
                }
            }
        }

        private function retrieveDataConversationHomepageFromMemcache() {
            $this->dataConversationMemcache = null;

            if ( $this->memcacheEnabled ) {

                $this->dataConversationMemcache = $this->memcacheQuery->getMemcache( 'asikt_conversation_data_memcache' );

                if ( $this->dataConversationMemcache == false ){
                    $this->dataConversationMemcache = null;
                }  
            }
    
            return $this->dataConversationMemcache;
        }



        public function getDataConversations( $block ) {
            if ( $this->memcacheEnabled ) {
                $this->makesureDataConversationHomepageRetrievedFromMemcache();
    
                if ( !is_null( $this->dataConversationMemcache ) ) {

                    $this->showDebugMemcache("Memcache Debug: Memcache returned data Conversations");
                    // var_dump($this->dataConversationMemcache[ 'post' ][ 'total-post' ]);
                    return $this->dataConversationMemcache[ "block-".$block ][ 'posts' ]; 
                }
            }

            return $this->conversationQuery->queryConversations( ($block - 1) * POSTS_HOMEPAGE_BLOCKSIZE, POSTS_HOMEPAGE_BLOCKSIZE );
        }

        public function getDataConversationChildren( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $this->makesureDataConversationHomepageRetrievedFromMemcache();
                    
                if ( !is_null( $this->dataConversationMemcache ) && $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'has-data' ] ) {
                    $this->showDebugMemcache("Memcache Debug: Memcache returned data Conversations Children");
                    return $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'children' ];   
                }
            }

            return $this->conversationQuery->queryConversationChildren( $post_id );
        }

        public function getDataConversationUpVotes( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $this->makesureDataConversationHomepageRetrievedFromMemcache();
    
                if ( !is_null( $this->dataConversationMemcache ) && $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'has-data' ] ) {
                    // $this->showDebugMemcache("Memcache Debug: Memcache returned data Votes");
                    return $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'upvotes' ];  
                }
            }

            return get_conversation_votes( $post_id, 1 );
        }

        public function getDataConversationDownVotes( $post_id ) {
            if ( $this->memcacheEnabled ) {
                $this->makesureDataConversationHomepageRetrievedFromMemcache();
    
                if ( !is_null( $this->dataConversationMemcache ) && $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'has-data' ] ) {
                    return $this->dataConversationMemcache[ 'post' ][ $post_id ][ 'downvotes' ];   
                }
            }

            return get_conversation_votes( $post_id, 0 );
        }
    }
?>