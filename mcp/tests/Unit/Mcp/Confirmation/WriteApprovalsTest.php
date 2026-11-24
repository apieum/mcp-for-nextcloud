<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCA\Mcp\Mcp\Confirmation\ApprovalStore;
use OCA\Mcp\Mcp\Confirmation\PlanState;
use OCA\Mcp\Mcp\Confirmation\WriteApprovals;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class WriteApprovalsTest extends TestCase {
	public function testUseNeedsAnExactPreviewAndEitherCurrentMandateOrBrowserApproval(): void {
		$active = false;
		$options = $this->createMock(IUserConfig::class);
		$options->method('getValueBool')->willReturnCallback(static function (string $uid) use (&$active): bool {
			return $uid === 'alice' && $active;
		});
		$logger = new NullLogger();
		$mandate = new AccountMandate($options, $logger);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturn('test-secret');
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(100);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $text): string => 'encrypted:' . $text);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $text): string => substr($text, 10));
		$store = $this->createMock(ApprovalStore::class);
		$row = [];
		$store->method('insert')->willReturnCallback(static function (array $value) use (&$row): void {
			$row = $value + ['approved' => 0];
		});
		$store->method('find')->willReturnCallback(static function (string $id, string $uid) use (&$row): ?array {
			return ($row['id'] ?? '') === $id && $row['uid'] === $uid ? $row : null;
		});
		$store->method('approve')->willReturnCallback(static function (string $id, string $uid) use (&$row): bool {
			if (($row['id'] ?? '') !== $id || $row['uid'] !== $uid) {
				return false;
			}
			$row['approved'] = 1;
			return true;
		});
		$store->method('consume')->willReturnCallback(static function () use (&$row): bool {
			$row = [];
			return true;
		});

		$preview = new WriteApprovals($store, $mandate, new PlanState($config), $clock, $crypto, $logger);
		$execution = new WriteApprovals($store, $mandate, new PlanState($config), $clock, $crypto, $logger);
		$args = ['value' => 'one'];
		$id = $preview->stage('edit', 'alice', $args, 'Review one');
		self::assertSame('encrypted:Review one', $row['display']);
		self::assertSame((new PlanState($config))->of('approval-owner', 'alice', []), $row['uid']);
		self::assertNull($execution->review($id, 'Alice'));
		self::assertSame('Review one', $execution->review($id, 'alice'));
		self::assertNull($execution->review($id, 'bob'));
		self::assertFalse($execution->approve($id, 'bob'));
		self::assertFalse($execution->authorize($id, 'edit', 'alice', $args));
		self::assertTrue($execution->approve($id, 'alice'));
		self::assertFalse($execution->authorize($id, 'edit-other', 'alice', $args));
		self::assertFalse($execution->authorize($id, 'edit', 'alice', ['value' => 'two']));
		$active = true;
		self::assertFalse($execution->authorize($id, 'edit', 'bob', $args));
		self::assertTrue($execution->authorize($id, 'edit', 'alice', $args));
		self::assertFalse($execution->authorize($id, 'edit', 'alice', $args));
		$id = $preview->stage('edit', 'alice', $args, 'Review one');
		$active = false;
		self::assertFalse($execution->authorize($id, 'edit', 'alice', $args));
		self::assertTrue($execution->approve($id, 'alice'));
		self::assertTrue($execution->authorize($id, 'edit', 'alice', $args));
	}
}
