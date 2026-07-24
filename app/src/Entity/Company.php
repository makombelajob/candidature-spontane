<?php

namespace App\Entity;

final class Company
{
    private ?int $id = null;
    private ?string $siren = null;
    private ?string $siret = null;
    private ?string $name = null;
    private ?string $activeNaf = null;
    private ?string $adresse = null;
    private ?string $codePostal = null;
    private ?string $city = null;
    private ?string $effectif = null;
    private ?string $createdAt = null;
    private ?string $phone = null;
    private ?string $email = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function getSiren(): ?string { return $this->siren; }
    public function setSiren(?string $siren): self { $this->siren = $siren; return $this; }
    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): self { $this->siret = $siret; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }
    public function getActiveNaf(): ?string { return $this->activeNaf; }
    public function setActiveNaf(?string $activeNaf): self { $this->activeNaf = $activeNaf; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): self { $this->adresse = $adresse; return $this; }
    public function getCodePostal(): ?string { return $this->codePostal; }
    public function setCodePostal(?string $codePostal): self { $this->codePostal = $codePostal; return $this; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): self { $this->city = $city; return $this; }
    public function getEffectif(): ?string { return $this->effectif; }
    public function setEffectif(?string $effectif): self { $this->effectif = $effectif; return $this; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
}
