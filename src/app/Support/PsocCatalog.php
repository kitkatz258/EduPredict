<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Program;
use App\Models\PsocOccupation;
use Illuminate\Support\Collection;

/**
 * Official PSA PSOC 2012 major groups (ISCO-08 aligned).
 * Occupations keep their stored codes; this catalog does not invent unit-group identifiers.
 */
final class PsocCatalog
{
    /**
     * @return array<string, string>
     */
    public static function majorGroups(): array
    {
        return [
            '0' => 'Armed Forces Occupations',
            '1' => 'Managers',
            '2' => 'Professionals',
            '3' => 'Technicians and Associate Professionals',
            '4' => 'Clerical Support Workers',
            '5' => 'Service and Sales Workers',
            '6' => 'Skilled Agricultural, Forestry and Fishery Workers',
            '7' => 'Craft and Related Trades Workers',
            '8' => 'Plant and Machine Operators and Assemblers',
            '9' => 'Elementary Occupations',
        ];
    }

    public static function majorGroupForCode(string $code): ?string
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if ($code === '') {
            return null;
        }

        return self::majorGroups()[$code[0]] ?? null;
    }

    public static function isOfficialMajorGroup(string $group): bool
    {
        return in_array($group, self::majorGroups(), true);
    }

    public static function codeLooksOfficial(string $code): bool
    {
        return (bool) preg_match('/^[0-9]{1,4}$/', trim($code));
    }

    /**
     * @return list<string>
     */
    public static function knownSkillTags(): array
    {
        $tags = PsocOccupation::query()->pluck('skill_tags')->flatten()->filter()->map(
            fn (mixed $tag): string => mb_strtolower(trim((string) $tag)),
        )->filter()->unique()->sort()->values();

        return $tags->all();
    }

    /**
     * @return Collection<int, Program>
     */
    public static function programOptions(): Collection
    {
        return Program::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']);
    }
}
