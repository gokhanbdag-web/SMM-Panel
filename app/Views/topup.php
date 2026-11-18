<h1>Bakiye yükle</h1>
<?php if (!empty($error)): ?><p class="error"><?=e($error)?></p><?php endif ?>
<section class="card">
    <h2>Havale / EFT</h2>
    <p>IBAN bilgileri yönetici tarafından tanımlanmalıdır.</p>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=e($_SESSION['_csrf']??=bin2hex(random_bytes(32)))?>">
        <label>Tutar (TL)</label>
        <input name="amount" type="number" min="10" max="100000" step=".01" required>
        <label>Gönderen / açıklama</label>
        <input name="note" maxlength="500">
        <button>Bildirim gönder</button>
    </form>
</section>
<section class="card">
    <h2>Yükleme geçmişi</h2>
    <?php if (!$rows): ?><p>Henüz bakiye yükleme bildiriminiz yok.</p><?php endif ?>
    <?php foreach ($rows as $r): ?>
        <p><?=e($r['created_at'])?> · <?=money($r['amount'])?> · <?=e($r['status'])?></p>
    <?php endforeach ?>
</section>
