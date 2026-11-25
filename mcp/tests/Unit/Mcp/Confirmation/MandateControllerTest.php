<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Controller\MandateController;
use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCA\Mcp\Mcp\Confirmation\BrowserConfirmation;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MandateControllerTest extends TestCase {
	public function testExplicitPostOnlyChangesTheSignedInAccount(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturn('');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('isEnabled')->willReturn(true);
		$users = $this->createMock(IUserSession::class);
		$users->method('getUser')->willReturn($user);
		$config = $this->createMock(IUserConfig::class);
		$config->expects($this->once())->method('setValueBool')->with('alice', 'mcp', 'write_account_mandate', true)->willReturn(true);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturn('/settings/user/mcp');
		$mandate = new AccountMandate($config, new NullLogger(), $this->createMock(IConfig::class));
		$controller = new MandateController('mcp', $request, new BrowserConfirmation($users, $this->createMock(ISession::class)), $mandate, $urls);
		self::assertSame(400, $controller->update('yes')->getStatus());
		self::assertSame(303, $controller->update('1')->getStatus());
	}
}
