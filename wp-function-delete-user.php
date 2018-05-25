<?php 
	//TODO: Refactor and use the following function for GDPR features
	function delete_user_data( $user_id, $reassigned_user_id = null, $isDeleteAllPostWithUserID = false, $isAnonymousAllPost = true) {
		global $wpdb;

		$error = null;

		if (! is_numeric( $user_id )){
			$error = "Type of user_id is not number!";
			return $error;
		}

		$user = new WP_User( $user_id );

		if (!$user->exists()) {
			$error = "User does not exists!";
			return $error;
		}

		if ( null !== $reassigned_user_id ) {
			if (! is_numeric( $reassigned_user_id )){
				$error = "Type of reassign is not number!";
				return $error;
			}

			$userReassign = new WP_User( $reassigned_user_id );

			if (!$userReassign->exists()) {
				$error = "User to reassign does not exists!";
				return $error;
			}
		}

		if (! is_bool( $isAnonymousAllPost )){
			$error = "Type of isAnonymousAllPost is not boolean!";
			return $error;
		}

		if (! is_bool( $isDeleteAllPostWithUserID )){
			$error = "Type of isDeleteAllPostWithUserID is not boolean!";
			return $error;
		}

		$wpdb->query("START TRANSACTION;");

		if ($isDeleteAllPostWithUserID){
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d ", $user_id ) );
		
			if ( $post_ids ) {
				foreach ( $post_ids as $post_id )
					$valueDeletePost = wp_delete_post( $post_id );
					if ($valueDeletePost === false) {
						$wpdb->query("ROLLBACK;");					
						$error = 'Error when deleting post! ';
						return $error;
					}
			}
 
			// Clean links
			$link_ids = $wpdb->get_col( $wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $user_id) );
 
			if ( $link_ids ) {
				foreach ( $link_ids as $link_id )
					wp_delete_link($link_id);
			}
		} else {

			$dataPostMetaOfUserID = $wpdb->get_results("SELECT `ID` FROM `wp_posts` where `post_author` = ".$user_id."");
			foreach($dataPostMetaOfUserID as $item) {

				if ($isAnonymousAllPost) {
					/*Delete post meta with post_id*/  							
					$wpdb->get_results("DELETE FROM `wp_postmeta` where `post_id` = ".$item->ID."");
					if ($wpdb->last_error != null) {
						$wpdb->query("ROLLBACK;");					
						$error = 'Error when deleting post meta data! ';
						return $error;
					}

					/*Insert new post meta with key _author_anonymous and value 1*/  	
					$wpdb->get_results("INSERT INTO `wp_postmeta`(`post_id`, `meta_key`, `meta_value`) VALUES (".$item->ID.",'_author_anonymous', 1)");
					if ($wpdb->last_error != null) {
						$wpdb->query("ROLLBACK;");					
						$error = 'Error when updating post meta data value! ';
						return $error;
					}
				} else {
					$wpdb->get_results("DELETE FROM `wp_postmeta` where `post_id` = ".$item->ID." AND `meta_key` != '_author_anonymous'");
					if ($wpdb->last_error != null) {
						$wpdb->query("ROLLBACK;");					
						$error = 'Error when deleting post meta data! ';
						return $error;
					}
				}
	
				clean_post_cache( $item->ID );
			}

			if ($reassigned_user_id !== null) {
				/*Update post with new author*/  			
				$valueUpdatePostNewAuthor = $wpdb->update( $wpdb->posts, array('post_author' => $reassigned_user_id), array('post_author' => $user_id) );	
				if ($valueUpdatePostNewAuthor === false) {
					$wpdb->query("ROLLBACK;");					
					$error = 'Error when updating post to new author! ';
					return $error;
				}

				/*Update link with new author*/  
				$link_ids = $wpdb->get_col( $wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $user_id) );
				$updateLink = $wpdb->update( $wpdb->links, array('link_owner' => $reassigned_user_id), array('link_owner' => $user_id) );
				if ($updateLink === false) {
					$wpdb->query("ROLLBACK;");					
					$error = 'Error when updating post link to new author! ';
					return $error;
				}
				if ( ! empty( $link_ids ) ) {
					foreach ( $link_ids as $link_id )
						clean_bookmark_cache( $link_id );
				}
			}

		}
			
		/*Delete user meta with user_id*/  		
		$wpdb->get_results("DELETE FROM `wp_usermeta` where `user_id` = ".$user_id."");
		if ($wpdb->last_error != null) {
			$wpdb->query("ROLLBACK;");	
			$error = 'Error when deleting user meta with user_id!';			
			return $error;
		}

		/*Delete user with user_id*/  
		$wpdb->get_results("DELETE FROM `wp_users` where `ID` = ".$user_id."");
		if ($wpdb->last_error != null) {
			$wpdb->query("ROLLBACK;");	
			$error = 'Error when deleting user with user_id!';			
			return $error;
		}

		clean_user_cache( $user );

		//Commit transaction
		$wpdb->query("COMMIT;");
		return true;
	}
?>
