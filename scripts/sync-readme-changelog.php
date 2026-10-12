<?php
/**
 * Prepends new releases from CHANGELOG.md to the "== Changelog ==" section of readme.txt.
 *
 * CHANGELOG.md is written by @semantic-release/changelog (conventional-changelog, angular
 * preset); readme.txt is what WordPress.org renders. scripts/update-version.sh runs this in
 * the semantic-release prepare step, after the new release was added to CHANGELOG.md and
 * before @semantic-release/git commits.
 *
 * The readme section has this shape (the contract this script reads and writes):
 *
 *     == Changelog ==
 *
 *     = 1.4.2 =
 *     * Fix: Sentence starting with a capital letter and ending with a period.
 *
 *     = 1.4.1 =
 *     * Fix: ...
 *
 *     Older releases: [full changelog on GitHub](https://github.com/.../CHANGELOG.md).
 *
 *     == Upgrade Notice ==
 *
 * Only versions that are newer than the newest "= X.Y.Z =" already in the section are
 * rendered and prepended, so hand-curated entries are never rewritten. The section is then
 * trimmed to the 10 most recent versions and the "Older releases" line is kept last.
 * Every other byte of readme.txt (including line endings) is preserved, and running the
 * script twice changes nothing.
 *
 * Usage: php scripts/sync-readme-changelog.php [--changelog=PATH] [--readme=PATH]
 *        Defaults: CHANGELOG.md and readme.txt in the repository root.
 * Exit:  0 synced or already up to date, 1 malformed input, 2 bad arguments.
 *
 * Not shipped in the plugin package (.distignore) and not scanned by PHPCS (.phpcs.xml.dist).
 *
 * @package Shift64\SmartPhoneValidation
 */

declare( strict_types=1 );

namespace Shift64\SmartPhoneValidation\Scripts\ReadmeChangelog;

use RuntimeException;

/** How many versions the readme changelog keeps. */
const MAX_VERSIONS = 10;

/** Target of the "Older releases" line when the script has to add it. */
const FULL_CHANGELOG_URL = 'https://github.com/mateusz-zadorozny/shift64-phone-validation/blob/master/CHANGELOG.md';

/** CHANGELOG.md section title => readme label. Every other section is not user-facing. */
const LABELS = array(
	'BREAKING CHANGES'         => 'Breaking',
	'Features'                 => 'New',
	'Bug Fixes'                => 'Fix',
	'Performance Improvements' => 'Performance',
	'Reverts'                  => 'Revert',
);

/** Order of the labels inside one readme version. */
const LABEL_ORDER = array( 'Breaking', 'New', 'Fix', 'Performance', 'Revert' );

/** Conventional-commit scopes that only concern tooling; dropped from the readme. */
const TOOLING_SCOPES = array( 'ci', 'build', 'release', 'deps-dev', 'test', 'tests', 'e2e' );

/** A semantic version, optionally prefixed with "v" in CHANGELOG.md headings. */
const VERSION_PATTERN = '\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';

/**
 * Malformed input: reported on STDERR, exit code 1.
 */
final class SyncError extends RuntimeException {
}

/**
 * Parses CHANGELOG.md into readme items.
 *
 * @param string $markdown CHANGELOG.md contents.
 * @return array<string, list<string>> Version => readme lines ("* Fix: Text."), file order.
 * @throws SyncError When no release heading is found or the structure is broken.
 */
function parse_changelog( string $markdown ): array {
	$releases = array();
	$version  = null;
	$section  = null; // Current "### " title, null before the first one of a version.
	$item     = null; // Index of the last item of the current section, for continuation lines.
	$items    = array(); // Version => list of array{label: string|null, text: string}.

	foreach ( preg_split( '/\r\n|\n|\r/', $markdown ) as $number => $line ) {
		$line_no = $number + 1;

		// "# [1.4.0](compare-url) (2026-09-21)", "## [1.4.2](...) (date)", "# 1.0.0 (2026-01-28)".
		if ( preg_match( '/^#{1,3}\s+(?:\[v?(' . VERSION_PATTERN . ')\]\([^)]*\)|v?(' . VERSION_PATTERN . '))(?:\s+"[^"]*")?(?:\s+\(\d{4}-\d{2}-\d{2}\))?\s*$/', $line, $m ) ) {
			$version = '' !== $m[1] ? $m[1] : $m[2];
			if ( isset( $items[ $version ] ) ) {
				throw new SyncError( "CHANGELOG.md line {$line_no}: version {$version} appears twice." );
			}
			$items[ $version ] = array();
			$section           = null;
			$item              = null;
			continue;
		}

		if ( null === $version ) {
			continue; // Title or preamble above the first release.
		}

		if ( preg_match( '/^###\s+(.+?)\s*$/u', $line, $m ) ) {
			// The conventionalcommits preset prints "⚠ BREAKING CHANGES".
			$section = (string) preg_replace( '/^\x{26A0}\x{FE0F}?\s*/u', '', $m[1] );
			$item    = null;
			continue;
		}

		if ( '' === trim( $line ) ) {
			continue;
		}

		if ( null === $section ) {
			throw new SyncError( "CHANGELOG.md line {$line_no}: text under version {$version} before any '### ' section: {$line}" );
		}

		if ( preg_match( '/^[*-]\s+(.*)$/', $line, $m ) ) {
			$items[ $version ][] = array(
				'label' => LABELS[ $section ] ?? null,
				'text'  => $m[1],
			);
			$item                = array_key_last( $items[ $version ] );
			continue;
		}

		if ( null === $item ) {
			throw new SyncError( "CHANGELOG.md line {$line_no}: text in '### {$section}' of version {$version} that is not a list item: {$line}" );
		}

		// A multi-line note (e.g. a BREAKING CHANGE paragraph) continues the previous item.
		$items[ $version ][ $item ]['text'] .= ' ' . trim( $line );
	}

	if ( ! $items ) {
		throw new SyncError( "CHANGELOG.md has no release headings such as '## [1.2.3](...) (2026-01-01)'." );
	}

	foreach ( $items as $release => $list ) {
		$lines = array();
		foreach ( $list as $entry ) {
			if ( null === $entry['label'] ) {
				continue; // Documentation, Code Refactoring, ... sections: not user-facing.
			}
			$text = clean_item_text( $entry['text'] );
			if ( null === $text ) {
				continue;
			}
			$lines[] = array(
				'order' => array_search( $entry['label'], LABEL_ORDER, true ),
				'line'  => '* ' . $entry['label'] . ': ' . $text,
			);
		}
		usort( $lines, static fn( array $a, array $b ): int => $a['order'] <=> $b['order'] ); // Stable since PHP 8.0.
		$releases[ $release ] = array_values( array_unique( array_column( $lines, 'line' ) ) );
	}

	return $releases;
}

/**
 * Turns one CHANGELOG.md list item into readme text.
 *
 * @param string $text Item text after "* ".
 * @return string|null Sentence, or null when the item must not appear in the readme.
 */
function clean_item_text( string $text ): ?string {
	$link = '\[[^\]]*\]\([^)]*\)';
	$ref  = '(?:' . $link . '|[\w.\/-]*#\d+)';

	// Link groups: " ([#24](url))", " ([790a3b9](url))", " ([#1](url), [#2](url))".
	$text = (string) preg_replace( '/\s*\((?:' . $link . '(?:,\s*)?)+\)/', '', $text );
	// Reference trailer: "..., closes [#12](url) [#13](url)" (angular preset) or ", fixes #12".
	$text = (string) preg_replace( '/,\s*(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+' . $ref . '(?:[\s,]+' . $ref . ')*\s*$/i', '', $text );
	// Plain references left in the subject: " (#24)".
	$text = (string) preg_replace( '/\s*\(#\d+(?:,\s*#\d+)*\)/', '', $text );
	// Any other Markdown link keeps its text: "[@user](url)" -> "@user".
	$text = (string) preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $text );

	if ( preg_match( '/^\*\*([^*]+):\*\*\s*(.*)$/s', $text, $m ) ) {
		$scopes = array_map( static fn( string $s ): string => strtolower( trim( $s ) ), explode( ',', $m[1] ) );
		if ( ! array_diff( $scopes, TOOLING_SCOPES ) ) {
			return null; // Tooling-only change, not relevant to site owners.
		}
		$text = $m[2];
	}

	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	if ( '' === $text ) {
		return null;
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 1 ) : substr( $text, 0, 1 );
	$rest  = function_exists( 'mb_substr' ) ? mb_substr( $text, 1 ) : substr( $text, 1 );
	$text  = ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first ) ) . $rest;

	if ( ! preg_match( '/[.!?][)"\'\x{201D}]?$/u', $text ) ) {
		$text .= '.';
	}

	return $text;
}

/**
 * Prepends missing releases to the readme changelog section.
 *
 * @param string                       $readme   readme.txt contents.
 * @param array<string, list<string>> $releases Output of parse_changelog().
 * @return array{0: string, 1: list<string>, 2: list<string>} Updated readme, added versions, dropped versions.
 * @throws SyncError When the section is missing or does not follow the contract.
 */
function sync_readme( string $readme, array $releases ): array {
	// Lines keep their own terminator, so untouched lines are re-emitted byte for byte.
	$lines   = preg_split( '/(?<=\n)/', $readme, -1, PREG_SPLIT_NO_EMPTY );
	$content = static fn( string $line ): string => rtrim( $line, " \t\r\n" );

	$header = null;
	foreach ( $lines as $index => $line ) {
		if ( '== Changelog ==' === $content( $line ) ) {
			if ( null !== $header ) {
				throw new SyncError( "readme.txt has more than one '== Changelog ==' section." );
			}
			$header = $index;
		}
	}
	if ( null === $header ) {
		throw new SyncError( "readme.txt has no '== Changelog ==' section." );
	}

	$end = count( $lines );
	for ( $index = $header + 1; $index < count( $lines ); $index++ ) {
		if ( preg_match( '/^==\s.*\s==$/', $content( $lines[ $index ] ) ) ) {
			$end = $index;
			break;
		}
	}

	$eol    = str_ends_with( $lines[ $header ], "\r\n" ) ? "\r\n" : "\n";
	$lead   = array(); // Blank lines between the header and the first version.
	$blocks = array(); // Version => its lines, up to the next version or the "Older releases" line.
	$older  = null;
	$tail   = array(); // Blank lines after the "Older releases" line.
	$block  = null;

	for ( $index = $header + 1; $index < $end; $index++ ) {
		$line = $lines[ $index ];
		$text = $content( $line );
		$at   = 'readme.txt line ' . ( $index + 1 );

		if ( preg_match( '/^=[^=].*=$/', $text ) ) {
			if ( ! preg_match( '/^= (' . VERSION_PATTERN . ') =$/', $text, $m ) ) {
				throw new SyncError( "{$at}: changelog heading '{$text}' must be '= X.Y.Z =' (no date, no prefix)." );
			}
			if ( null !== $older ) {
				throw new SyncError( "{$at}: version {$m[1]} comes after the 'Older releases' line, which must be last." );
			}
			if ( isset( $blocks[ $m[1] ] ) ) {
				throw new SyncError( "{$at}: version {$m[1]} appears twice in the changelog." );
			}
			$block            = $m[1];
			$blocks[ $block ] = array( $line );
			continue;
		}

		if ( str_starts_with( $text, 'Older releases:' ) ) {
			if ( null !== $older ) {
				throw new SyncError( "{$at}: second 'Older releases' line." );
			}
			$older = $line;
			continue;
		}

		if ( null !== $older ) {
			if ( '' !== $text ) {
				throw new SyncError( "{$at}: text after the 'Older releases' line, which must be last in the changelog: {$text}" );
			}
			$tail[] = $line;
		} elseif ( null !== $block ) {
			$blocks[ $block ][] = $line;
		} elseif ( '' === $text ) {
			$lead[] = $line;
		} else {
			throw new SyncError( "{$at}: text before the first '= X.Y.Z =' heading of the changelog: {$text}" );
		}
	}

	$newest = null;
	foreach ( array_keys( $blocks ) as $version ) {
		$version = (string) $version;
		if ( null === $newest || version_compare( $version, $newest, '>' ) ) {
			$newest = $version;
		}
	}

	$added = array();
	foreach ( array_keys( $releases ) as $version ) {
		$version = (string) $version;
		if ( ! isset( $blocks[ $version ] ) && ( null === $newest || version_compare( $version, $newest, '>' ) ) ) {
			$added[] = $version;
		}
	}
	usort( $added, static fn( string $a, string $b ): int => version_compare( $b, $a ) );

	$all = array();
	foreach ( $added as $version ) {
		$items = $releases[ $version ] ? $releases[ $version ] : array( '* Maintenance release.' );
		$all[ $version ] = array_map(
			static fn( string $line ): string => $line . $eol,
			array_merge( array( '= ' . $version . ' =' ), $items, array( '' ) )
		);
	}
	foreach ( $blocks as $version => $block_lines ) {
		$all[ (string) $version ] = $block_lines;
	}

	$kept    = array_slice( $all, 0, MAX_VERSIONS, true );
	$dropped = array_map( 'strval', array_keys( array_slice( $all, MAX_VERSIONS, null, true ) ) );
	$section = array_merge( ...array_values( $kept ) );

	if ( ! $blocks && ! $lead && $added ) {
		$lead[] = $eol; // Empty section: keep the blank line after the header.
	}

	if ( null === $older ) {
		// Missing line: add it last, separated by blank lines from what surrounds it.
		$last = end( $section );
		if ( false !== $last ) {
			if ( ! str_ends_with( $last, "\n" ) ) {
				$section[ array_key_last( $section ) ] .= $eol;
			} elseif ( '' !== $content( $last ) ) {
				$section[] = $eol;
			}
		}
		$older = 'Older releases: [full changelog on GitHub](' . FULL_CHANGELOG_URL . ').' . $eol;
		if ( $end < count( $lines ) ) {
			$tail = array( $eol );
		}
	}

	$updated = implode(
		'',
		array_merge(
			array_slice( $lines, 0, $header + 1 ),
			$lead,
			$section,
			array( $older ),
			$tail,
			array_slice( $lines, $end )
		)
	);

	return array( $updated, $added, $dropped );
}

/**
 * Reads the CLI options.
 *
 * @param list<string> $args Arguments without the script name.
 * @return array{changelog: string, readme: string}
 * @throws \InvalidArgumentException On an unknown argument.
 */
function parse_args( array $args ): array {
	$root    = dirname( __DIR__ );
	$options = array(
		'changelog' => $root . '/CHANGELOG.md',
		'readme'    => $root . '/readme.txt',
	);
	foreach ( $args as $arg ) {
		if ( preg_match( '/^--(changelog|readme)=(.+)$/', $arg, $m ) ) {
			$options[ $m[1] ] = $m[2];
			continue;
		}
		throw new \InvalidArgumentException( "Unknown argument: {$arg}" );
	}
	return $options;
}

/**
 * Entry point.
 *
 * @param list<string> $argv Command line.
 * @return int Exit code.
 */
function main( array $argv ): int {
	try {
		$options = parse_args( array_slice( $argv, 1 ) );
	} catch ( \InvalidArgumentException $e ) {
		fwrite( STDERR, $e->getMessage() . "\nUsage: php scripts/sync-readme-changelog.php [--changelog=PATH] [--readme=PATH]\n" );
		return 2;
	}

	try {
		foreach ( $options as $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				throw new SyncError( "Cannot read {$path}." );
			}
		}
		$readme = (string) file_get_contents( $options['readme'] );

		list( $updated, $added, $dropped ) = sync_readme( $readme, parse_changelog( (string) file_get_contents( $options['changelog'] ) ) );

		if ( $updated === $readme ) {
			echo "readme.txt changelog already up to date.\n";
			return 0;
		}
		if ( false === file_put_contents( $options['readme'], $updated ) ) {
			throw new SyncError( "Cannot write {$options['readme']}." );
		}
		echo 'readme.txt changelog synced. Added: ' . ( $added ? implode( ', ', $added ) : 'none' )
			. '. Dropped (older than the last ' . MAX_VERSIONS . '): ' . ( $dropped ? implode( ', ', $dropped ) : 'none' ) . ".\n";
		return 0;
	} catch ( SyncError $e ) {
		fwrite( STDERR, 'sync-readme-changelog: ' . $e->getMessage() . "\n" );
		return 1;
	}
}

exit( main( $argv ) );
