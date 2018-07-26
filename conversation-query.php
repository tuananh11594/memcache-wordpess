<?php 
    class ConversationQuery {

        public function queryConversations( $offset, $postPerPage ){
            $args = array (
                'post_type'		=> 'asikt',
                'post_status'	=> 'publish',
                'post_parent' 	=> 0,
                'posts_per_page'=> $postPerPage,
                'offset'=> $offset,
            );
            $query = new WP_Query( $args );
            return $query;
        }

        public function queryConversationChildren( $postID ){
            $args = array(
                'post_type' => 'asikt',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'order' => 'ASC',
                'post_parent' => $postID
            );
            
            // The Query
            $loop = new WP_Query( $args );
            return $loop;
        }
    }
?>