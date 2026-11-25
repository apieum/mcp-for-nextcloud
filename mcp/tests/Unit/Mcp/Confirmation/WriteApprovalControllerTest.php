<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Controller\WriteApprovalController;
use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCA\Mcp\Mcp\Confirmation\ApprovalStore;
use OCA\Mcp\Mcp\Confirmation\BrowserConfirmation;
use OCA\Mcp\Mcp\Confirmation\PlanState;
use OCA\Mcp\Mcp\Confirmation\WriteApprovals;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class WriteApprovalControllerTest extends TestCase {
	public function testPostRequiresTheRealBrowserAccountAndAnExplicitDecision(): void {
		$id = '85bc1cb922bb2ec9e66da05d1634c1f0';
		$logger = new NullLogger();
		$config = $this->createMock(IUserConfig::class);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(100);
		$system = $this->createMock(IConfig::class);
		$state = new PlanState($system);
		$store = $this->createMock(ApprovalStore::class);
		$store->expects($this->once())->method('approve')->with($id, $state->of('approval-owner', 'alice', []), 100)->willReturn(true);
		$approvals = new WriteApprovals($store, new AccountMandate($config, $logger, $system), $state, $clock, $this->createMock(ICrypto::class), $logger);
		$auth = 'Bearer mcp';
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnCallback(static function (string $header) use (&$auth): string {
			return $header === 'Authorization' ? $auth : '';
		});
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('isEnabled')->willReturn(true);
		$users = $this->createMock(IUserSession::class);
		$users->method('getUser')->willReturn($user);
		$controller = new WriteApprovalController('mcp', $request, new BrowserConfirmation($users, $this->createMock(ISession::class)), $approvals, $this->createMock(IURLGenerator::class));

		self::assertSame(403, $controller->approve($id, 'approve')->getStatus());
		$auth = '';
		self::assertSame(400, $controller->approve($id)->getStatus());
		self::assertSame(200, $controller->approve($id, 'approve')->getStatus());
	}
}
