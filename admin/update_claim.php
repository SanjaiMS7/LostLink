<?php
/**
 * Approve or reject a claim (administrators only, POST + CSRF).
 * Approving a claim marks the item as Claimed and rejects the other
 * pending claims of the same item.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Only allow returning to the two admin pages that show claims.
$back = input('back');
if (!preg_match('/^manage_(claims|items)\.php(\?[A-Za-z0-9_=&]{0,60})?$/', $back)) {
    $back = 'manage_claims.php';
}
$back = 'admin/' . $back;

if (!is_post()) {
    redirect($back);
}
if (!csrf_verify()) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($back);
}

$claimId = ctype_digit(input('claim_id')) ? (int)input('claim_id') : 0;
$action  = input('action');
if (!in_array($action, ['approve', 'reject'], true)) {
    set_flash('error', 'Invalid action.');
    redirect($back);
}
$adminId = (int)$_SESSION['admin_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT claim_id, item_id, status FROM claims WHERE claim_id = ? FOR UPDATE');
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        $pdo->rollBack();
        set_flash('error', 'The claim was not found.');
        redirect($back);
    }
    if ($claim['status'] !== 'pending') {
        $pdo->rollBack();
        set_flash('error', 'This claim has already been reviewed.');
        redirect($back);
    }

    if ($action === 'reject') {
        $pdo->prepare("UPDATE claims SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE claim_id = ?")
            ->execute([$adminId, $claimId]);
        $pdo->commit();
        set_flash('success', 'The claim has been rejected.');
        redirect($back);
    }

    // Approve: the item must still be open.
    $stmt = $pdo->prepare('SELECT status FROM items WHERE item_id = ? FOR UPDATE');
    $stmt->execute([$claim['item_id']]);
    $itemStatus = $stmt->fetchColumn();
    if ($itemStatus !== 'open') {
        $pdo->rollBack();
        set_flash('error', 'The item is not open any more, so the claim cannot be approved.');
        redirect($back);
    }

    $pdo->prepare("UPDATE claims SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE claim_id = ?")
        ->execute([$adminId, $claimId]);
    $pdo->prepare("UPDATE items SET status = 'claimed' WHERE item_id = ?")
        ->execute([$claim['item_id']]);
    $pdo->prepare("UPDATE claims SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                    WHERE item_id = ? AND claim_id <> ? AND status = 'pending'")
        ->execute([$adminId, $claim['item_id'], $claimId]);
    $pdo->commit();

    set_flash('success', 'The claim has been approved and the item is marked as Claimed.');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'The claim could not be updated. Please try again.');
}
redirect($back);
