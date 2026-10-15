<?php

namespace Shift64\SmartPhoneValidation\Tests\Unit;

/**
 * scripts/ci-docs-only.sh decides whether a pull request skips the code checks
 * (.ai/specs/2026-10-13-skip-ci-for-docs-only-prs.md). A path wrongly called docs
 * merges untested code, so every file a check reads or the package ships is pinned
 * here as "not docs".
 */
class CiDocsOnlyClassifierTest extends TestCase {

	/**
	 * @dataProvider provide_changed_paths
	 *
	 * @param list<string> $paths    Changed paths, as `git diff --name-only` lists them.
	 * @param string       $expected The script's answer.
	 */
	public function test_classifies_changed_paths( array $paths, string $expected ): void {
		$this->assertSame( $expected, $this->classify( implode( "\n", $paths ) . "\n" ) );
	}

	/**
	 * @return array<string, array{list<string>, string}>
	 */
	public static function provide_changed_paths(): array {
		return array(
			'contributor doc'                        => array( array( 'AGENTS.md' ), 'true' ),
			'every root doc on the list'             => array( array( 'AGENTS.md', 'CLAUDE.md', 'SDLC.md', 'CODE_REVIEW.md', 'BACKWARD_COMPATIBILITY.md', 'README.md' ), 'true' ),
			'spec and docs folder'                   => array( array( '.ai/specs/x.md', 'docs/CI-CD-SETUP.md' ), 'true' ),
			'agent skill'                            => array( array( '.claude/skills/a/SKILL.md' ), 'true' ),
			'directory listing asset'                => array( array( '.wordpress-org/banner-772x250.png' ), 'true' ),
			// Ships, and Plugin Check validates its headers and Stable tag.
			'readme.txt'                             => array( array( 'readme.txt' ), 'false' ),
			// ReadmeChangelogSyncTest reads it.
			'CHANGELOG.md'                           => array( array( 'CHANGELOG.md' ), 'false' ),
			'Markdown test fixture'                  => array( array( 'tests/Unit/fixtures/readme-sync/CHANGELOG-1.5.0.md' ), 'false' ),
			// build-package.sh rejects a root Markdown file .distignore does not exclude.
			'new root Markdown file'                 => array( array( 'NEW-NOTES.md' ), 'false' ),
			'Markdown that may ship'                 => array( array( 'src/notes.md' ), 'false' ),
			'LICENSE'                                => array( array( 'LICENSE' ), 'false' ),
			// playwright.config.ts loads .ai/qa/test-env.env.
			'QA environment file'                    => array( array( '.ai/qa/test-env.env' ), 'false' ),
			'translation'                            => array( array( 'languages/shift64-phone-validation-pl_PL.po' ), 'false' ),
			'workflow'                               => array( array( '.github/workflows/e2e.yml' ), 'false' ),
			// The demo network's wp-env config, read by the playwright-multisite job.
			'demo wp-env config'                     => array( array( '.wp-env.demo.json' ), 'false' ),
			'docs plus code'                         => array( array( 'README.md', 'src/Validation/Normalizer.php' ), 'false' ),
			'code listed after docs still counts'    => array( array( 'docs/CI-CD-SETUP.md', '.ai/specs/x.md', 'composer.json' ), 'false' ),
			// git quotes non-ASCII paths; the quote is not on the allowlist.
			'quoted path'                            => array( array( '"docs/\303\263.md"' ), 'false' ),
			'name starting like a folder'            => array( array( 'docs.php' ), 'false' ),
			// .phpcs.xml.dist scans docs/, so only Markdown there is docs.
			'PHP under docs'                         => array( array( 'docs/example.php' ), 'false' ),
		);
	}

	public function test_no_changed_paths_is_not_docs_only(): void {
		$this->assertSame( 'false', $this->classify( '' ) );
		$this->assertSame( 'false', $this->classify( "\n\n" ) );
	}

	public function test_last_path_without_a_newline_is_classified(): void {
		$this->assertSame( 'false', $this->classify( "AGENTS.md\nsrc/Plugin.php" ) );
	}

	/**
	 * Each code job waits for the classifier and skips only on its explicit "true",
	 * so a crashed classifier still runs the job. Read as text: no YAML parser ships,
	 * so the gate must stay the two lines right after the job's name.
	 *
	 * @dataProvider provide_gated_jobs
	 */
	public function test_code_job_is_gated_on_the_classifier( string $workflow, string $job ): void {
		$yaml = (string) file_get_contents( dirname( __DIR__, 2 ) . '/.github/workflows/' . $workflow );

		$this->assertStringContainsString( "\n  changes:\n", $yaml );
		$this->assertStringContainsString( './scripts/ci-docs-only.sh', $yaml );
		$this->assertStringContainsString( 'docs_only: ${{ steps.classify.outputs.docs_only }}', $yaml );
		$this->assertMatchesRegularExpression(
			'/\n  ' . preg_quote( $job, '/' ) . ":\n    name: [^\n]+\n    needs: changes\n    if: \\$\\{\\{ !cancelled\\(\\) && needs\\.changes\\.outputs\\.docs_only != 'true' \\}\\}\n/",
			$yaml
		);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_gated_jobs(): array {
		return array(
			'PHPCS'                  => array( 'code-quality.yml', 'phpcs' ),
			'PHPStan'                => array( 'code-quality.yml', 'phpstan' ),
			'PHPUnit'                => array( 'code-quality.yml', 'phpunit' ),
			'Package + Plugin Check' => array( 'code-quality.yml', 'package' ),
			'E2E'                    => array( 'e2e.yml', 'playwright' ),
			'E2E multisite'          => array( 'e2e.yml', 'playwright-multisite' ),
		);
	}

	private function classify( string $stdin ): string {
		$script = dirname( __DIR__, 2 ) . '/scripts/ci-docs-only.sh';
		$pipes  = array();
		$proc   = proc_open( array( 'bash', $script ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $proc, 'bash is needed to run scripts/ci-docs-only.sh' );

		fwrite( $pipes[0], $stdin );
		fclose( $pipes[0] );
		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$this->assertSame( 0, proc_close( $proc ), $stderr );

		return trim( $stdout );
	}
}
