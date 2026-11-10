<?php

namespace App\Support;

use App\Models\SkParticipant;

class SkImportIdentity
{
    public static function digest(SkParticipant $participant): string
    {
        $attributes = $participant->getAttributes();
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, scalar|null> */
    public static function values(SkParticipant $participant): array
    {
        $fields = ['sk_number', 'sk_order', 'name_on_sk', 'official_participant_id', 'phone_number', 'internal_note', 'assigned_operator_id', ...ParticipantOrigin::FIELDS];
        $values = ['sk_date' => $participant->sk_date->format('Y-m-d')];
        foreach ($fields as $field) {
            $values[$field] = $participant->getAttribute($field);
        }

        return $values;
    }
}
