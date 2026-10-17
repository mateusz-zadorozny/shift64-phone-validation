<?php
/**
 * Checks the translation files shipped in languages/: complete against the .pot,
 * placeholders kept, .mo compiled from the current .po, and the German variants
 * in their own register (de_DE says "du", de_DE_formal says "Sie").
 *
 * @package Shift64\SmartPhoneValidation\Tests
 */

namespace Shift64\SmartPhoneValidation\Tests\Unit;

use Shift64\SmartPhoneValidation\Checkout\ErrorMessages;
use Shift64\SmartPhoneValidation\Validation\ValidationResult;

class TranslationFilesTest extends TestCase {

	const LANGUAGES_DIR = __DIR__ . '/../../languages/';

	/**
	 * Locales whose .po must translate every string. en_US is left out: an empty
	 * msgstr there falls back to the English msgid, which is the right text.
	 */
	const COMPLETE_LOCALES = array( 'pl_PL', 'de_DE', 'de_DE_formal' );

	/**
	 * @dataProvider complete_locales
	 */
	public function test_every_string_is_translated( string $locale ): void {
		$po = self::read_po( $locale );

		$this->assertSame( array_keys( self::read_pot() ), array_keys( $po ), 'The .po must list exactly the strings of the .pot; run composer makepo.' );
		foreach ( $po as $msgid => $msgstr ) {
			$this->assertNotSame( '', $msgstr, "Untranslated in {$locale}: {$msgid}" );
		}
	}

	/**
	 * @dataProvider complete_locales
	 */
	public function test_placeholders_are_kept( string $locale ): void {
		foreach ( self::read_po( $locale ) as $msgid => $msgstr ) {
			$this->assertSame( self::placeholders( $msgid ), self::placeholders( $msgstr ), "Placeholders differ in {$locale}: {$msgid}" );
		}
	}

	/**
	 * WordPress reads the .mo, so a .po edited without composer makemo changes nothing.
	 *
	 * @dataProvider shipped_locales
	 */
	public function test_mo_is_compiled_from_the_po( string $locale ): void {
		$expected = array_filter( self::read_po( $locale ), static fn ( $msgstr ) => '' !== $msgstr );
		ksort( $expected );

		$actual = self::read_mo( $locale );
		ksort( $actual );

		$this->assertSame( $expected, $actual, "languages/shift64-phone-validation-{$locale}.mo is out of date; run composer makemo." );
	}

	/**
	 * Themes map a notice to the phone field by keyword (issue #5): every German
	 * message, with the real translation files, names the phone.
	 *
	 * @dataProvider german_locales
	 */
	public function test_every_german_message_mentions_the_phone( string $locale ): void {
		$this->use_locale_file( $locale );

		foreach ( array( ErrorMessages::FIELD_BILLING, ErrorMessages::FIELD_SHIPPING ) as $field ) {
			foreach ( array( ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX, ValidationResult::ERROR_INVALID_NUMBER, null ) as $code ) {
				$this->assertStringContainsStringIgnoringCase( 'telefon', ErrorMessages::for_classic_checkout( $code, $field ) );
				$this->assertStringContainsStringIgnoringCase( 'telefon', ErrorMessages::for_block_checkout( $code, $field ) );
			}
		}
	}

	public function test_german_checkout_messages(): void {
		$this->use_locale_file( 'de_DE' );
		$this->assertSame( 'Bitte gib eine gültige Telefonnummer ein.', ErrorMessages::for_classic_checkout( ValidationResult::ERROR_INVALID_NUMBER, ErrorMessages::FIELD_BILLING ) );
		$this->assertSame( 'Telefon (Rechnungsadresse) ist keine gültige Telefonnummer.', ErrorMessages::for_block_checkout( ValidationResult::ERROR_INVALID_NUMBER, ErrorMessages::FIELD_BILLING ) );
		$this->assertSame( 'Telefon (Lieferadresse) muss eine Ländervorwahl enthalten.', ErrorMessages::for_block_checkout( ValidationResult::ERROR_MISSING_INTERNATIONAL_PREFIX, ErrorMessages::FIELD_SHIPPING ) );

		$this->use_locale_file( 'de_DE_formal' );
		$this->assertSame( 'Bitte geben Sie eine gültige Telefonnummer ein.', ErrorMessages::for_classic_checkout( ValidationResult::ERROR_INVALID_NUMBER, ErrorMessages::FIELD_BILLING ) );
		$this->assertSame( 'Bitte geben Sie eine gültige Telefonnummer für die Lieferadresse ein.', ErrorMessages::for_classic_checkout( ValidationResult::ERROR_INVALID_NUMBER, ErrorMessages::FIELD_SHIPPING ) );
	}

	/**
	 * de_DE_formal is the "Sie" variant: no "du" pronoun, no "du" imperative before a
	 * sentence's first comma ("Wähle das Format" - "Wählen Sie" passes), and every
	 * "Bitte ..." request says "Sie". "/u" makes \b and \w Unicode-aware in PHP.
	 */
	public function test_formal_german_never_uses_du(): void {
		foreach ( self::read_po( 'de_DE_formal' ) as $msgid => $msgstr ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(du|dich|dir|dein\w*|euch|euer\w*)\b/iu', $msgstr, "Informal address in de_DE_formal: {$msgid}" );
			$this->assertDoesNotMatchRegularExpression( '/(^|[.!?]\s+)(Bitte\s+)?(gib|nimm|sieh|lies|wähle|lege|gehe|prüfe|aktiviere|installiere|klicke|trage)\b/iu', $msgstr, "Informal imperative in de_DE_formal: {$msgid}" );
			if ( preg_match( '/\bBitte\b/u', $msgstr ) ) {
				$this->assertMatchesRegularExpression( '/\bSie\b/u', $msgstr, "A request without \"Sie\" in de_DE_formal: {$msgid}" );
			}
		}
	}

	/**
	 * de_DE is the "du" variant, as WordPress's own de_DE: no "Sie" address. Only a
	 * capitalised "Sie/Ihr..." inside a sentence counts; at a sentence start it may be
	 * a correct third-person "sie" (the number).
	 */
	public function test_informal_german_never_uses_sie(): void {
		foreach ( self::read_po( 'de_DE' ) as $msgid => $msgstr ) {
			$this->assertDoesNotMatchRegularExpression( '/(?<=[\p{L},;]\s)(Sie|Ihnen|Ihr\w*)\b/u', $msgstr, "Formal address in de_DE: {$msgid}" );
		}
	}

	/**
	 * Only the strings that address the reader differ between the two variants. Lists
	 * English msgids, so rewording one of them in the source means updating it here.
	 */
	public function test_german_variants_differ_only_in_the_form_of_address(): void {
		$informal = self::read_po( 'de_DE' );
		$formal   = self::read_po( 'de_DE_formal' );

		$different = array_keys( array_diff_assoc( $formal, $informal ) );
		sort( $different );

		$this->assertSame(
			array(
				'Choose how phone numbers should be validated.',
				'Configure phone number validation and formatting behavior.',
				'Please enter a valid phone number.',
				'Please enter a valid shipping phone number.',
				'SHIFT64 Phone Validation for WooCommerce cannot run because its bundled vendor folder is missing. Reinstall the plugin from WordPress.org or from the release ZIP on GitHub.',
				'Select the format for storing phone numbers.',
			),
			$different
		);
	}

	public function complete_locales(): array {
		return self::cases( self::COMPLETE_LOCALES );
	}

	public function shipped_locales(): array {
		$locales = array();
		foreach ( glob( self::LANGUAGES_DIR . 'shift64-phone-validation-*.po' ) as $file ) {
			$locales[] = substr( basename( $file, '.po' ), strlen( 'shift64-phone-validation-' ) );
		}
		return self::cases( $locales );
	}

	public function german_locales(): array {
		return self::cases( array( 'de_DE', 'de_DE_formal' ) );
	}

	private static function cases( array $locales ): array {
		return array_combine( $locales, array_map( static fn ( $locale ) => array( $locale ), $locales ) );
	}

	/**
	 * Make the stubbed __() translate with the strings of a shipped .po.
	 */
	private function use_locale_file( string $locale ): void {
		$GLOBALS['shift64_test_translations'] = self::read_po( $locale );
	}

	/**
	 * Printf placeholders (%s, %1$s, ...) in order; "%%" is not one.
	 *
	 * @return string[]
	 */
	private static function placeholders( string $text ): array {
		preg_match_all( '/%(?:\d+\$)?[sdf]/', str_replace( '%%', '', $text ), $matches );
		return $matches[0];
	}

	/**
	 * @return array<string, string> msgid => msgstr, header entry left out.
	 */
	private static function read_pot(): array {
		return self::parse_po( self::LANGUAGES_DIR . 'shift64-phone-validation.pot' );
	}

	/**
	 * @return array<string, string> msgid => msgstr, header entry left out.
	 */
	private static function read_po( string $locale ): array {
		return self::parse_po( self::LANGUAGES_DIR . "shift64-phone-validation-{$locale}.po" );
	}

	/**
	 * Minimal reader for the files WP-CLI writes: no plurals, no contexts. A fuzzy entry
	 * is not translated (msgfmt leaves it out of the .mo), so it is read as empty.
	 *
	 * @return array<string, string>
	 */
	private static function parse_po( string $file ): array {
		$entries = array();
		$key     = null;
		$current = null;
		$msgid   = '';
		$fuzzy   = false;

		foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
			if ( preg_match( '/^#,.*\bfuzzy\b/', $line ) ) {
				$fuzzy = true;
			} elseif ( preg_match( '/^(msgid|msgstr) "(.*)"$/', $line, $m ) ) {
				$key = $m[1];
				if ( 'msgid' === $key ) {
					$msgid = stripcslashes( $m[2] );
				} else {
					$current = stripcslashes( $m[2] );
				}
			} elseif ( preg_match( '/^"(.*)"$/', $line, $m ) ) {
				if ( 'msgid' === $key ) {
					$msgid .= stripcslashes( $m[1] );
				} else {
					$current .= stripcslashes( $m[1] );
				}
			} elseif ( '' === trim( $line ) && null !== $current ) {
				$entries[ $msgid ] = $fuzzy ? '' : $current;
				$current           = null;
				$fuzzy             = false;
			}
		}
		if ( null !== $current ) {
			$entries[ $msgid ] = $fuzzy ? '' : $current;
		}

		unset( $entries[''] );
		return $entries;
	}

	/**
	 * Reads a GNU .mo file (little-endian, as WP-CLI writes it).
	 *
	 * @return array<string, string> msgid => msgstr, header entry left out.
	 */
	private static function read_mo( string $locale ): array {
		$data = file_get_contents( self::LANGUAGES_DIR . "shift64-phone-validation-{$locale}.mo" );

		$header = unpack( 'Vmagic/Vrevision/Vcount/Voriginals/Vtranslations', $data );
		self::assertSame( 0x950412de, $header['magic'], 'Not a little-endian .mo file.' );

		$entries = array();
		for ( $i = 0; $i < $header['count']; $i++ ) {
			$original    = unpack( 'Vlength/Voffset', $data, $header['originals'] + 8 * $i );
			$translation = unpack( 'Vlength/Voffset', $data, $header['translations'] + 8 * $i );

			$entries[ substr( $data, $original['offset'], $original['length'] ) ] = substr( $data, $translation['offset'], $translation['length'] );
		}

		unset( $entries[''] );
		return $entries;
	}
}
