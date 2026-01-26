<?php

namespace App\Mapper;

use App\DTO\FixtureDTO;
use App\Models\Fixture;

class FixtureMapper
{
    public static function fromModel(Fixture $fixture): FixtureDTO
    {
        $dto = new FixtureDTO(
            $fixture->id,
            $fixture->utc_date->format('Y-m-d\TH:i:s\Z'),
            $fixture->status,
            $fixture->matchday ?? 1,
            $fixture->stage,
            $fixture->group,
            ScoreMapper::fromModel($fixture)
        );

        if ($fixture->homeTeam) {
            $dto->setHomeTeam(TeamMapper::fromModel($fixture->homeTeam));
        }
        if ($fixture->awayTeam) {
            $dto->setAwayTeam(TeamMapper::fromModel($fixture->awayTeam));
        }

        return $dto;
    }

    public static function fromArray(array $data): FixtureDTO
    {
        $dto = new FixtureDTO(
            $data['id'],
            $data['utcDate'],
            $data['status'],
            $data['matchday'],
            $data['stage'] ?? null,
            $data['group'] ?? null,
            ScoreMapper::fromArray($data['score'])
        );

        $dto->setHomeTeam(TeamMapper::fromArray($data['homeTeam']));
        $dto->setAwayTeam(TeamMapper::fromArray($data['awayTeam']));

        return $dto;
    }
}
