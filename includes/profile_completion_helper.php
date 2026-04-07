<?php

if (!function_exists('pc_parse_json_if_needed')) {
    function pc_parse_json_if_needed($value) {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '' || strtolower($raw) === 'null') {
            return null;
        }

        if (!(($raw[0] ?? '') === '{' || ($raw[0] ?? '') === '[')) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }
}

if (!function_exists('pc_has_non_empty_value')) {
    function pc_has_non_empty_value($value): bool {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (pc_has_non_empty_value($v)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string)($value ?? '')) !== '';
    }
}

if (!function_exists('pc_has_alnum_content')) {
    function pc_has_alnum_content($value): bool {
        return (bool)preg_match('/[\p{L}\p{N}]/u', (string)($value ?? ''));
    }
}

if (!function_exists('pc_has_meaningful_value')) {
    function pc_has_meaningful_value($value): bool {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (pc_has_meaningful_value($v)) {
                    return true;
                }
            }
            return false;
        }

        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return false;
        }

        return pc_has_alnum_content($raw);
    }
}

if (!function_exists('pc_is_valid_email')) {
    function pc_is_valid_email($value): bool {
        $raw = trim((string)($value ?? ''));
        return $raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('pc_is_valid_phone')) {
    function pc_is_valid_phone($value): bool {
        $digits = preg_replace('/\D+/', '', (string)($value ?? ''));
        $len = strlen($digits);
        return $len >= 10 && $len <= 15;
    }
}

if (!function_exists('pc_is_valid_date')) {
    function pc_is_valid_date($value): bool {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return false;
        }

        return strtotime($raw) !== false;
    }
}

if (!function_exists('pc_is_valid_url')) {
    function pc_is_valid_url($value): bool {
        $raw = trim((string)($value ?? ''));
        return $raw !== '' && filter_var($raw, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('pc_is_field_filled')) {
    function pc_is_field_filled($value, bool $isJson = false, bool $isArray = false, bool $mustBeMeaningful = false): bool {
        if ($isArray) {
            if (is_array($value)) {
                return pc_has_meaningful_value($value);
            }

            $decoded = pc_parse_json_if_needed($value);
            return is_array($decoded) && pc_has_meaningful_value($decoded);
        }

        if ($isJson) {
            if (is_array($value)) {
                return pc_has_meaningful_value($value);
            }

            if (is_string($value)) {
                $raw = trim($value);
                if ($raw === '' || $raw === '[]' || $raw === '{}' || strtolower($raw) === 'null') {
                    return false;
                }

                $decoded = pc_parse_json_if_needed($raw);
                if (is_array($decoded)) {
                    return pc_has_meaningful_value($decoded);
                }

                return pc_has_meaningful_value($raw);
            }

            return false;
        }

        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return false;
        }

        if ($mustBeMeaningful) {
            return pc_has_alnum_content($raw);
        }

        return true;
    }
}

if (!function_exists('compute_profile_completion_percent')) {
    /**
     * Keep this list in sync with studio_users/profile/js/script.js
     */
    function compute_profile_completion_percent(array $user): int {
        $phone = $user['phone_number'] ?? ($user['phone'] ?? null);

        $fields = [
            ['key' => 'profile_picture', 'value' => $user['profile_picture'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'username', 'value' => $user['username'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'email', 'value' => $user['email'] ?? null, 'validator' => 'email'],
            ['key' => 'phone', 'value' => $phone, 'validator' => 'phone'],
            ['key' => 'designation', 'value' => $user['designation'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'department', 'value' => $user['department'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'dob', 'value' => $user['dob'] ?? null, 'validator' => 'date'],
            ['key' => 'gender', 'value' => $user['gender'] ?? null],
            ['key' => 'bio', 'value' => $user['bio'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'address', 'value' => $user['address'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'nationality', 'value' => $user['nationality'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'blood_group', 'value' => $user['blood_group'] ?? null],
            ['key' => 'marital_status', 'value' => $user['marital_status'] ?? null],
            ['key' => 'languages', 'value' => $user['languages'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'skills', 'value' => $user['skills'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'interests', 'value' => $user['interests'] ?? null, 'mustBeMeaningful' => true],
            ['key' => 'bank_details', 'value' => $user['bank_details'] ?? null, 'isJson' => true],
            ['key' => 'education_background', 'value' => $user['education_background'] ?? null, 'isArray' => true],
            ['key' => 'work_experiences', 'value' => $user['work_experiences'] ?? null, 'isArray' => true],
            ['key' => 'emergency_contact', 'value' => $user['emergency_contact'] ?? null, 'isJson' => true],
        ];

        $total = count($fields);
        if ($total === 0) {
            return 0;
        }

        $filled = 0;
        foreach ($fields as $f) {
            $value = $f['value'] ?? null;
            $isFilled = false;

            if (($f['validator'] ?? '') === 'email') {
                $isFilled = pc_is_valid_email($value);
            } else if (($f['validator'] ?? '') === 'phone') {
                $isFilled = pc_is_valid_phone($value);
            } else if (($f['validator'] ?? '') === 'date') {
                $isFilled = pc_is_valid_date($value);
            } else {
                $isFilled = pc_is_field_filled(
                    $value,
                    !empty($f['isJson']),
                    !empty($f['isArray']),
                    !empty($f['mustBeMeaningful'])
                );
            }

            if ($isFilled) {
                $filled++;
            }
        }

        return (int)round(($filled / $total) * 100);
    }
}
