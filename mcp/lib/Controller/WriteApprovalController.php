<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Controller;

use OCA\Mcp\Mcp\Confirmation\BrowserConfirmation;
use OCA\Mcp\Mcp\Confirmation\WriteApprovals;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

/** The owner reviews a preview and explicitly approves it in the Nextcloud web session. */
final class WriteApprovalController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private BrowserConfirmation $browser,
		private WriteApprovals $approvals,
		private IURLGenerator $urls,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(string $id): Response {
		$uid = $this->browser->uid($this->request);
		if ($uid === null) {
			return new JSONResponse(['error' => 'Browser session required'], Http::STATUS_FORBIDDEN);
		}
		$plan = $this->approvals->review($id, $uid);
		if ($plan === null) {
			return new JSONResponse(['error' => 'Plan unavailable'], Http::STATUS_NOT_FOUND);
		}
		return $this->page([
			'plan' => $plan,
			'action' => $this->urls->linkToRoute('mcp.write_approval.approve', ['id' => $id]),
			'token' => Util::callRegister(),
		]);
	}

	#[NoAdminRequired]
	public function approve(string $id, string $decision = ''): Response {
		$uid = $this->browser->uid($this->request);
		if ($uid === null) {
			return new JSONResponse(['error' => 'Browser session required'], Http::STATUS_FORBIDDEN);
		}
		if ($decision !== 'approve') {
			return new JSONResponse(['error' => 'Invalid decision'], Http::STATUS_BAD_REQUEST);
		}
		if (!$this->approvals->approve($id, $uid)) {
			return new JSONResponse(['error' => 'Plan unavailable'], Http::STATUS_NOT_FOUND);
		}
		return $this->page(['plan' => null]);
	}

	/** @param array<string, mixed> $data */
	private function page(array $data): TemplateResponse {
		$response = new TemplateResponse('mcp', 'approval', $data);
		$response->addHeader('Cache-Control', 'no-store');
		$response->addHeader('Referrer-Policy', 'no-referrer');
		$response->addHeader('X-Frame-Options', 'DENY');
		return $response;
	}
}
