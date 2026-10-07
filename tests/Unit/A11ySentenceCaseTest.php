<?php

declare(strict_types=1);

namespace Ccd\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class A11ySentenceCaseTest extends TestCase {
	public function testEmpty(): void {
		$this->assertSame('', ccd_a11y_sentence_case(''));
		$this->assertSame('', ccd_a11y_sentence_case('   '));
	}

	public function testLowercasesRest(): void {
		$this->assertSame('Diabetes', ccd_a11y_sentence_case('DIABETES'));
		$this->assertSame('Receitas doces', ccd_a11y_sentence_case('RECEITAS DOCES'));
	}

	public function testUtf8(): void {
		$this->assertSame('Alimentação', ccd_a11y_sentence_case('ALIMENTAÇÃO'));
	}
}
