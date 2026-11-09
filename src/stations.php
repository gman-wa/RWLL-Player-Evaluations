<?php
declare(strict_types=1);

/*
 * The eval stations. This one list drives the station forms, server-side validation, the "already scored" check,
 * the admin progress table and the CSV - add a station or a field here, and add its column to `evaluations`.
 *
 * Field types:
 *   scale   tap one of min..max (int), always sent, starts at `default`
 *   number  free decimal entry (decimal(6,2) columns), `required` or blank = NULL
 *   yesno   0 / 1
 */
const STATIONS = [
    'fielding' => [
        'title'  => 'Fielding',
        'fields' => [
            'fielding_ground_ball' => ['label' => 'Ground balls', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
            'fielding_fly_ball'    => ['label' => 'Fly balls', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
            'fielding_throwing'    => ['label' => 'Throwing', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
            'fielding_iq'          => ['label' => 'IQ', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
        ],
    ],
    'hitting' => [
        'title'  => 'Hitting',
        'fields' => [
            'hitting_contact'   => ['label' => 'Contact', 'type' => 'scale', 'min' => 0, 'max' => 7, 'default' => 5],
            'hitting_power'     => ['label' => 'Power', 'type' => 'number', 'step' => '0.1', 'required' => false],
            'hitting_mechanics' => ['label' => 'Mechanics', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
        ],
    ],
    'pitching' => [
        'title'  => 'Pitching',
        'fields' => [
            'pitching_velocity'  => ['label' => 'Velocity', 'unit' => 'mph', 'type' => 'number', 'step' => '0.1', 'required' => true],
            'pitching_accuracy'  => ['label' => 'Accuracy', 'unit' => '# strikes', 'type' => 'scale', 'min' => 0, 'max' => 5, 'default' => 2],
            'pitching_mechanics' => ['label' => 'Mechanics', 'type' => 'scale', 'min' => 1, 'max' => 9, 'default' => 5],
            'catching_disposed'  => ['label' => 'Willing to catch', 'type' => 'yesno', 'default' => 0],
        ],
    ],
    'baserunning' => [
        'title'  => 'Baserunning',
        'fields' => [
            'hitting_baserunning' => ['label' => 'First to third', 'unit' => 'seconds', 'type' => 'number', 'step' => '0.01', 'required' => true],
        ],
    ],
];

/**
 * Validates posted scores against a station's fields.
 * Returns [column => value|null] for every field, or a string error message.
 */
function station_values(string $station, array $posted): array|string
{
    $values = [];
    foreach (STATIONS[$station]['fields'] as $column => $field) {
        $raw = $posted[$column] ?? null;
        $raw = is_string($raw) ? trim($raw) : $raw;
        switch ($field['type']) {
            case 'scale':
            case 'yesno':
                [$min, $max] = $field['type'] === 'yesno' ? [0, 1] : [$field['min'], $field['max']];
                if (filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) === false) {
                    return "{$field['label']} must be $min to $max";
                }
                $values[$column] = (int) $raw;
                break;
            case 'number':
                if ($raw === null || $raw === '') {
                    if ($field['required']) {
                        return "{$field['label']} is required";
                    }
                    $values[$column] = null;
                    break;
                }
                $number = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($number === false || $number < 0 || $number >= 10000) {
                    return "{$field['label']} must be a number";
                }
                $values[$column] = round($number, 2);
                break;
        }
    }
    return $values;
}
