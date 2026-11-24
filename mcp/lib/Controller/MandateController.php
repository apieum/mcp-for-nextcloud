<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Controller;

use OCA\Mcp\Mcp\Confirmation\AccountMandate;
use OCA\Mcp\Mcp\Confirmation\BrowserConfirmation;
use OCA\Mcp\Settings\PersonalSection;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;

final class MandateController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private BrowserConfirmation $browser,
		private AccountMandate $mandate,
		private IURLGenerator $urls,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function update(string $enabled = ''): Response {
		$uid = $this->browser->uid($this->request);
		if ($uid === null) {
			return new JSONResponse(['error' => 'Browser session required'], Http::STATUS_FORBIDDEN);
		}
		if (!in_array($enabled, ['0', '1'], true)) {
			return new JSONResponse(['error' => 'Invalid request'], Http::STATUS_BAD_REQUEST);
		}
		$this->mandate->set($uid, $enabled === '1');
		return new RedirectResponse($this->urls->linkToRoute('settings.PersonalSettings.index', ['section' => PersonalSection::ID]));
	}
}
