<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCP\Config\IUserConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AccountMandateTest extends TestCase {
	public function testMandateIsOffByDefaultAndRevocableAcrossInstancesForOneAccount(): void {
		$values = [];
		$config = $this->createMock(IUserConfig::class);
		$config->method('getValueBool')->willReturnCallback(
			static function (string $uid, string $app, string $key, bool $default) use (&$values): bool {
				return $values[$uid][$app][$key] ?? $default;
			},
		);
		$config->method('setValueBool')->willReturnCallback(
			static function (string $uid, string $app, string $key, bool $value) use (&$values): bool {
				$values[$uid][$app][$key] = $value;
				return true;
			},
		);
		$first = new AccountMandate($config, new NullLogger());
		$second = new AccountMandate($config, new NullLogger());
		self::assertFalse($first->active('alice'));
		$first->set('alice', true);
		self::assertTrue($second->active('alice'));
		self::assertFalse($second->active('bob'));
		$second->set('alice', false);
		self::assertFalse($first->active('alice'));
	}
}
