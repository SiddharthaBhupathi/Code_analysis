<?php
/**
 * Unit tests covering WP_HTML_Processor bookmark functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.4.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor_Bookmark extends WP_UnitTestCase {

	/**
	 * @ticked tbd
	 *
	 * @covers ::set_bookmark
	 */
	public function test_set_bookmark() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$processor->next_tag( 'li' );
		$this->assertTrue( $processor->set_bookmark( 'first li' ), 'Could not allocate a "first li" bookmark' );
		$processor->next_tag( 'li' );
		$this->assertTrue( $processor->set_bookmark( 'second li' ), 'Could not allocate a "second li" bookmark' );
		$this->assertTrue( $processor->set_bookmark( 'first li' ), 'Could not move the "first li" bookmark' );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::release_bookmark
	 */
	public function test_release_bookmark() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$processor->next_tag( 'li' );
		$this->assertFalse( $processor->release_bookmark( 'first li' ), 'Released a non-existing bookmark' );
		$processor->set_bookmark( 'first li' );
		$this->assertTrue( $processor->release_bookmark( 'first li' ), 'Could not release a bookmark' );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::has_bookmark
	 */
	public function test_has_bookmark_returns_false_if_bookmark_does_not_exist() {
		$processor = WP_HTML_Processor::create_fragment( '<div>Test</div>' );
		$this->assertFalse( $processor->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::has_bookmark
	 */
	public function test_has_bookmark_returns_true_if_bookmark_exists() {
		$processor = WP_HTML_Processor::create_fragment( '<div>Test</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'my-bookmark' );
		$this->assertTrue( $processor->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::has_bookmark
	 */
	public function test_has_bookmark_returns_false_if_bookmark_has_been_released() {
		$processor = WP_HTML_Processor::create_fragment( '<div>Test</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'my-bookmark' );
		$processor->release_bookmark( 'my-bookmark' );
		$this->assertFalse( $processor->has_bookmark( 'my-bookmark' ) );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_seek() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$processor->next_tag( 'li' );
		$processor->set_bookmark( 'first li' );

		$processor->next_tag( 'li' );
		$processor->set_attribute( 'foo-2', 'bar-2' );

		$processor->seek( 'first li' );
		$processor->set_attribute( 'foo-1', 'bar-1' );

		$this->assertSame(
			'<ul><li foo-1="bar-1">One</li><li foo-2="bar-2">Two</li><li>Three</li></ul>',
			$processor->get_updated_html(),
			'Did not seek to the intended bookmark locations'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_seeks_to_tag_closer_bookmark() {
		$processor = WP_HTML_Processor::create_fragment( '<div>First</div><span>Second</span>' );
		$processor->next_tag();
		$processor->set_bookmark( 'first' );
		$processor->next_token(); // Visit "First".
		$processor->next_token(); // Visit "</div>".
		$processor->set_bookmark( 'second' );

		$processor->seek( 'first' );
		$processor->seek( 'second' );

		$this->assertSame(
			'DIV',
			$processor->get_tag(),
			'Did not seek to the intended bookmark location'
		);
	}

	/**
	 * WP_HTML_Processor used to test for the diffs affecting
	 * the adjusted bookmark position while simultaneously adjusting
	 * the bookmark in question. As a result, updating the bookmarks
	 * of a next tag while removing two subsequent attributes in
	 * a previous tag unfolded like this:
	 *
	 * 1. Check if the first removed attribute is before the bookmark:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *         ^-------------------^                  ^
	 *           diff applied here           the bookmark is here
	 *
	 *    (Yes it is)
	 *
	 * 2. Move the bookmark to the left by the attribute length:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *                           ^
	 *                   the bookmark is here
	 *
	 * 3. Check if the second removed attribute is before the bookmark:
	 *
	 * <button twenty_one_characters 7_chars></button><button></button>
	 *                           ^   ^-----^
	 *                    bookmark    diff
	 *
	 *    This time, it isn't!
	 *
	 * The fix in the WP_HTML_Processor involves doing all the checks
	 * before moving the bookmark. This test is here to guard us from
	 * the erroneous behavior accidentally returning one day.
	 *
	 * @ticked tbd
	 *
	 * @covers ::seek
	 * @covers ::set_bookmark
	 */
	public function test_removing_long_attributes_doesnt_break_seek() {
		$input     = <<<HTML
		<button twenty_one_characters 7_chars></button><button></button>
HTML;
		$processor = WP_HTML_Processor::create_fragment( $input );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'first' );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'second' );

		$this->assertTrue(
			$processor->seek( 'first' ),
			'Seek() to the first button has failed'
		);
		$processor->remove_attribute( 'twenty_one_characters' );
		$processor->remove_attribute( '7_chars' );

		$this->assertTrue(
			$processor->seek( 'second' ),
			'Seek() to the second button has failed'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 * @covers ::set_bookmark
	 */
	public function test_bookmarks_complex_use_case() {
		$input           = <<<HTML
<div selected class="merge-message" checked>
	<div class="select-menu d-inline-block">
		<div checked class="BtnGroup MixedCaseHTML position-relative" />
		<div checked class="BtnGroup MixedCaseHTML position-relative">
			<button type="button" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button type="button" class="merge-box-button btn-group-squash rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button type="button" class="merge-box-button btn-group-rebase rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Rebase and merge
			</button>

			<button aria-label="Select merge method" disabled="disabled" type="button" data-view-component="true" class="select-menu-button btn BtnGroup-item"></button>
		</div>
	</div>
</div>
HTML;
		$expected_output = <<<HTML
<div selected class="merge-message" checked>
	<div class="select-menu d-inline-block">
		<div  class="BtnGroup MixedCaseHTML position-relative" />
		<div checked class="BtnGroup MixedCaseHTML position-relative">
			<button type="submit" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button  class="hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button id="rebase-and-merge"     disabled="">
			  Rebase and merge
			</button>

			<button id="last-button"     ></button>
		</div>
	</div>
</div>
HTML;
		$processor       = WP_HTML_Processor::create_fragment( $input );
		$processor->next_tag( 'div' );
		$processor->next_tag( 'div' );
		$processor->next_tag( 'div' );
		$processor->set_bookmark( 'first div' );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'first button' );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'second button' );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'third button' );
		$processor->next_tag( 'button' );
		$processor->set_bookmark( 'fourth button' );

		$processor->seek( 'first button' );
		$processor->set_attribute( 'type', 'submit' );

		$this->assertTrue(
			$processor->seek( 'third button' ),
			'Seek() to the third button failed'
		);
		$processor->remove_attribute( 'class' );
		$processor->remove_attribute( 'type' );
		$processor->remove_attribute( 'aria-expanded' );
		$processor->set_attribute( 'id', 'rebase-and-merge' );
		$processor->remove_attribute( 'data-details-container' );

		$this->assertTrue(
			$processor->seek( 'first div' ),
			'Seek() to the first div failed'
		);
		$processor->set_attribute( 'checked', false );

		$this->assertTrue(
			$processor->seek( 'fourth button' ),
			'Seek() to the fourth button failed'
		);
		$processor->set_attribute( 'id', 'last-button' );
		$processor->remove_attribute( 'class' );
		$processor->remove_attribute( 'type' );
		$processor->remove_attribute( 'checked' );
		$processor->remove_attribute( 'aria-label' );
		$processor->remove_attribute( 'disabled' );
		$processor->remove_attribute( 'data-view-component' );

		$this->assertTrue(
			$processor->seek( 'second button' ),
			'Seek() to the second button failed'
		);
		$processor->remove_attribute( 'type' );
		$processor->set_attribute( 'class', 'hx_create-pr-button' );

		$this->assertSame(
			$expected_output,
			$processor->get_updated_html(),
			'Performing several attribute updates on different tags does not produce the expected HTML snippet'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_updates_bookmark_for_additions_after_both_sides() {
		$processor = WP_HTML_Processor::create_fragment( '<div>First</div><div>Second</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'first' );
		$processor->next_tag();
		$processor->add_class( 'second' );

		$processor->seek( 'first' );
		$processor->add_class( 'first' );

		$this->assertSame(
			'<div class="first">First</div><div class="second">Second</div>',
			$processor->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_updates_bookmark_for_additions_before_both_sides() {
		$processor = WP_HTML_Processor::create_fragment( '<div>First</div><div>Second</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'first' );
		$processor->next_tag();
		$processor->set_bookmark( 'second' );

		$processor->seek( 'first' );
		$processor->add_class( 'first' );

		$processor->seek( 'second' );
		$processor->add_class( 'second' );

		$this->assertSame(
			'<div class="first">First</div><div class="second">Second</div>',
			$processor->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_updates_bookmark_for_deletions_after_both_sides() {
		$processor = WP_HTML_Processor::create_fragment( '<div>First</div><div disabled>Second</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'first' );
		$processor->next_tag();
		$processor->remove_attribute( 'disabled' );

		$processor->seek( 'first' );
		$processor->set_attribute( 'untouched', true );

		$this->assertSame(
			/*
			 * It shouldn't be necessary to assert the extra space after the tag
			 * following the attribute removal, but doing so makes the test easier
			 * to see than it would be if parsing the output HTML for proper
			 * validation. If the Tag Processor changes so that this space no longer
			 * appears then this test should be updated to reflect that. The space
			 * is not required.
			 */
			'<div untouched>First</div><div >Second</div>',
			$processor->get_updated_html(),
			'The bookmark was incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_updates_bookmark_for_deletions_before_both_sides() {
		$processor = WP_HTML_Processor::create_fragment( '<div disabled>First</div><div>Second</div>' );
		$processor->next_tag();
		$processor->set_bookmark( 'first' );
		$processor->next_tag();
		$processor->set_bookmark( 'second' );

		$processor->seek( 'first' );
		$processor->remove_attribute( 'disabled' );

		$processor->seek( 'second' );
		$processor->set_attribute( 'safe', true );

		$this->assertSame(
			/*
			 * It shouldn't be necessary to assert the extra space after the tag
			 * following the attribute removal, but doing so makes the test easier
			 * to see than it would be if parsing the output HTML for proper
			 * validation. If the Tag Processor changes so that this space no longer
			 * appears then this test should be updated to reflect that. The space
			 * is not required.
			 */
			'<div >First</div><div safe>Second</div>',
			$processor->get_updated_html(),
			'The bookmark was updated incorrectly in response to HTML markup updates'
		);
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::set_bookmark
	 */
	public function test_limits_the_number_of_bookmarks() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$processor->next_tag( 'li' );

		for ( $i = 0; $i < WP_HTML_Processor::MAX_BOOKMARKS; $i++ ) {
			$this->assertTrue( $processor->set_bookmark( "bookmark $i" ), "Could not allocate the bookmark #$i" );
		}

		$this->setExpectedIncorrectUsage( 'WP_HTML_Processor::set_bookmark' );
		$this->assertFalse( $processor->set_bookmark( 'final bookmark' ), "Allocated $i bookmarks, which is one above the limit" );
	}

	/**
	 * @ticked tbd
	 *
	 * @covers ::seek
	 */
	public function test_limits_the_number_of_seek_calls() {
		$processor = WP_HTML_Processor::create_fragment( '<ul><li>One</li><li>Two</li><li>Three</li></ul>' );
		$processor->next_tag( 'li' );
		$processor->set_bookmark( 'bookmark' );

		for ( $i = 0; $i < WP_HTML_Processor::MAX_SEEK_OPS; $i++ ) {
			$this->assertTrue( $processor->seek( 'bookmark' ), 'Could not seek to the "bookmark"' );
		}

		$this->setExpectedIncorrectUsage( 'WP_HTML_Processor::seek' );
		$this->assertFalse( $processor->seek( 'bookmark' ), "$i-th seek() to the bookmark succeeded, even though it should exceed the allowed limit" );
	}

	/**
	 * Ensures that it's possible to seek to an earlier location in a document even
	 * after reaching the end of a document, when most functionality shuts down.
	 *
	 * @ticked tbd
	 *
	 * @dataProvider data_incomplete_html_with_target_nodes_for_seeking
	 *
	 * @param string $html_with_target_element HTML string containing a tag with a `target` attribute.
	 */
	public function test_can_seek_after_document_ends( $html_with_target_element ) {
		$processor = WP_HTML_Processor::create_fragment( $html_with_target_element );

		$sought_tag_name = null;
		while ( $processor->next_tag() ) {
			if ( null !== $processor->get_attribute( 'target' ) ) {
				$processor->set_bookmark( 'target' );
				$sought_tag_name = $processor->get_tag();
			}
		}

		$this->assertTrue(
			$processor->seek( 'target' ),
			'Should have been able to seek to the target bookmark after reaching the end of the document.'
		);

		$this->assertSame(
			$sought_tag_name,
			$processor->get_tag(),
			"Should have found original target node instead of {$processor->get_tag()}."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_incomplete_html_with_target_nodes_for_seeking() {
		return array(
			'Compete document'    => array( '<div><img target></div>' ),
			'Incomplete document' => array( '<div><img target></div' ),
		);
	}
}