<?php

function issue_email_verification_token(PDO $pdo, int $userId, int $ttlMinutes = 30): string
{
    $token = create_token_string();
    $hash = token_hash($token);

    $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?")->execute([$userId]);
    $stmt = $pdo->prepare("
        INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ");
    $stmt->execute([$userId, $hash, $ttlMinutes]);

    return $token;
}

function consume_email_verification_token(PDO $pdo, string $token): ?array
{
    $hash = token_hash($token);
    $stmt = $pdo->prepare("
        SELECT evt.id, evt.user_id, u.role
        FROM email_verification_tokens evt
        INNER JOIN users u ON u.id = evt.user_id
        WHERE evt.token_hash = ?
          AND evt.used_at IS NULL
          AND evt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE email_verification_tokens
            SET used_at = NOW()
            WHERE id = ? AND used_at IS NULL
        ")->execute([$row['id']]);

        if ($row['role'] === 'teacher') {
            $pdo->prepare("
                UPDATE users
                SET is_email_verified = 1,
                    email_verified_at = NOW(),
                    status = 'pending'
                WHERE id = ?
            ")->execute([$row['user_id']]);
        } else {
            $pdo->prepare("
                UPDATE users
                SET is_email_verified = 1,
                    email_verified_at = NOW(),
                    is_approved = 1,
                    approved_at = COALESCE(approved_at, NOW()),
                    status = 'active'
                WHERE id = ?
            ")->execute([$row['user_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $row;
}

function issue_password_reset_token(PDO $pdo, int $userId, int $ttlMinutes = 20): string
{
    $token = create_token_string();
    $hash = token_hash($token);

    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ")->execute([$userId, $hash, $ttlMinutes]);

    return $token;
}

function consume_password_reset_token(PDO $pdo, string $token): ?int
{
    $hash = token_hash($token);
    $stmt = $pdo->prepare("
        SELECT id, user_id
        FROM password_reset_tokens
        WHERE token_hash = ?
          AND used_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
    return (int)$row['user_id'];
}
