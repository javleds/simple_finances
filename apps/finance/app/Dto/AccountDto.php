<?php

namespace App\Dto;

class AccountDto
{
    public string $name;

    public ?string $color;

    public ?string $description;

    public bool $isVirtual;

    public bool $isCreditCard;

    public ?float $creditLine;

    public ?int $cutOffDay;

    public ?int $feedAccountId;

    public function __construct(string $name, ?string $color, ?string $description, bool $isVirtual, bool $isCreditCard, ?float $creditLine, ?int $cutOffDay, ?int $feedAccountId)
    {
        $this->name = $name;
        $this->color = $color;
        $this->description = $description;
        $this->isVirtual = $isVirtual;
        $this->isCreditCard = $isCreditCard;
        $this->creditLine = $creditLine;
        $this->cutOffDay = $cutOffDay;
        $this->feedAccountId = $feedAccountId;
    }

    public static function fromFormArray(array $data): self
    {
        return new static(
            $data['name'],
            $data['color'] ?? null,
            $data['description'] ?? null,
            $data['virtual'] ?? false,
            $data['credit_card'] ?? false,
            $data['credit_line'] ?? false,
            $data['cutoff_day'] ?? false,
            $data['feed_account_id'] ?? null,
        );
    }

    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
            'virtual' => $this->isVirtual,
            'credit_card' => $this->isCreditCard,
            'credit_line' => $this->creditLine,
            'cutoff_day' => $this->cutOffDay,
            'feed_account_id' => $this->feedAccountId,
        ];
    }
}
