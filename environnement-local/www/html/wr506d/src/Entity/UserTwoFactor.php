<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "user_two_factor")]
class UserTwoFactor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(nullable: true)]
    private ?array $backupCodes = null;

    /**
     * Secret en cours d'enrôlement. Tant qu'il n'est pas confirmé par un code
     * valide (/api/2fa/enable), le secret actif et les codes de secours
     * restent inchangés : un appel à /setup ne peut plus désactiver la 2FA.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pendingSecret = null;

    /**
     * Dernier pas de temps TOTP accepté : un code déjà utilisé ne peut pas
     * être rejoué pendant sa fenêtre de validité.
     */
    #[ORM\Column(nullable: true)]
    private ?int $lastUsedTimestep = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): static
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    public function getPendingSecret(): ?string
    {
        return $this->pendingSecret;
    }

    public function setPendingSecret(?string $pendingSecret): static
    {
        $this->pendingSecret = $pendingSecret;

        return $this;
    }

    public function getLastUsedTimestep(): ?int
    {
        return $this->lastUsedTimestep;
    }

    public function setLastUsedTimestep(?int $lastUsedTimestep): static
    {
        $this->lastUsedTimestep = $lastUsedTimestep;

        return $this;
    }
}
