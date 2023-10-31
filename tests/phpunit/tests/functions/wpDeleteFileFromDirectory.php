<?php
/**
 * Tests for the wp_delete_file_from_directory function.
 *
 * @group functions.php
 *
 * @covers ::wp_delete_file_from_directory
 */#
class Tests_functions_wpDeleteFileFromDirectory extends WP_UnitTestCase{

	/**
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$this->assertTrue( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileDoesNotExist( $file );
	}

	public function test_wp_delete_file_from_directory_no_file() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
//		touch( $file );

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileDoesNotExist( $file );
	}

	public function test_wp_delete_file_from_directory_file_steam() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$this->assertTrue( wp_delete_file_from_directory( 'file://' . $file, 'file://' . $directory ) );
		$this->assertFileDoesNotExist( $file );
	}

	public function test_wp_delete_file_from_directory_non_file_steam() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$this->assertFalse( wp_delete_file_from_directory( 'php://' . $file, 'php://' . $directory ) );
		$this->assertFileExists( $file );
		@unlink( $file );
	}
	public function test_wp_delete_file_from_directory_no_folder() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA ) . '/images';
		touch( $file );

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileExists( $file );
		@unlink( $file );
	}
}
