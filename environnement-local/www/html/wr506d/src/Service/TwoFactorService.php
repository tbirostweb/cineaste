<?php

namespace App\Service;

use App\Entity\User;
use OTPHP\TOTP;
use Symfony\Component\Clock\Clock;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\QrCode;
use RuntimeException;
use Throwable;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
class TwoFactorService
{
    private string $issuer;

    public function __construct(string $appName = "Movie's 2FA")
    {
        $this->issuer = $appName;
    }

    /**
     * Generate a new TOTP secret for a user
     */
    public function generateSecret(): string
    {
        $totp = TOTP::generate();
        return $totp->getSecret();
    }

    /**
     * Get TOTP instance for a user (secret actif, ou secret fourni).
     */
    private function getTOTP(User $user, ?string $secret = null): TOTP
    {
        $secret ??= $user->getTwoFactorSecret();
        if ($secret === null) {
            throw new RuntimeException('User does not have a 2FA secret');
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($user->getFirstname() ?? 'user');
        $totp->setIssuer($this->issuer);

        return $totp;
    }

    /**
     * Generate provisioning URI for QR code
     */
    public function getProvisioningUri(User $user, ?string $secret = null): string
    {
        return $this->getTOTP($user, $secret)->getProvisioningUri();
    }

    /**
     * Generate QR code as base64 image data
     */
    public function getQrCode(User $user, ?string $secret = null): string
    {
        $provisioningUri = $this->getProvisioningUri($user, $secret);

        try {
            // Tentative avec instanciation directe de Builder (compatible v4, v5, v6 si create() n'existe pas)
            $builder = new Builder(
                writer: new SvgWriter(),
                writerOptions: [],
                validateResult: false,
                data: $provisioningUri,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin
            );

            $result = $builder->build();
            return 'data:image/svg+xml;base64,' . base64_encode($result->getString());
        } catch (Throwable $e) {
            // Fallback : tentative très basique si Builder échoue
            try {
                // Si on est sur une version très ancienne ou très différente
                // On essaie de construire manuellement si les classes existent
                if (class_exists('Endroid\QrCode\QrCode')) {
                    $qrCode = new QrCode($provisioningUri);
                    // On ne configure rien d'autre pour éviter les erreurs de méthode
                    $writer = new SvgWriter();
                    $result = $writer->write($qrCode);
                    return 'data:image/svg+xml;base64,' . base64_encode($result->getString());
                }
            } catch (Throwable $e2) {
                 throw new RuntimeException(
                     'QR Code generation failed: ' . $e->getMessage() . ' | Fallback: ' . $e2->getMessage()
                 );
            }

            throw new RuntimeException('QR Code generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie un code TOTP contre le secret actif (ou le secret fourni).
     *
     * Protection anti-rejeu : le pas de temps du code accepté est mémorisé et
     * tout code d'un pas de temps égal ou antérieur est refusé ensuite.
     * Le pas courant et ses deux voisins (±30 s) sont acceptés pour absorber
     * une dérive d'horloge raisonnable.
     */
    public function verifyCode(User $user, string $code, ?string $secret = null): bool
    {
        $secret ??= $user->getTwoFactorSecret();
        if (!$secret || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);
        $period = $totp->getPeriod();
        $now = $this->clock();
        $lastUsed = $user->getTwoFactorAuth()?->getLastUsedTimestep();

        foreach ([0, -1, 1] as $offset) {
            $timestamp = $now + $offset * $period;
            $timestep = intdiv($timestamp, $period);
            if (!hash_equals($totp->at($timestamp), $code)) {
                continue;
            }
            if ($lastUsed !== null && $timestep <= $lastUsed) {
                return false;
            }
            $user->getTwoFactorAuth()?->setLastUsedTimestep($timestep);

            return true;
        }

        return false;
    }

    /** Horloge Symfony : remplaçable dans les tests (ClockSensitiveTrait). */
    protected function clock(): int
    {
        return Clock::get()->now()->getTimestamp();
    }

    /** Code TOTP courant pour un secret (outil de test et de diagnostic). */
    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return TOTP::createFromSecret($secret)->at($timestamp ?? $this->clock());
    }

    /**
     * Generate backup codes
     * @return list<string>
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Generate 8-character alphanumeric codes
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage
     * @param list<string> $codes
     * @return list<string>
     */
    public function hashBackupCodes(array $codes): array
    {
        return array_map(fn($code) => hash('sha256', $code), $codes);
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $hashedCode = hash('sha256', strtoupper(trim($code)));
        $backupCodes = $user->getTwoFactorBackupCodes();

        if ($backupCodes === null) {
            return false;
        }

        foreach ($backupCodes as $storedCode) {
            if (is_string($storedCode) && hash_equals($storedCode, $hashedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove used backup code
     */
    public function removeBackupCode(User $user, string $code): void
    {
        $hashedCode = hash('sha256', strtoupper(trim($code)));
        $backupCodes = $user->getTwoFactorBackupCodes();

        if ($backupCodes === null) {
            return;
        }

        $backupCodes = array_filter(
            $backupCodes,
            fn($storedCode) => $storedCode !== $hashedCode
        );

        $user->setTwoFactorBackupCodes(array_values($backupCodes));
    }
}
