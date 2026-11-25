<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AccountMandateTest extends TestCase {
	#[DataProvider('ownerDefaults')]
	public function testAccountChoiceOverridesTheServerDefaultAcrossInstances(string $owner, bool $expected): void {
		$values = [];
		$config = $this->createMock(IUserConfig::class);
		$config->method('getValueBool')->willReturnCallback(
			static function (string $uid, string $app, string $key, bool $default) use (&$values): bool {
				return $values[$uid][$app][$key] ?? $default;
			},
		);
		$config->expects($this->exactly(3))->method('setValueBool')->willReturnCallback(
			static function (string $uid, string $app, string $key, bool $value) use (&$values): bool {
				$values[$uid][$app][$key] = $value;
				return true;
			},
		);
		$system = $this->createMock(IConfig::class);
		$system->method('getSystemValue')->with('mcp_write_mandate_owner', '')->willReturn($owner);
		$first = new AccountMandate($config, new NullLogger(), $system);
		$second = new AccountMandate($config, new NullLogger(), $system);
		self::assertSame($expected, $first->active('alice'));
		self::assertFalse($first->active(''));
		self::assertFalse($first->active('Alice'));
		self::assertFalse($first->active('bob'));
		$second->set('alice', false);
		self::assertFalse($first->active('alice'));
		$first->set('alice', true);
		self::assertTrue($second->active('alice'));
		self::assertFalse($second->active('bob'));
		$second->set('alice', false);
		self::assertFalse($first->active('alice'));
	}

	public static function ownerDefaults(): array {
		return [
			'no server owner' => ['', false],
			'designated owner' => ['alice', true],
		];
	}

	public function testBooleanServerValueCannotDesignateANumericUid(): void {
		$system = $this->createStub(IConfig::class);
		$system->method('getSystemValue')->willReturn(true);
		$config = $this->createStub(IUserConfig::class);
		$config->method('getValueBool')->willReturnArgument(3);

		self::assertFalse((new AccountMandate($config, new NullLogger(), $system))->active('1'));
	}
}
