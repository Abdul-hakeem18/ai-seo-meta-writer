<?php
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase {

	public function test_short_text_is_returned_unchanged() {
		$this->assertSame( 'Shop lab reagents online.', AISMW_Generator::clean( 'Shop lab reagents online.' ) );
	}

	public function test_quotes_and_label_are_removed() {
		$this->assertSame( 'Shop lab reagents online.', AISMW_Generator::clean( '"Meta description: Shop lab reagents online."' ) );
		$this->assertSame( 'Smart quotes go.', AISMW_Generator::clean( '“Smart quotes go.”' ) );
	}

	public function test_html_and_shortcodes_are_stripped() {
		$this->assertSame( 'Hello world', AISMW_Generator::plain_text( '<p>Hello</p> [gallery ids="1,2"] <strong>world</strong>' ) );
		$this->assertSame( 'Text', AISMW_Generator::plain_text( '<script>alert(1)</script>Text' ) );
	}

	public function test_long_text_is_cut_at_a_word_boundary_within_limit() {
		$long = str_repeat( 'Premium agarose tablets for fast gel preparation ', 10 );
		$out  = AISMW_Generator::clean( $long );
		$this->assertLessThanOrEqual( AISMW_Generator::MAX_LENGTH, mb_strlen( $out ) );
		$this->assertStringEndsWith( '…', $out );
		$this->assertDoesNotMatchRegularExpression( '/\s…$/u', $out );
	}

	public function test_multibyte_text_is_not_corrupted() {
		$text = str_repeat( 'Café crème brûlée – délicieux ', 12 );
		$out  = AISMW_Generator::clean( $text );
		$this->assertTrue( mb_check_encoding( $out, 'UTF-8' ) );
		$this->assertLessThanOrEqual( AISMW_Generator::MAX_LENGTH, mb_strlen( $out ) );
	}

	public function test_messages_include_rules_title_and_keyword() {
		$messages = AISMW_Generator::build_messages( 'Agarose Tablets', '<p>Fast gels.</p>', 'product', 'friendly', 'agarose' );
		$this->assertCount( 2, $messages );
		$this->assertStringContainsString( 'friendly tone', $messages[0]['content'] );
		$this->assertStringContainsString( '"agarose"', $messages[0]['content'] );
		$this->assertStringContainsString( 'Title: Agarose Tablets', $messages[1]['content'] );
		$this->assertStringContainsString( 'Fast gels.', $messages[1]['content'] );
	}

	public function test_source_text_is_limited() {
		$messages = AISMW_Generator::build_messages( 'T', str_repeat( 'a ', 5000 ), 'post' );
		$this->assertLessThan( AISMW_Generator::SOURCE_CHARS + 100, mb_strlen( $messages[1]['content'] ) );
	}
}
