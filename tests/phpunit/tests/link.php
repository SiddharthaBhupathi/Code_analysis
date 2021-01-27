<?php
/**
 * @group link
 */
class Tests_Link extends WP_UnitTestCase {

	function _get_pagenum_link_cb( $url ) {
		return $url . '/WooHoo';
	}

	/**
	 * @ticket 8847
	 */
	function test_get_pagenum_link_case_insensitivity() {
		$old_req_uri = $_SERVER['REQUEST_URI'];

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		add_filter( 'home_url', array( $this, '_get_pagenum_link_cb' ) );
		$_SERVER['REQUEST_URI'] = '/woohoo';
		$paged                  = get_pagenum_link( 2 );

		remove_filter( 'home_url', array( $this, '_get_pagenum_link_cb' ) );
		$this->assertSame( $paged, home_url( '/WooHoo/page/2/' ) );

		$_SERVER['REQUEST_URI'] = $old_req_uri;
	}

	function test_wp_get_shortlink() {
		$post_id  = self::factory()->post->create();
		$post_id2 = self::factory()->post->create();

		// Basic case.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$GLOBALS['post'] = get_post( $post_id );

		// Global post is set.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink() );

		// Not the global post.
		$this->assertSame( get_permalink( $post_id2 ), wp_get_shortlink( $post_id2, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set, once again.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		// With a permalink structure set, get_permalink() will no longer match.
		$this->assertNotEquals( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		// Global post and permalink structure are set.
		$GLOBALS['post'] = get_post( $post_id );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink() );
	}

	function test_wp_get_shortlink_with_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		// Basic case.
		// Don't test against get_permalink() since it uses ?page_id= for pages.
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 26871
	 */
	function test_wp_get_shortlink_with_home_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_post_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 day' ) ),
			)
		);

		$non_pretty_permalink = add_query_arg( 'p', $p, trailingslashit( home_url() ) );

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_cpt_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'wptests_pt', array( 'public' => true ) );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_type'   => 'wptests_pt',
				'post_date'   => strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 day' ) ),
			)
		);

		$non_pretty_permalink = add_query_arg(
			array(
				'post_type' => 'wptests_pt',
				'p'         => $p,
			),
			trailingslashit( home_url() )
		);

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 1914
	 */
	public function test_unattached_attachment_has_a_pretty_permalink() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( home_url( user_trailingslashit( $attachment->post_name ) ), get_permalink( $attachment_id ) );
		// Ensure the URL does not 404.
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );
	}

	/**
	 * @ticket 1914
	 */
	public function test_attachment_attached_to_non_existent_post_type_has_a_pretty_permalink() {
		global $wp_post_types;

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'not_a_post_type', array( 'public' => true ) );

		flush_rewrite_rules();

		$post_id = self::factory()->post->create( array( 'post_type' => 'not_a_post_type' ) );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( get_permalink( $post_id ) . user_trailingslashit( $attachment->post_name ), get_permalink( $attachment_id ) );
		// Ensure the URL does not 404.
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );

		foreach ( $wp_post_types as $id => $pt ) {
			if ( 'not_a_post_type' === $pt->name ) {
				unset( $wp_post_types[ $id ] );
				break;
			}
		}

		$date = explode( ' ', str_replace( array( '-', ':' ), ' ', $attachment->post_date ) );
		$this->assertSame( home_url( user_trailingslashit( "/{$date[0]}/{$date[1]}/{$date[2]}/attachment/{$attachment->post_name}" ) ), get_permalink( $attachment_id ) );
		// Ensure the URL does not 404.
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );
	}
}
