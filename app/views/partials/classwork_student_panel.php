<?php
// Included only from the authenticated subject classroom, after enrollment verification.
if (!isset($classworkReady, $classworkItems, $enrollment, $csrf_token)) return;
?>
<div class="cw-student">
    <?php if ($classworkNotice): ?><p class="cw-notice" role="status"><?= kns_subject_escape($classworkNotice) ?></p><?php endif; ?>
    <?php if ($classworkError): ?><p class="cw-notice cw-error" role="alert"><?= kns_subject_escape($classworkError) ?></p><?php endif; ?>
    <?php foreach ($classworkItems as $item): ?>
        <article class="cw-card" data-classwork-category="<?= kns_subject_escape($item['category']) ?>" id="classwork-<?= (int) $item['id'] ?>">
            <h3><?= kns_subject_escape($item['title']) ?></h3>
            <p><?= kns_subject_escape(ucfirst($item['category'])) ?> &middot; <?= count($item['questions']) ?> questions &middot; <?= array_sum(array_column($item['questions'], 'points')) ?> points</p>
            <?php if ($item['instructions'] !== ''): ?><p class="cw-text"><?= kns_subject_escape($item['instructions']) ?></p><?php endif; ?>
            <?php if (!empty($item['due_at'])): ?><p>Deadline: <?= kns_subject_escape($item['due_at']) ?></p><?php endif; ?><?php if ($item['score'] !== null): ?><p class="cw-notice">Grade: <?= kns_subject_escape($item['score']) ?> / <?= kns_subject_escape($item['max_score']) ?><br><?= kns_subject_escape($item['feedback']) ?></p><?php endif; ?><?php if ($item['submitted_at']): ?><p class="cw-notice">Submitted <?= kns_subject_escape($item['submitted_at']) ?>. Your answers are saved for professor review. Grades appear after professor review.</p><?php endif; ?>
            <details <?= !$item['submitted_at'] && count($classworkItems) === 1 ? 'open' : '' ?>><summary><?= $item['submitted_at'] ? 'View your submitted answers' : 'Answer in Student Portal' ?></summary>
                <form method="post" action="classwork_submit.php" class="cw-answer-form">
                    <input type="hidden" name="csrf_token" value="<?= kns_subject_escape($csrf_token) ?>">
                    <input type="hidden" name="subject_id" value="<?= (int) $subject_id ?>">
                    <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                    <input type="hidden" name="classwork_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="embedded" value="<?= $subject_embedded ? '1' : '0' ?>">
                    <?php foreach ($item['questions'] as $index => $question): $answer = $item['answers'][$index] ?? ''; ?>
                        <fieldset class="cw-question" <?= $item['submitted_at'] ? 'disabled' : '' ?>>
                            <legend><?= ($index + 1) ?>. <?= kns_subject_escape($question['text']) ?> (<?= kns_subject_escape($question['points']) ?> points)</legend>
                            <?php if ($question['type'] === 'multiple_choice'): ?>
                                <?php foreach ($question['choices'] as $choiceIndex => $choice): ?>
                                    <label class="cw-choice"><input type="radio" name="answers[<?= $index ?>]" value="<?= $choiceIndex ?>" required <?= $answer !== '' && (string) $answer === (string) $choiceIndex ? 'checked' : '' ?>><span><?= chr(65 + $choiceIndex) ?>) <?= kns_subject_escape($choice) ?></span></label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <label>Your answer<textarea name="answers[<?= $index ?>]" rows="3" maxlength="10000" required><?= kns_subject_escape($answer) ?></textarea></label>
                            <?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <?php if (!$item['submitted_at']): ?><p>Review your responses before submitting. Each classwork accepts one submission per enrollment.</p><button type="submit">Submit answers</button><?php endif; ?>
                </form>
            </details>
        </article>
    <?php endforeach; ?>
</div>
