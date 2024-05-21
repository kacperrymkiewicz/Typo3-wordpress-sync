<?php

namespace App\Entity;

use App\Repository\EntryMapperRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryMapperRepository::class)]
class EntryMapper
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $typo_id = null;

    #[ORM\Column]
    private ?int $wordpress_id = null;

    #[ORM\Column]
    private ?int $sync_time = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypoId(): ?int
    {
        return $this->typo_id;
    }

    public function setTypoId(int $typo_id): static
    {
        $this->typo_id = $typo_id;

        return $this;
    }

    public function getWordpressId(): ?int
    {
        return $this->wordpress_id;
    }

    public function setWordpressId(int $wordpress_id): static
    {
        $this->wordpress_id = $wordpress_id;

        return $this;
    }

    public function getSyncTime(): ?int
    {
        return $this->sync_time;
    }

    public function setSyncTime(int $sync_time): static
    {
        $this->sync_time = $sync_time;

        return $this;
    }
}
