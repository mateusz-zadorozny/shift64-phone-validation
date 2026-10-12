<?php
/**
 * Runs scripts/sync-readme-changelog.php the way scripts/update-version.sh does,
 * on temporary copies of the fixtures in tests/Unit/fixtures/readme-sync/.
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class ReadmeChangelogSyncTest extends PHPUnitTestCase {

	const OLDER_RELEASES = 'Older releases: [full changelog on GitHub](https://github.com/mateusz-zadorozny/shift64-phone-validation/blob/master/CHANGELOG.md).';

	/** @var string */
	private $dir;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/shift64-readme-sync-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );
		parent::tearDown();
	}

	public function test_new_release_is_prepended_in_readme_format_and_ci_fixes_are_skipped(): void {
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), self::fixture( 'readme.txt' ) );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( self::fixture( 'readme-1.5.0-expected.txt' ), $result['readme'] );
		$this->assertStringContainsString( 'Added: 1.5.0.', $result['stdout'] );
		$this->assertStringNotContainsString( 'plugin check', $result['readme'], 'A **ci:** fix must not reach the readme.' );
	}

	public function test_second_run_changes_nothing(): void {
		$first = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), self::fixture( 'readme.txt' ) );
		$again = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), $first['readme'] );

		$this->assertSame( 0, $again['exit'], $again['stderr'] );
		$this->assertSame( $first['readme'], $again['readme'] );
		$this->assertStringContainsString( 'already up to date', $again['stdout'] );
	}

	public function test_already_synced_readme_is_left_byte_for_byte(): void {
		$readme = self::fixture( 'readme-1.5.0-expected.txt' );
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), $readme );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( $readme, $result['readme'] );
	}

	public function test_changelog_is_trimmed_to_ten_versions_and_older_releases_stays_last(): void {
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), self::fixture( 'readme-ten-versions.txt' ) );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( self::fixture( 'readme-ten-versions-1.5.0-expected.txt' ), $result['readme'] );
		$this->assertStringContainsString( 'Dropped (older than the last 10): 1.2.0.', $result['stdout'] );

		$section = $this->changelog_section( $result['readme'] );
		preg_match_all( '/^= (\S+) =$/m', $section, $headings );
		$this->assertSame(
			array( '1.5.0', '1.4.2', '1.4.1', '1.4.0', '1.3.3', '1.3.2', '1.3.1', '1.3.0', '1.2.2', '1.2.1' ),
			$headings[1]
		);
		$this->assertSame( self::OLDER_RELEASES, $this->last_line( $section ) );
	}

	public function test_crlf_readme_keeps_crlf_line_endings(): void {
		$crlf   = static fn( string $text ): string => str_replace( "\n", "\r\n", $text );
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), $crlf( self::fixture( 'readme.txt' ) ) );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( $crlf( self::fixture( 'readme-1.5.0-expected.txt' ) ), $result['readme'] );
	}

	public function test_bytes_outside_the_changelog_section_are_preserved(): void {
		// Other sections with CRLF endings and trailing spaces; the changelog section itself is LF.
		$readme   = self::fixture( 'readme.txt' );
		$expected = self::fixture( 'readme-1.5.0-expected.txt' );
		$split    = static fn( string $text ): array => explode( '== Changelog ==', $text, 2 );
		$before   = str_replace( "\n", "  \r\n", $split( $readme )[0] );

		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), $before . '== Changelog ==' . $split( $readme )[1] );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( $before . '== Changelog ==' . $split( $expected )[1], $result['readme'] );
		$this->assertStringEndsWith( "== Credits ==\n\nLast section, ends with a newline.\n", $result['readme'] );
	}

	public function test_release_with_only_tooling_changes_is_a_maintenance_release(): void {
		$result = $this->sync( self::fixture( 'CHANGELOG-tooling-only.md' ), self::fixture( 'readme.txt' ) );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertStringContainsString(
			"== Changelog ==\n\n= 1.4.3 =\n* Maintenance release.\n\n= 1.4.2 =\n* Fix: Hand-curated wording",
			$result['readme']
		);
	}

	public function test_every_label_is_mapped_and_non_user_facing_sections_are_skipped(): void {
		$result = $this->sync( self::fixture( 'CHANGELOG-all-labels.md' ), self::fixture( 'readme-1.5.0-expected.txt' ) );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertStringContainsString(
			"== Changelog ==\n\n"
			. "= 2.0.0 =\n"
			. "* Breaking: The shift64_phone_validation_format option now stores one format per country.\n"
			. "* New: Add a per-country output format, thanks @contributor.\n"
			. "* Fix: Show the error next to the field.\n"
			. "* Performance: Load the metadata only on the checkout page.\n"
			. "* Revert: Guess the country from the browser language.\n"
			. "\n= 1.5.0 =\n",
			$result['readme']
		);
		$this->assertStringNotContainsString( 'smaller classes', $result['readme'], 'Code Refactoring is not user-facing.' );
		$this->assertStringNotContainsString( 'SVN secrets', $result['readme'], 'A **ci:** breaking note is not user-facing.' );
	}

	public function test_older_versions_missing_from_the_readme_are_not_backfilled(): void {
		// 1.4.0 is in CHANGELOG.md but older than the newest readme entry (1.4.2): left out on purpose.
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), self::fixture( 'readme.txt' ) );

		$this->assertStringNotContainsString( '= 1.4.0 =', $result['readme'] );
	}

	/**
	 * @dataProvider malformed_readmes
	 */
	public function test_malformed_readme_fails_without_touching_the_file( string $readme, string $message ): void {
		$result = $this->sync( self::fixture( 'CHANGELOG-1.5.0.md' ), $readme );

		$this->assertSame( 1, $result['exit'] );
		$this->assertStringContainsString( $message, $result['stderr'] );
		$this->assertSame( $readme, $result['readme'] );
	}

	public static function malformed_readmes(): array {
		$readme = self::fixture( 'readme.txt' );
		return array(
			'no changelog section'      => array( str_replace( '== Changelog ==', '== History ==', $readme ), "no '== Changelog ==' section" ),
			'free text instead of list' => array( str_replace( "== Changelog ==\n\n", "== Changelog ==\n\nSee CHANGELOG.md on GitHub.\n\n", $readme ), "text before the first '= X.Y.Z =' heading" ),
			'dated version heading'     => array( str_replace( '= 1.4.1 =', '= 1.4.1 - 2026-09-22 =', $readme ), "must be '= X.Y.Z ='" ),
			'text after older releases' => array( str_replace( self::OLDER_RELEASES . "\n", self::OLDER_RELEASES . "\nMore text.\n", $readme ), "text after the 'Older releases' line" ),
		);
	}

	public function test_changelog_without_release_headings_fails(): void {
		$readme = self::fixture( 'readme.txt' );
		$result = $this->sync( "# Changelog\n\n* nothing released yet\n", $readme );

		$this->assertSame( 1, $result['exit'] );
		$this->assertStringContainsString( 'no release headings', $result['stderr'] );
		$this->assertSame( $readme, $result['readme'] );
	}

	public function test_unknown_argument_is_rejected(): void {
		$result = $this->run_script( array( '--readme-file=x' ) );

		$this->assertSame( 2, $result['exit'] );
		$this->assertStringContainsString( 'Unknown argument', $result['stderr'] );
	}

	/**
	 * The committed readme.txt must already contain every release in CHANGELOG.md, in the
	 * format above; otherwise the next release would fail in scripts/update-version.sh.
	 */
	public function test_repository_readme_is_in_sync_with_changelog_md(): void {
		$root   = dirname( __DIR__, 2 );
		$readme = (string) file_get_contents( $root . '/readme.txt' );
		$result = $this->sync( (string) file_get_contents( $root . '/CHANGELOG.md' ), $readme );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( $readme, $result['readme'], 'Run php scripts/sync-readme-changelog.php and commit readme.txt.' );
	}

	/**
	 * Writes both inputs to the temp dir, runs the script on them and returns the outcome.
	 *
	 * @return array{exit: int, stdout: string, stderr: string, readme: string}
	 */
	private function sync( string $changelog, string $readme ): array {
		file_put_contents( $this->dir . '/CHANGELOG.md', $changelog );
		file_put_contents( $this->dir . '/readme.txt', $readme );

		$result           = $this->run_script( array( '--changelog=' . $this->dir . '/CHANGELOG.md', '--readme=' . $this->dir . '/readme.txt' ) );
		$result['readme'] = (string) file_get_contents( $this->dir . '/readme.txt' );
		return $result;
	}

	/**
	 * @param list<string> $args Script arguments.
	 * @return array{exit: int, stdout: string, stderr: string}
	 */
	private function run_script( array $args ): array {
		$command = array_merge( array( PHP_BINARY, dirname( __DIR__, 2 ) . '/scripts/sync-readme-changelog.php' ), $args );
		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $process );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		return array(
			'exit'   => proc_close( $process ),
			'stdout' => $stdout,
			'stderr' => $stderr,
		);
	}

	private static function fixture( string $name ): string {
		return (string) file_get_contents( __DIR__ . '/fixtures/readme-sync/' . $name );
	}

	private function changelog_section( string $readme ): string {
		preg_match( '/^== Changelog ==\n(.*?)^== /ms', $readme, $m );
		return $m[1] ?? '';
	}

	private function last_line( string $text ): string {
		$lines = array_values( array_filter( explode( "\n", $text ), static fn( string $line ): bool => '' !== trim( $line ) ) );
		return (string) end( $lines );
	}
}
