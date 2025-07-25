<?php
function verifyAndUpgradePassword($pdo, $driverId, $inputPassword, $storedHash) {
    logActivity("Password verification started for Driver ID: $driverId");

    // Check for old MD5 hash
    if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
        logActivity("Detected legacy MD5 password format for Driver ID: $driverId");

        if (md5($inputPassword) === $storedHash) {
            logActivity("MD5 password matched for Driver ID: $driverId. Upgrading to bcrypt...");

            $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE driver SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $driverId]);

            logActivity("Password successfully upgraded to bcrypt for Driver ID: $driverId");
            return true;
        } else {
            logActivity("MD5 password verification failed for Driver ID: $driverId");
            return false;
        }
    }

    // Check if bcrypt/argon2 hash is valid
    if (password_verify($inputPassword, $storedHash)) {
        logActivity("Password matched using modern hashing for Driver ID: $driverId");

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            logActivity("Hash needs rehashing for Driver ID: $driverId. Rehashing now...");
            $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE driver SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $driverId]);

            logActivity("Password rehash completed for Driver ID: $driverId");
        }

        return true;
    }

    logActivity("Password verification failed for Driver ID: $driverId");
    return false;
}

function verifyAndUpgradeSecretAnswer($pdo, $driverId, $inputSecretAnswer, $storedHash) {
    logActivity("Secret Answer verification started for Driver ID: $driverId");

    // Check for old MD5 hash
    if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
        logActivity("Detected legacy MD5 secret answer format for Driver ID: $driverId");

        if (md5( $inputSecretAnswer) === $storedHash) {
            logActivity("MD5 secret answer matched for Driver ID: $driverId. Upgrading to bcrypt...");

            $newHash = password_hash( $inputSecretAnswer, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE driver SET secret_answer = ? WHERE id = ?");
            $stmt->execute([$newHash, $driverId]);

            logActivity("Secret Answer successfully upgraded to bcrypt for Driver ID: $driverId");
            return true;
        } else {
            logActivity("MD5 secret Answer verification failed for Driver ID: $driverId");
            return false;
        }
    }

    // Check if bcrypt/argon2 hash is valid
    if (password_verify($inputSecretAnswer, $storedHash)) {
        logActivity("Secret Answer matched using modern hashing for Driver ID: $driverId");

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            logActivity("Secret Answer Hash needs rehashing for Driver ID: $driverId. Rehashing now...");
            $newHash = password_hash($inputSecretAnswer, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE driver SET secret_answer = ? WHERE id = ?");
            $stmt->execute([$newHash, $driverId]);

            logActivity("Secret Answer rehash completed for Driver ID: $driverId");
        }

        return true;
    }

    logActivity("Secret Answer verification failed for Driver ID: $driverId");
    return false;
}