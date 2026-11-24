<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="section">
	<h2><?= $l->t('MCP write approval') ?></h2>
	<?php if ($_['plan'] === null) { ?>
		<p><?= $l->t('Plan approved. Return to your MCP client to finish this action.') ?></p>
	<?php } else { ?>
		<pre><?= $escape($_['plan']) ?></pre>
		<form method="post" action="<?= $escape($_['action']) ?>">
			<input type="hidden" name="requesttoken" value="<?= $escape($_['token']) ?>">
			<button name="decision" value="approve" type="submit"><?= $l->t('Approve this plan') ?></button>
		</form>
	<?php } ?>
</section>
