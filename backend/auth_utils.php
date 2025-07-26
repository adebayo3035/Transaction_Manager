<?php
function verifyAndUpgradePassword($pdo, $adminId, $inputPassword, $storedHash) {
    logActivity("Password verification started for Admin ID: $adminId");

    // Check for old MD5 hash
    if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
        logActivity("Detected legacy MD5 password format for Admin ID: $adminId");

        if (md5($inputPassword) === $storedHash) {
            logActivity("MD5 password matched for Admin ID: $adminId. Upgrading to bcrypt...");

            $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_tbl SET password = ? WHERE unique_id = ?");
            $stmt->execute([$newHash, $adminId]);

            logActivity("Password successfully upgraded to bcrypt for Admin ID: $adminId");
            return true;
        } else {
            logActivity("MD5 password verification failed for Admin ID: $adminId");
            return false;
        }
    }

    // Check if bcrypt/argon2 hash is valid
    if (password_verify($inputPassword, $storedHash)) {
        logActivity("Password matched using modern hashing for Admin ID: $adminId");

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            logActivity("Hash needs rehashing for Admin ID: $adminId. Rehashing now...");
            $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_tbl SET password = ? WHERE unique_id = ?");
            $stmt->execute([$newHash, $adminId]);

            logActivity("Password rehash completed for Admin ID: $adminId");
        }

        return true;
    }

    logActivity("Password verification failed for Admin ID: $adminId");
    return false;
}

function verifyAndUpgradeSecretAnswer($pdo, $adminId, $inputSecretAnswer, $storedHash) {
    logActivity("Secret Answer verification started for Admin ID: $adminId");

    // Check for old MD5 hash
    if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
        logActivity("Detected legacy MD5 secret answer format for Admin ID: $adminId");

        if (md5( $inputSecretAnswer) === $storedHash) {
            logActivity("MD5 secret answer matched for Admin ID: $adminId. Upgrading to bcrypt...");

            $newHash = password_hash( $inputSecretAnswer, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_tbl SET secret_answer = ? WHERE unique_id = ?");
            $stmt->execute([$newHash, $adminId]);

            logActivity("Secret Answer successfully upgraded to bcrypt for Admin ID: $adminId");
            return true;
        } else {
            logActivity("MD5 secret Answer verification failed for Admin ID: $adminId");
            return false;
        }
    }

    // Check if bcrypt/argon2 hash is valid
    if (password_verify($inputSecretAnswer, $storedHash)) {
        logActivity("Secret Answer matched using modern hashing for Admin ID: $adminId");

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            logActivity("Secret Answer Hash needs rehashing for Admin ID: $adminId. Rehashing now...");
            $newHash = password_hash($inputSecretAnswer, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_tbl SET secret_answer = ? WHERE unique_id = ?");
            $stmt->execute([$newHash, $adminId]);

            logActivity("Secret Answer rehash completed for Admin ID: $adminId");
        }

        return true;
    }

    logActivity("Secret Answer verification failed for Admin ID: $adminId");
    return false;
}

function verifyAndUpgradePIN($pdo, $adminId, $cardId, $inputPIN, $storedHash) {
    logActivity("PIN verification started for Admin ID: $adminId, Card ID: $cardId");

    // Check for old MD5 hash
    if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
        logActivity("Detected legacy MD5 PIN format for Card ID: $cardId");

        if (md5($inputPIN) === $storedHash) {
            logActivity("MD5 PIN matched for Card ID: $cardId. Upgrading to bcrypt...");

            $newHash = password_hash($inputPIN, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE cards SET card_pin = ? WHERE id = ? AND unique_id = ?");
            $stmt->execute([$newHash, $cardId, $adminId]);

            logActivity("PIN successfully upgraded to bcrypt for Card ID: $cardId");
            return true;
        } else {
            logActivity("MD5 PIN verification failed for Card ID: $cardId");
            return false;
        }
    }

    // Modern hash verification
    if (password_verify($inputPIN, $storedHash)) {
        logActivity("PIN matched using modern hashing for Card ID: $cardId");

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            logActivity("PIN hash needs rehashing for Card ID: $cardId. Rehashing now...");

            $newHash = password_hash($inputPIN, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE cards SET card_pin = ? WHERE id = ? AND unique_id = ?");
            $stmt->execute([$newHash, $cardId, $adminId]);

            logActivity("PIN rehash completed for Card ID: $cardId");
        }

        return true;
    }

    logActivity("PIN verification failed for Card ID: $cardId");
    return false;
}



