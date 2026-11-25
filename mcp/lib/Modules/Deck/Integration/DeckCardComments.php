<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Modules\Deck\Integration;

use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\CommentService;
use OCP\ISession;
use Psr\Container\ContainerInterface;

/** Uses the same session-bound service as Deck's comment API, including its native READ permission and events. */
final class DeckCardComments {
	private DeckServiceContext $services;

	public function __construct(
		ContainerInterface $container,
		ISession $session,
		private DeckGatewayInterface $deck,
		private DeckBoardAudience $audience,
	) {
		$this->services = new DeckServiceContext($container, $session);
	}

	/** @return array<string, mixed> Authorized card, board and audience used in the approval. */
	public function target(string $uid, int $boardId, int $cardId): array {
		$board = $this->services->bindUser($uid);
		if ($this->deck->cardBoardId($uid, $cardId) !== $boardId) {
			throw new NoPermissionException('Permission denied');
		}
		$card = $this->deck->findCard($uid, $cardId);
		$board = $board->find($boardId, false);
		$owner = (string)$board->getOwner();
		$audience = $this->audience->ofStack($uid, $card->getStackId());
		usort($audience, static fn (array $a, array $b): int => [$a['type'], $a['id']] <=> [$b['type'], $b['id']]);

		// Resolve both methods before allowing an approval on an unsupported native installation.
		$this->service($uid);

		return [
			'card' => ['id' => $cardId, 'title' => $card->getTitle(), 'stackId' => $card->getStackId(),
				'lastModified' => $card->getLastModified()],
			'board' => ['id' => $boardId, 'name' => $board->getTitle(), 'owner' => $owner],
			'audience' => $audience,
		];
	}

	/** @return array<string, mixed>|null The exact comment read back by native card and comment ID. */
	public function add(string $uid, int $cardId, string $message): ?array {
		$service = $this->service($uid);
		$id = $service->create($cardId, $message)->getData()['id'] ?? null;
		if (!is_int($id) || $id < 1) {
			return null;
		}
		$comment = $service->getFormatted($cardId, $id);
		return $comment['id'] === $id && $comment['objectId'] === $cardId
			&& $comment['actorType'] === 'users' && $comment['actorId'] === $uid
			&& $comment['message'] === $message ? $comment : null;
	}

	private function service(string $uid): CommentService {
		$this->services->bindUser($uid);
		$service = $this->services->get(CommentService::class);
		if (!is_callable([$service, 'create']) || !is_callable([$service, 'getFormatted'])) {
			throw new \RuntimeException('Deck comment service unavailable');
		}
		return $service;
	}
}
