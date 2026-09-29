<?php
/**
 * 経費の入力欄（イベントの会計・イベントに付かない経費・経費を直す で共通）
 *
 * @var array $values
 * @var list<string> $items 項目の候補
 * @var bool $needsDate 支払った日が必須か（イベントに付かない経費）
 * @var string $prefix  datalist の id の区別
 */
$listId = 'expense-items-' . $prefix;
?>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">項目</span>
        <input type="text" name="item" list="<?= e($listId) ?>" maxlength="100" required value="<?= e($values['item'] ?? '') ?>">
        <datalist id="<?= e($listId) ?>">
            <?php foreach ($items as $item): ?><option value="<?= e($item) ?>"><?php endforeach; ?>
        </datalist>
    </label>
    <label class="form__field">
        <span class="form__label">金額（円）</span>
        <input type="number" name="amount" min="0" inputmode="numeric" required value="<?= e((string) ($values['amount'] ?? '')) ?>">
    </label>
</div>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">勘定科目（確定申告の区分）</span>
        <select name="account">
            <option value="">項目の名前から自動で選ぶ</option>
            <?php foreach (App\Expenses::ACCOUNTS as $account => $hint): ?>
                <option value="<?= e($account) ?>"<?= ($values['account'] ?? null) === $account ? ' selected' : '' ?>><?= e($account) ?>：<?= e($hint) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="form__field">
        <span class="form__label">支払った日<?= $needsDate ? '<span class="req">必須</span>' : '' ?></span>
        <input type="date" name="paid_on" value="<?= e((string) ($values['paid_on'] ?? '')) ?>"<?= $needsDate ? ' required' : '' ?>>
        <?php if (!$needsDate): ?><span class="form__help">空欄ならイベントの日にします</span><?php endif; ?>
    </label>
</div>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">支払先（任意）</span>
        <input type="text" name="payee" maxlength="100" value="<?= e((string) ($values['payee'] ?? '')) ?>" placeholder="店・会場・講師の名前など">
    </label>
    <label class="form__field">
        <span class="form__label">メモ（任意）</span>
        <input type="text" name="memo" maxlength="255" value="<?= e((string) ($values['memo'] ?? '')) ?>">
    </label>
</div>
<label class="form__check"><input type="checkbox" name="has_receipt" value="1"<?= !empty($values['has_receipt']) ? ' checked' : '' ?>> <span>領収書・レシートを保管している</span></label>
